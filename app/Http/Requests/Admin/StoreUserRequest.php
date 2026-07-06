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

            'citizen_number' => [
                'required',
                Rule::unique('non_employees', 'ktp')
                    ->ignore($this->user),
            ],

            'role' => [
                'required',
                Rule::in([
                    'non-employee',
                    'employee',
                    'admin',
                ]),
            ],

            'gender' => [
                'required',
            ],

            'address' => [
                'required',
            ],
        ];
    }
}