<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $connection = 'kpncorp';
    protected $table = 'locations';

    /**
     * The application calls this business unit "Plantations"; the HRIS side
     * (employees.group_company on some rows, and locations.company_name)
     * calls it "KPN Plantations". Everything else matches on both sides.
     */
    private const BUSINESS_UNIT_ALIASES = [
        'Plantations' => 'KPN Plantations',
    ];

    /**
     * App business unit -> locations.company_name.
     */
    public static function companyNameFor(?string $businessUnit): ?string
    {
        if ($businessUnit === null) {
            return null;
        }

        return self::BUSINESS_UNIT_ALIASES[$businessUnit] ?? $businessUnit;
    }

    /**
     * locations.company_name -> app business unit.
     */
    public static function businessUnitFor(?string $companyName): ?string
    {
        if ($companyName === null) {
            return null;
        }

        $flipped = array_flip(self::BUSINESS_UNIT_ALIASES);

        return $flipped[$companyName] ?? $companyName;
    }

    public function scopeForBusinessUnit(Builder $query, ?string $businessUnit): Builder
    {
        return $query->where(
            'company_name',
            self::companyNameFor($businessUnit)
        );
    }

    /**
     * "Head Office - Jakarta — Jakarta Selatan, DKI Jakarta"
     */
    public function getLabelAttribute(): string
    {
        $place = collect([$this->city, $this->state])
            ->filter()
            ->implode(', ');

        return collect([$this->area, $place])
            ->filter()
            ->implode(' — ');
    }
}
