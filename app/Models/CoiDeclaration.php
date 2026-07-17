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

    public function responses(): HasMany
    {
        return $this->hasMany(CoiResponse::class);
    }
}