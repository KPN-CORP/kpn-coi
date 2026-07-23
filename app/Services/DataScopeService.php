<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessUnit;
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
 *   work_area_code          -> office_area
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
    private const COLUMNS = [
        'work_area_code' => 'office_area',
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
     * Constrain a query over a people table (Employee or NonEmployee).
     */
    public function applyToPeople(Builder $query, ?User $user): Builder
    {
        foreach ($this->restrictionsFor($user) as $dimension => $values) {

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
