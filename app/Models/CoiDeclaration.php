<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\DeclarationStatus;

class CoiDeclaration extends Model
{
    use SoftDeletes;

    /**
     * 2025 is a historical import: no interactive form, just a single Yes/No
     * per employee stored under this response key, plus an uploaded attachment
     * for the "Yes" rows. See the coi_2025 import SQL.
     */
    public const LEGACY_PERIOD = 2025;
    public const LEGACY_CONFLICT_KEY = '2025_has_conflict';

    protected $connection = 'mysql';
    protected $table = 'coi_declarations'; // Adjust to your actual table name
    
    protected $fillable = [
        'user_id',
        'period',
        'status',
        'submitted_at',
        'reviewed_at',
        'admin_notes',
        'type',
        'sign',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'status' => DeclarationStatus::class,
    ];

    /**
     * user_id is ambiguous on its own: `type` decides which database it
     * points at. Both users tables auto-increment from 1, so their ids
     * overlap -- reading the wrong relation returns a real but wrong person.
     *
     *   type = employee     -> user_id references kpncorp.users.id
     *   type = non_employee -> user_id references (local) users.id
     *
     * Always branch on `type`, or use declarant() which does it for you.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function nonEmployeeUser()
    {
        return $this->belongsTo(NonEmployeeUser::class, 'user_id');
    }

    /**
     * Resolve the declarant from the correct database based on `type`.
     * A cross-database morphTo is not possible, so this switches explicitly.
     */
    public function declarant(): User|NonEmployeeUser|null
    {
        return $this->type === 'employee'
            ? $this->user
            : $this->nonEmployeeUser;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id', 'id');
    }

    /**
     * The declarant's profile row -- an Employee for employee declarations, a
     * NonEmployee for the rest. Both carry fullname / ktp / permanent_address,
     * and both relations happen to be called `employee`, so the accessors
     * below read the same fields off either.
     *
     * Reaching for `$declaration->user->employee` instead is wrong for a
     * non-employee row: `user` resolves against kpncorp, where that user_id
     * belongs to somebody else entirely or to nobody at all.
     */
    public function declarantProfile(): Employee|NonEmployee|null
    {
        return $this->declarant()?->employee;
    }

    /**
     * Declarant fields for the PDF templates. These return a placeholder
     * rather than null so a declaration whose account was since deleted still
     * renders instead of failing the whole document.
     */
    public function declarantName(): string
    {
        return (string) ($this->declarantProfile()?->fullname ?: '-');
    }

    public function declarantIdNumber(): string
    {
        return (string) ($this->declarantProfile()?->ktp ?: '-');
    }

    public function declarantAddress(): string
    {
        return (string) ($this->declarantProfile()?->permanent_address ?: '-');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CoiResponse::class);
    }
}