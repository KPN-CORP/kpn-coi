<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    /**
     * HRIS column that points a rehired stint at its immediately-previous
     * employee_id (E3 -> E2 -> E1, a backward chain, null at the first
     * stint). The name is fixed by the upstream HR system and carries
     * parentheses, which are only legal inside backticks -- Laravel's grammar
     * wraps the whole identifier, so referencing it through this constant keeps
     * the awkward spelling in exactly one place. Read it via previousEmployeeId().
     */
    public const REHIRE_PREVIOUS_ID = 'old_employee_id_(rehired)';

    protected $connection = 'kpncorp';

    protected $table = 'employees';

    protected $fillable = [
        // Kolom-kolom lainnya,
        'access_menu', 'id', 'employee_id', 'fullname', 'gender', 'email', 'group_company',
        'designation', 'designation_name', 'job_level', 'company_name', 'contribution_level_code',
        'work_area_code', 'office_area', 'manager_l1_id', 'manager_l2_id',
        'employee_type', 'unit', 'date_of_joining', 'user_id',
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

    public function coiDeclaration()
    {
        return $this->hasMany(
            CoiDeclaration::class,
            'user_id',
            'id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id');
    }

    /**
     * The employee_id of this stint's immediately-previous stint, or null if
     * this is the person's first time employed. Empty strings collapse to null
     * so callers only have to check for null when walking the rehire chain.
     */
    public function previousEmployeeId(): ?string
    {
        $previous = trim((string) $this->getAttribute(self::REHIRE_PREVIOUS_ID));

        return $previous === '' ? null : $previous;
    }
}
