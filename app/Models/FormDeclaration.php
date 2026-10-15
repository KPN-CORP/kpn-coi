<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormDeclaration extends Model
{
    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_NON_EMPLOYEE = 'non_employee';

    /** Read and agreed to, not signed yet. */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SIGNED = 'signed';

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
        'period',
        'business_unit',
        'status',
        'locale',
        'agreed_at',
        'signed_name',
        'variables',
        'signed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'period' => 'integer',
        'variables' => 'array',
        'signed_at' => 'datetime',
        'agreed_at' => 'datetime',
    ];

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
