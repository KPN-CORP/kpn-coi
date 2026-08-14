<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeclarationIdentity;
use App\Models\Employee;
use App\Models\NonEmployeeUser;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Groups the several identities one human holds -- a non-employee login and one
 * or more employee stints across rehires -- into a single `person`, so a
 * declaration made under any past identity is readable from the current one.
 *
 * The links come from two soft, cross-database anchors:
 *
 *   - employees.`old_employee_id_(rehired)` chains a rehired stint back to its
 *     immediately-previous employee_id (E3 -> E2 -> E1), and
 *   - users.employee_id ties a converted non-employee login to the HRIS
 *     employee_id it was promoted into.
 *
 * Neither can be a foreign key (the two tables live in different databases), so
 * the grouping is materialised here into declaration_identities and read back
 * by DeclarationScopeService. Resolution follows the rehire chain **backward
 * only** (current stint -> its previous -> ...) plus the non-employee logins
 * attached to each stint. It never walks forward ("who lists me as previous?"):
 * that fans out across every row sharing a sentinel value and would merge
 * unrelated people. Completeness across a whole chain instead comes from the
 * backfill, which seeds from every declarant and union-finds their groups
 * together. Nothing about coi_declarations is ever touched: read-side only.
 */
class IdentityLinkService
{
    /**
     * Safety valve against a malformed rehire chain (a cycle, or an
     * implausibly long one). A real component is a handful of nodes; this only
     * ever bites bad HRIS data, and truncating is preferable to looping.
     */
    private const MAX_NODES = 50;

    /**
     * Resolve every identity that belongs to the same person as the given
     * account, without writing anything.
     *
     * @return array{
     *     identities: list<array{type: string, user_id: int, employee_id: ?string}>,
     *     orphans: list<string>,
     *     primary: ?array{type: string, user_id: int}
     * }
     *   orphans are employee_ids referenced by the chain but absent from
     *   employees (e.g. a purged stint) -- reported, never fatal.
     */
    public function resolveIdentities(User|NonEmployeeUser $account): array
    {
        $type = $account instanceof User
            ? DeclarationIdentity::TYPE_EMPLOYEE
            : DeclarationIdentity::TYPE_NON_EMPLOYEE;

        $employeeId = trim((string) $account->employee_id) ?: null;

        return $this->resolveFromSeed($type, (int) $account->id, $employeeId);
    }

    /**
     * Resolve the connected component from a raw identity, without needing a
     * live account model. Used by the backfill, which seeds from the
     * (user_id, type) pairs that actually appear in coi_declarations -- some of
     * whose accounts no longer exist.
     *
     * For an employee seed, user_id is employees.id (it mirrors users.id
     * one-to-one) and employee_id may be null: it is looked up from employees
     * when omitted, so the chain can still be walked.
     *
     * @return array{
     *     identities: list<array{type: string, user_id: int, employee_id: ?string}>,
     *     orphans: list<string>,
     *     primary: ?array{type: string, user_id: int}
     * }
     */
    public function resolveFromSeed(string $type, int $userId, ?string $employeeId = null): array
    {
        $employeeId = $employeeId !== null ? (trim($employeeId) ?: null) : null;

        // Recover the seed's employee_id from its row when the caller did not
        // supply it, so a bare declarant pair can still reach its rehire chain.
        if ($employeeId === null) {
            $employeeId = $type === DeclarationIdentity::TYPE_EMPLOYEE
                ? (Employee::query()->whereKey($userId)->value('employee_id'))
                : (NonEmployeeUser::query()->whereKey($userId)->value('employee_id'));
            $employeeId = $employeeId !== null ? (trim((string) $employeeId) ?: null) : null;
        }

        /** @var array<string, array{type: string, user_id: int, employee_id: ?string}> $identities */
        $identities = [];
        $orphans = [];
        // employee_ids that are some later stint's "previous" -- used to pick
        // the tip of the chain (the current stint nobody points back to).
        $prevPointers = [];

        $add = function (string $type, int $userId, ?string $employeeId) use (&$identities): void {
            $key = $type.':'.$userId;
            // First writer wins, but fill in an employee_id we did not have yet.
            if (! isset($identities[$key])) {
                $identities[$key] = [
                    'type' => $type,
                    'user_id' => $userId,
                    'employee_id' => $employeeId,
                ];
            } elseif ($identities[$key]['employee_id'] === null && $employeeId !== null) {
                $identities[$key]['employee_id'] = $employeeId;
            }
        };

        $queue = [];

        $add($type, $userId, $employeeId);
        if ($employeeId !== null) {
            $queue[] = $employeeId;
        }

        $visited = [];
        $expanded = 0;

        while ($queue !== [] && $expanded < self::MAX_NODES) {
            $employeeId = array_shift($queue);

            if (isset($visited[$employeeId])) {
                continue;
            }
            $visited[$employeeId] = true;
            $expanded++;

            $rows = Employee::query()
                ->whereNull('deleted_at')
                ->where('employee_id', $employeeId)
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                $orphans[] = $employeeId;
            }

            foreach ($rows as $row) {
                $add(DeclarationIdentity::TYPE_EMPLOYEE, (int) $row->id, $employeeId);

                // Backward ONLY: follow this stint to its immediately-previous
                // one. We deliberately never walk forward ("who lists me as
                // their previous?"). A forward hop fans out across every row
                // sharing a value, and rehire exports routinely put a sentinel
                // (0, '-', 'N/A', ...) in this column instead of null for
                // non-rehired staff -- forward traversal through that sentinel
                // would merge the whole company into one person and leak every
                // declaration to everyone. Backward is a simple one-parent
                // chain and cannot fan out; the current stint always seeds the
                // walk, and the backfill's union-find joins earlier stints to it
                // from their own seeds, so backward alone is complete.
                $previous = $row->previousEmployeeId();
                if ($previous !== null) {
                    $prevPointers[$previous] = true;
                    if (! isset($visited[$previous])) {
                        $queue[] = $previous;
                    }
                }
            }

            // Non-employee logins converted into this HRIS id.
            $nonEmployees = NonEmployeeUser::query()
                ->where('employee_id', $employeeId)
                ->get();

            foreach ($nonEmployees as $nonEmployee) {
                $add(DeclarationIdentity::TYPE_NON_EMPLOYEE, (int) $nonEmployee->id, $employeeId);
            }
        }

        $list = array_values($identities);

        return [
            'identities' => $list,
            'orphans' => array_values(array_unique($orphans)),
            'primary' => $this->choosePrimary($list, $prevPointers),
        ];
    }

    /**
     * The person's current identity: the tip of the rehire chain (the employee
     * stint nobody points back to), or the lone non-employee login if they were
     * never an employee. Falls back to the highest user_id when bad data leaves
     * the tip ambiguous, so exactly one identity is always chosen.
     *
     * @param  list<array{type: string, user_id: int, employee_id: ?string}>  $identities
     * @param  array<string, bool>  $prevPointers
     * @return ?array{type: string, user_id: int}
     */
    private function choosePrimary(array $identities, array $prevPointers): ?array
    {
        if ($identities === []) {
            return null;
        }

        $employees = array_values(array_filter(
            $identities,
            fn ($identity) => $identity['type'] === DeclarationIdentity::TYPE_EMPLOYEE
        ));

        $pool = $employees;

        if ($pool === []) {
            // Never an employee -- the (single) non-employee login is current.
            $pool = $identities;
        } else {
            // Tip = an employee stint whose employee_id is not another stint's
            // "previous". A clean chain has exactly one.
            $tips = array_values(array_filter(
                $employees,
                fn ($identity) => $identity['employee_id'] === null
                    || ! isset($prevPointers[$identity['employee_id']])
            ));

            if (count($tips) === 1) {
                return [
                    'type' => $tips[0]['type'],
                    'user_id' => $tips[0]['user_id'],
                ];
            }
        }

        // Ambiguous (branching or cyclic data): newest id wins, deterministically.
        usort($pool, fn ($a, $b) => $b['user_id'] <=> $a['user_id']);

        return [
            'type' => $pool[0]['type'],
            'user_id' => $pool[0]['user_id'],
        ];
    }

    /**
     * Resolve the account's person and persist the grouping. Idempotent: the
     * unique (type, user_id) key makes every identity an upsert, so re-running
     * on each login converges rather than duplicating.
     */
    public function syncFor(User|NonEmployeeUser $account): ?Person
    {
        $resolved = $this->resolveIdentities($account);

        if ($resolved['identities'] === []) {
            return null;
        }

        return $this->persistGroup($resolved['identities'], $resolved['primary']);
    }

    /**
     * Write one resolved group into persons + declaration_identities under a
     * single person_id, merging any person_ids the identities were previously
     * split across. Shared by login sync and the backfill command.
     *
     * @param  list<array{type: string, user_id: int, employee_id: ?string}>  $identities
     * @param  ?array{type: string, user_id: int}  $primary
     */
    public function persistGroup(array $identities, ?array $primary): ?Person
    {
        if ($identities === []) {
            return null;
        }

        return DB::connection('mysql')->transaction(function () use ($identities, $primary) {
            $existing = DeclarationIdentity::query()
                ->where(function ($query) use ($identities) {
                    foreach ($identities as $identity) {
                        $query->orWhere(fn ($inner) => $inner
                            ->where('type', $identity['type'])
                            ->where('user_id', $identity['user_id']));
                    }
                })
                ->get();

            $personIds = $existing->pluck('person_id')->unique()->sort()->values();

            if ($personIds->isEmpty()) {
                $personId = (int) Person::query()->create([])->id;
            } else {
                // Reuse the lowest existing person and fold the rest into it, so
                // two chains discovered to be one person collapse cleanly.
                $personId = (int) $personIds->first();
                $mergeAway = $personIds->reject(fn ($id) => (int) $id === $personId);

                if ($mergeAway->isNotEmpty()) {
                    DeclarationIdentity::query()
                        ->whereIn('person_id', $mergeAway)
                        ->update(['person_id' => $personId]);
                    Person::query()->whereIn('id', $mergeAway)->delete();
                }
            }

            $primaryKey = $primary !== null
                ? $primary['type'].':'.$primary['user_id']
                : null;

            foreach ($identities as $identity) {
                $key = $identity['type'].':'.$identity['user_id'];
                $isPrimary = $key === $primaryKey;

                DeclarationIdentity::query()->updateOrCreate(
                    [
                        'type' => $identity['type'],
                        'user_id' => $identity['user_id'],
                    ],
                    [
                        'person_id' => $personId,
                        'employee_id' => $identity['employee_id'],
                        'is_primary' => $isPrimary,
                        'source' => $this->sourceFor($identity, $isPrimary),
                    ]
                );
            }

            // Enforce exactly one primary per person, even if a merged-away
            // row carried a stale flag from an earlier sync.
            DeclarationIdentity::query()
                ->where('person_id', $personId)
                ->when($primary !== null, fn ($query) => $query->where(
                    fn ($inner) => $inner
                        ->where('type', '!=', $primary['type'])
                        ->orWhere('user_id', '!=', $primary['user_id'])
                ))
                ->update(['is_primary' => false]);

            if ($primary !== null) {
                DeclarationIdentity::query()
                    ->where('type', $primary['type'])
                    ->where('user_id', $primary['user_id'])
                    ->update(['is_primary' => true]);
            }

            return Person::query()->find($personId);
        });
    }

    /**
     * Audit label for how an identity joined the group, relative to the current
     * (primary) one.
     *
     * @param  array{type: string, user_id: int, employee_id: ?string}  $identity
     */
    private function sourceFor(array $identity, bool $isPrimary): string
    {
        if ($isPrimary) {
            return DeclarationIdentity::SOURCE_SELF;
        }

        return $identity['type'] === DeclarationIdentity::TYPE_NON_EMPLOYEE
            ? DeclarationIdentity::SOURCE_CONVERSION
            : DeclarationIdentity::SOURCE_REHIRE;
    }
}
