<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'in:en,id'],

            'responses' => ['required', 'array'],

            'consent' => ['accepted'],
        ];
    }
}