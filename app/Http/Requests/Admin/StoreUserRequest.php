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

            // locations lives in the kpncorp database, so no FK backs this --
            // the rule is the only guard that the id exists and belongs to the
            // selected business unit.
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('kpncorp.locations', 'id')->where(
                    fn ($query) => $query->where(
                        'company_name',
                        Location::companyNameFor($this->business_unit)
                    )
                ),
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