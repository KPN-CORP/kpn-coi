<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A declaration's `period` is a month stored as one number, YYYYMM (202610 =
 * October 2026), computed from period_year/period_month. Templates have no
 * period and the yearly rules (pending, reports) go by period_year; what is
 * left here reads YYYYMM values -- old report links and {{period}} snapshots
 * taken before signatures were stamped with a year. COI periods stay plain
 * years and never pass through here.
 */
final class FormPeriod
{
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
}
