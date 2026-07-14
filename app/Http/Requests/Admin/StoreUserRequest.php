<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

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
                Rule::in(['indonesian', 'foreigner']),
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
                    $this->nationality_type === 'indonesian',
                    ['digits:16'],
                    ['max:10']
                ),
            ],

            'business_unit' => [
                'required',
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