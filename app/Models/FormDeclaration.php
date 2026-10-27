<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormDeclaration extends Model
{
    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_NON_EMPLOYEE = 'non_employee';

    /** Read and agreed to, not signed yet. */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SIGNED = 'signed';

    /** Signed in the app (the column default). */
    public const SOURCE_APP = 'app';

    /** Loaded from a spreadsheet in the Import Center. */
    public const SOURCE_IMPORT = 'import';

    /**
     * A row is a draft unless signing says otherwise. The column's database
     * default is `signed`; this keeps a forgotten status (an import, a
     * seeder) from producing a "signature" with no name or snapshot.
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $connection = 'mysql';

    protected $table = 'form_declarations';

    protected $fillable = [
        'form_type_id',
        'template_id',
        'user_id',
        'type',
        'period_year',
        'period_month',
        'business_unit',
        'status',
        'locale',
        'agreed_at',
        'signed_name',
        'variables',
        'signed_at',
        'ip_address',
        'user_agent',
        'source',
        'imported_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'variables' => 'array',
        'signed_at' => 'datetime',
        'agreed_at' => 'datetime',
    ];

    /**
     * YYYYMM. Computed by the database from period_year and period_month
     * (never written); worked out here too for a model not saved yet, such
     * as the template editor's preview.
     */
    protected function period(): Attribute
    {
        return Attribute::get(function ($value, array $attributes) {
            if ($value !== null) {
                return (int) $value;
            }

            return isset($attributes['period_year'], $attributes['period_month'])
                ? (int) $attributes['period_year'] * 100 + (int) $attributes['period_month']
                : null;
        });
    }

    /**
     * Only real signatures. A draft is a person's bookmark ("I have read and
     * agree") and counts as not signed everywhere: pending lists, reports,
     * template locking, PDFs.
     */
    public function scopeSigned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SIGNED);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    /**
     * Signed templates are locked, but may still be soft-deleted from an older
     * build -- keep resolving them so the signed document always renders.
     */
    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class)->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'template_id')
            ->withTrashed();
    }

    /**
     * Resolve the signer from the correct database based on `type` -- see
     * CoiDeclaration::declarant(); the two users tables' ids overlap.
     */
    public function declarant(): User|NonEmployeeUser|null
    {
        return $this->type === self::TYPE_EMPLOYEE
            ? User::query()->find($this->user_id)
            : NonEmployeeUser::query()->find($this->user_id);
    }
}
