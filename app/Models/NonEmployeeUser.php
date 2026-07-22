<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'password_set_at',
    ];

    protected $casts = [
        'password_set_at' => 'datetime',
    ];

    public function declarations(): HasMany
    {
        return $this->hasMany(
            CoiDeclaration::class,
            'user_id'
        )->where('type', 'non_employee');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(NonEmployee::class, 'user_id');
    }

    /**
     * HRIS profile, once this user has been promoted to employee.
     * Soft link -- employees lives in the kpncorp database, so no FK is possible.
     */
    public function hrEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
