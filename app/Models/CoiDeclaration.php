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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function nonEmployeeUser()
    {
        return $this->belongsTo(NonEmployeeUser::class, 'user_id');
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