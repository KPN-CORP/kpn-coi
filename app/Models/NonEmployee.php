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
        'gender',
        'current_address',
        'ktp',
    ];


    public function coiDeclaration()
    {
        return $this->hasMany(CoiDeclaration::class, 'user_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(NonEmployeeUser::class, 'id');
    }
}
