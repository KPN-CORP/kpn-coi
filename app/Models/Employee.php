<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasFactory;

    protected $connection = 'kpncorp';
    protected $table = 'employees';
    
    protected $fillable = [
        // Kolom-kolom lainnya,
        'access_menu','id','employee_id', 'fullname', 'gender', 'email', 'group_company',
        'designation', 'designation_name', 'job_level', 'company_name', 'contribution_level_code',
        'work_area_code', 'office_area', 'manager_l1_id', 'manager_l2_id',
        'employee_type', 'unit', 'date_of_joining', 'user_id'
    ];

    public function subordinatesL1()
    {
        return $this->hasMany(
            self::class,
            'manager_l1_id',
            'employee_id'
        );
    }

    public function subordinatesL2()
    {
        return $this->hasMany(
            self::class,
            'manager_l2_id',
            'employee_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id');
    }
    
}
