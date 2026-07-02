<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{

    use HasRoles;
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $connection = 'kpncorp';

    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'password',
    ];

    
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(
            CoiDeclaration::class,
            'user_id'
        )->where('type', 'employee');
    }


    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'id');
    }
}
