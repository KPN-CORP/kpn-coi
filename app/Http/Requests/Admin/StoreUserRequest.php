<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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

            // NIK -> non_employees.employee_id. Digits only, stored as a string
            // so a leading zero is preserved.
            'nik' => [
                'nullable',
                'string',
                'regex:/^[0-9]*$/',
                'max:50',
            ],

            // Phone -> non_employees.personal_mobile_number. Digits only,
            // accepts a leading zero (e.g. 081199922290).
            'phone' => [
                'nullable',
                'string',
                'regex:/^[0-9]*$/',
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
                Rule::unique('users')
                    ->ignore($this->user),
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

            'citizen_number' => [
                'required',
                Rule::when(
                    $this->nationality_type === 'indonesian' || $this->nationality_type === 'Indonesian',
                    ['min:15'],
                    ['max:10']
                ),
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