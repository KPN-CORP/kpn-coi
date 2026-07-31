<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\Companies;
use App\Models\Employee;
use App\Models\NonEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data restrictions configured per role (Role management -> Data Restrictions).
 *
 * A role may pin which people an admin is allowed to see along three
 * dimensions. The restriction keys are the ones the role form posts; the
 * columns they filter are named differently, and both people tables --
 * kpncorp.employees and (local) non_employees -- carry all three:
 *
 *   work_area_code          -> work_area_code (falls back to office_area, see below)
 *   group_company           -> group_company
 *   contribution_level_code -> contribution_level_code
 *
 * An empty or missing dimension means "unrestricted on this dimension"; a user
 * whose roles carry no restrictions at all sees everything. A row whose value
 * for a restricted dimension is null or empty is *not* visible -- a restriction
 * denies by default rather than leaking unclassified rows.
 *
 * Declarations cannot be filtered with `whereHas('employee')`: coi_declarations
 * lives in the app database and employees in kpncorp, so MySQL would look for
 * `employees` in the app schema and fail outright. They are narrowed instead by
 * resolving the allowed ids on each people table first, then matching them per
 * declaration `type` -- the two types number their user_id from different
 * databases, so they must never share one id list.
 */
class DataScopeService
{
    /**
     * The holding company at the top of the group. For the report's
     * contribution-level cascade it means "the whole group", so selecting it
     * offers every company/level rather than only its own. Note: the *employee*
     * business-unit filter does NOT widen for it -- KPN Corporation is a real
     * group_company value (its own staff) and narrows like any other unit.
     */
    public const GROUP_HEAD = 'KPN Corporation';

    private const COLUMNS = [
        'work_area_code' => 'work_area_code',
        'group_company' => 'group_company',
        'contribution_level_code' => 'contribution_level_code',
    ];

    /** @var array<string, array<string, list<string>>> */
    private array $restrictionCache = [];

    /** @var array<string, string>|null kode_bisnis -> nama_bisnis */
    private ?array $businessUnitNames = null;

    /**
     * The dimensions this user is restricted on, empty values dropped.
     *
     * @return array<string, list<string>>
     */
    public function restrictionsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $cacheKey = (string) $user->getKey();

        if (isset($this->restrictionCache[$cacheKey])) {
            return $this->restrictionCache[$cacheKey];
        }

        $roles = $user->roles;

        $restrictions = [];

        foreach (array_keys(self::COLUMNS) as $dimension) {

            $values = [];

            foreach ($roles as $role) {

                $roleValues = collect(
                    data_get($role->restrictions, $dimension, [])
                )
                    ->filter(fn ($value) => filled($value))
                    ->values()
                    ->all();

                // A role that leaves this dimension open lets everything
                // through, so holding it widens the whole dimension.
                if ($roleValues === []) {
                    $values = [];

                    break;
                }

                $values = array_merge($values, $roleValues);
            }

            if ($values !== []) {
                $restrictions[$dimension] = array_values(
                    array_unique($values)
                );
            }
        }

        return $this->restrictionCache[$cacheKey] = $restrictions;
    }

    public function isRestricted(?User $user): bool
    {
        return $this->restrictionsFor($user) !== [];
    }

    /**
     * The business units to offer this user in a filter.
     *
     * Sourced from master_bisnisunits rather than from whatever the people
     * tables happen to carry, so a unit stays selectable in a period where
     * none of its rows exist yet, and a value left behind by a terminated
     * employee is not offered as if it were a live unit. "Others" is the HRIS
     * catch-all bucket and is never a filter.
     *
     * Scoped like the rows behind it: a restricted admin must not be offered a
     * unit they cannot open. Selecting one anyway returns nothing -- the
     * restriction is enforced on the queries, not here -- but an option that
     * can only ever come back empty has no business being in the list.
     */
    public function businessUnitOptions(?User $user): Collection
    {
        $allowed = $this->allowedGroupCompanies($user);

        return BusinessUnit::query()
            ->whereNotNull('nama_bisnis')
            ->whereNot('nama_bisnis', 'others')
            ->when(
                $allowed !== null,
                fn ($query) => $query->whereIn('nama_bisnis', $allowed)
            )
            ->orderBy('nama_bisnis')
            ->pluck('nama_bisnis')
            ->values();
    }

    /**
     * The business units this user is allowed to see, or null when their roles
     * leave that dimension open. Both names and legacy kode_bisnis values are
     * returned, same as applyToPeople() matches on.
     *
     * @return list<string>|null
     */
    private function allowedGroupCompanies(?User $user): ?array
    {
        $values = $this->restrictionsFor($user)['group_company'] ?? null;

        return $values === null
            ? null
            : $this->groupCompanyValues($values);
    }

    /**
     * The contribution levels to offer this user in a filter -- code + name so
     * the dropdown can show the readable name while filtering on the code the
     * people tables store. Each option also carries its business_unit so the UI
     * can cascade: pick a business unit first, then only its levels are offered.
     * The tie lives in companies.company_name ("KPN Corporation,<Unit>"); a
     * contribution_level_code belongs to exactly one unit.
     *
     * Scoped like businessUnitOptions(): a restricted admin is only offered the
     * levels their role allows. No code/name translation is needed here (unlike
     * group company) because the restriction, the option source, and
     * employees.contribution_level_code all speak the same code.
     */
    public function contributionLevelOptions(?User $user): Collection
    {
        $allowed = $this->restrictionsFor($user)['contribution_level_code'] ?? null;

        return Companies::query()
            ->select('contribution_level_code', 'contribution_level', 'company_name')
            ->whereNotNull('contribution_level_code')
            ->when(
                $allowed !== null,
                fn ($query) => $query->whereIn('contribution_level_code', $allowed)
            )
            ->orderBy('contribution_level')
            ->get()
            ->unique('contribution_level_code')
            ->map(fn ($item) => [
                'code' => $item->contribution_level_code,
                'name' => $item->contribution_level,
                'business_unit' => $this->companyBusinessUnit($item->company_name),
            ])
            ->values();
    }

    /**
     * The business unit a companies row belongs to. company_name is stored as
     * "KPN Corporation,<Unit>", and the people tables / BU filter carry the unit
     * as "Plantations", never "KPN Plantations", so normalise that one case.
     */
    private function companyBusinessUnit(?string $companyName): string
    {
        $unit = trim(explode(',', (string) $companyName)[1] ?? '');

        return $unit === 'KPN Plantations'
            ? 'Plantations'
            : $unit;
    }

    /**
     * Constrain a query over a people table (Employee or NonEmployee).
     */
    public function applyToPeople(Builder $query, ?User $user): Builder
    {
        foreach ($this->restrictionsFor($user) as $dimension => $values) {

            // Work area matches the canonical work_area_code, but also the
            // office_area name so roles saved before the switch to work_area_code
            // keep scoping instead of silently matching nothing. Both people
            // tables carry both columns.
            if ($dimension === 'work_area_code') {
                $query->where(
                    fn (Builder $inner) => $inner
                        ->whereIn(
                            $query->getModel()->qualifyColumn('work_area_code'),
                            $values
                        )
                        ->orWhereIn(
                            $query->getModel()->qualifyColumn('office_area'),
                            $values
                        )
                );

                continue;
            }

            $query->whereIn(
                $query->getModel()->qualifyColumn(
                    self::COLUMNS[$dimension]
                ),
                $dimension === 'group_company'
                    ? $this->groupCompanyValues($values)
                    : $values
            );
        }

        return $query;
    }

    /**
     * Constrain a coi_declarations query. Employee and non-employee rows are
     * matched separately because `user_id` points at a different database for
     * each -- both tables start their ids at 1, so a shared list would expose
     * unrelated people.
     */
    public function applyToDeclarations(Builder $query, ?User $user): Builder
    {
        if (! $this->isRestricted($user)) {
            return $query;
        }

        $employeeIds = $this->allowedEmployeeIds($user);

        $nonEmployeeUserIds = $this->allowedNonEmployeeUserIds($user);

        return $query->where(
            fn (Builder $scoped) => $scoped
                ->where(
                    fn (Builder $inner) => $inner
                        ->where('type', 'employee')
                        ->whereIn('user_id', $employeeIds)
                )
                ->orWhere(
                    fn (Builder $inner) => $inner
                        ->where('type', 'non_employee')
                        ->whereIn('user_id', $nonEmployeeUserIds)
                )
        );
    }

    /**
     * employees.id -- what an `employee` declaration stores in user_id.
     */
    public function allowedEmployeeIds(?User $user): Collection
    {
        return $this->applyToPeople(
            Employee::query()->whereNull('deleted_at'),
            $user
        )->pluck('id');
    }

    /**
     * non_employees.user_id -- what a `non_employee` declaration stores in
     * user_id. Profiles never linked to a login carry none and are skipped.
     */
    public function allowedNonEmployeeUserIds(?User $user): Collection
    {
        return $this->applyToPeople(
            NonEmployee::query()->whereNotNull('user_id'),
            $user
        )->pluck('user_id');
    }

    /**
     * Roles saved before the role form switched to business unit names hold
     * master_bisnisunits.kode_bisnis ("BU03"), while both people tables store
     * the name ("Cement"). Match on either so those roles keep working.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    private function groupCompanyValues(array $values): array
    {
        $this->businessUnitNames ??= BusinessUnit::query()
            ->pluck('nama_bisnis', 'kode_bisnis')
            ->all();

        $names = array_values(
            array_intersect_key(
                $this->businessUnitNames,
                array_flip($values)
            )
        );

        return array_values(
            array_unique([...$values, ...$names])
        );
    }
}
