<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A form period is a month, stored as one number: YYYYMM (202610 = October
 * 2026). One comparable integer keeps the per-period rules (one active
 * template per unit, pending, reports) simple. COI periods stay plain years
 * and never pass through here.
 */
final class FormPeriod
{
    public static function make(int $year, int $month): int
    {
        return $year * 100 + $month;
    }

    public static function current(): int
    {
        $now = now();

        return self::make($now->year, $now->month);
    }

    /**
     * The year a report filter asks for. Filters take a year; an old link
     * may still carry a month (YYYYMM), which means its year.
     */
    public static function yearFrom(mixed $value): int
    {
        $value = (int) $value;

        return $value > 9999 ? self::year($value) : $value;
    }

    public static function year(int $period): int
    {
        return intdiv($period, 100);
    }

    public static function month(int $period): int
    {
        return $period % 100;
    }

    public static function isValid(mixed $period): bool
    {
        if (! is_numeric($period)) {
            return false;
        }

        $period = (int) $period;
        $month = self::month($period);

        return self::year($period) >= 2000 && self::year($period) <= 2100 && $month >= 1 && $month <= 12;
    }

    /** Last day of the period: who had joined by then counts toward it. */
    public static function end(int $period): Carbon
    {
        return Carbon::create(self::year($period), self::month($period), 1)->endOfMonth();
    }

    /** "October 2026" / "Oktober 2026". */
    public static function label(int $period, string $locale = 'en'): string
    {
        if (! self::isValid($period)) {
            return (string) $period;
        }

        return Carbon::create(self::year($period), self::month($period), 1)
            ->locale($locale === 'id' ? 'id' : 'en')
            ->translatedFormat('F Y');
    }

    /** @return list<mixed> */
    public static function yearRules(): array
    {
        return ['required', 'integer', 'min:2000', 'max:2100'];
    }

    /** @return list<mixed> */
    public static function monthRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:12'];
    }
}
