<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

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
                    $this->nationality_type === 'indonesian',
                    ['digits:16'],
                    ['max:10']
                ),
            ],

            'nationality_type' => [
                'required',
                Rule::in(['indonesian', 'foreigner']),
            ],

            'nationality' => [
                'exclude_if:nationality_type,indonesian',
                'required_if:nationality_type,foreigner',
                'string',
                'max:100',
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