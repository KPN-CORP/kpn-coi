<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonEmployee extends Model
{
    use HasFactory;

    protected $table = 'non_employees';

    protected $fillable = [
        'user_id',
        'fullname',
        'email',
        'date_of_joining',
        'permanent_address',
        'ktp',
        'nationality',
        'group_company',
        'location_id',
    ];

    /**
     * Soft link: locations lives in the kpncorp database, so there is no FK.
     * Eloquent still resolves it -- Location declares its own connection.
     */
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }


    /**
     * Declarations belong to the user, not to this profile row, so the local
     * key must be user_id -- coi_declarations.user_id references users.id.
     */
    public function coiDeclaration()
    {
        return $this->hasMany(
            CoiDeclaration::class,
            'user_id',
            'user_id'
        )->where('type', 'non_employee');
    }

    public function user()
    {
        return $this->belongsTo(NonEmployeeUser::class, 'user_id');
    }
}
