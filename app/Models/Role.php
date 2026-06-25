<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'guard_name',
        'restrictions',
    ];

    protected $casts = [
        'restrictions' => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'model_has_roles',
            'role_id',
            'model_id'
        )->wherePivot(
            'model_type',
            User::class
        );
    }
}