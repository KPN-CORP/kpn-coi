<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * The "Employee Status" filter on every people table (reports, team pages).
 *
 * Active / inactive is `deleted_at` on the person's own row -- kpncorp
 * `employees` (set by the HRIS when someone leaves) or local `non_employees`.
 * Active is the default: before this filter existed, employee rows were
 * always limited to active people, so an unfiltered table still reads the
 * same.
 */
final class EmployeeStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const ALL = 'all';

    /** A request value, falling back to active for anything unknown. */
    public static function from(mixed $value): string
    {
        return in_array($value, [self::ACTIVE, self::INACTIVE, self::ALL], true)
            ? $value
            : self::ACTIVE;
    }

    /**
     * @template T of Builder
     *
     * @param  T  $query  an Employee or NonEmployee query
     * @return T
     */
    public static function apply(Builder $query, mixed $value): Builder
    {
        return match (self::from($value)) {
            self::ACTIVE => $query->whereNull('deleted_at'),
            self::INACTIVE => $query->whereNotNull('deleted_at'),
            self::ALL => $query,
        };
    }
}
