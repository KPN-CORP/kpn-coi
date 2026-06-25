<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class NonEmployeeUser extends Authenticatable
{
    use Notifiable;
    use HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'password',
    ];

    public function declarations(): HasMany
    {
        return $this->hasMany(CoiDeclaration::class, 'user_id', 'id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(NonEmployee::class, 'id');
    }
}
