<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonEmployee extends Model
{
    use HasFactory;

    protected $table = 'non_employees';

    protected $fillable = [
        'fullname',
        'email',
        'date_of_joining',
        'permanent_address',
        'ktp',
        'nationality',
        'group_company',
    ];


    public function coiDeclaration()
    {
        return $this->hasMany(
            CoiDeclaration::class,
            'user_id'
        )->where('type', 'non_employee');
    }

    public function user()
    {
        return $this->belongsTo(NonEmployeeUser::class);
    }
}
