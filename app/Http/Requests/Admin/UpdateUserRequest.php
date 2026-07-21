<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'nullable',
                'string',
                'max:50',
            ],

            // NIK -> non_employees.employee_id
            'nik' => [
                'nullable',
                'string',
                'max:50',
            ],

            // Phone -> non_employees.personal_mobile_number
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($this->route('user')),
            ],

            'citizen_number' => [
                'nullable',
                'string',
                Rule::when(
                   $this->nationality_type === 'indonesian' || $this->nationality_type === 'Indonesian',
                    ['min:15'],
                    ['max:10']
                ),
            ],

            'nationality_type' => [
                'required',
                Rule::in(['indonesian', 'Indonesian', 'foreigner']),
            ],

            'nationality' => [
                Rule::requiredIf($this->nationality_type === 'foreigner'),
                'nullable',
                'string',
                'max:100',
            ],

            'business_unit' => [
                'required',
            ],

            // Office area (locations.area). Free-form so the dropdown selection
            // is accepted; the work_area code is resolved from it at save time.
            'office_area' => [
                'nullable',
                'string',
                'max:255',
            ],

            'date_of_joining' => [
                'required',
            ],

            'address' => [
                'required',
            ],
        ];
    }
}