<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A kind of form people sign (Pakta Integritas, ...). Each active type gets
 * its own employee menu item and page; templates and declarations belong to
 * exactly one type, and the "one active template per business unit per
 * period" and "one signature per period" rules apply within a type.
 */
class FormType extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql';

    protected $table = 'form_types';

    protected $fillable = [
        'code',
        'name_en',
        'name_id',
        'description_en',
        'description_id',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Route model binding resolves /employee/forms/{formType} by code.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function templates(): HasMany
    {
        return $this->hasMany(FormTemplate::class);
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(FormDeclaration::class);
    }

    public function signedDeclarations(): HasMany
    {
        return $this->declarations()->signed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name_en');
    }

    /**
     * The type a report/dashboard filter points at: the requested id when it
     * exists, otherwise the first active type (then any type), so the screens
     * always show one type rather than mixing incomparable forms together.
     */
    public static function selectedOrDefault(mixed $id): ?self
    {
        $requested = filled($id) ? self::query()->find((int) $id) : null;

        return $requested
            ?? self::query()->active()->ordered()->first()
            ?? self::query()->ordered()->first();
    }

    /**
     * Options for a form type filter.
     *
     * @return list<array{id: int, code: string, name: array{en: string, id: string}, is_active: bool}>
     */
    public static function options(): array
    {
        return self::query()
            ->ordered()
            ->get()
            ->map(fn (self $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => ['en' => $type->name_en, 'id' => $type->name_id],
                'is_active' => $type->is_active,
            ])
            ->all();
    }

    public function name(string $locale): string
    {
        return $locale === 'id' ? $this->name_id : $this->name_en;
    }

    /**
     * @return array{id: int, code: string, name: array{en: string, id: string}, description: array{en: ?string, id: ?string}, icon: string}
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => ['en' => $this->name_en, 'id' => $this->name_id],
            'description' => ['en' => $this->description_en, 'id' => $this->description_id],
            'icon' => $this->icon,
        ];
    }
}
