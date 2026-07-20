<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesDeclarationDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitDeclarationRequest extends FormRequest
{
    use ValidatesDeclarationDates;

    public function authorize(): bool
    {
        return true;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateDeclarationDates($validator);
            },
        ];
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'in:en,id'],

            'responses' => ['required', 'array'],

            'responses.equity_ownership.details.*.ownership_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'responses.equity_ownership.details.*.ownership_percentage.numeric' =>
                'Percentage of Ownership must be a number.',
            'responses.equity_ownership.details.*.ownership_percentage.max' =>
                'Percentage of Ownership cannot be greater than 100.',
            'responses.equity_ownership.details.*.ownership_percentage.min' =>
                'Percentage of Ownership cannot be less than 0.',
        ];
    }
}