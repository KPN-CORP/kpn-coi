<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesDeclarationDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveDraftRequest extends FormRequest
{
    use ValidatesDeclarationDates;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'in:en,id'],
            'responses' => ['required', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateDeclarationDates($validator);

                foreach ($this->input('responses', []) as $question => $response) {

                    if (!($response['answer'] ?? false)) {
                        continue;
                    }

                    foreach ($response['details'] ?? [] as $index => $detail) {

                        foreach ($detail as $field => $value) {

                            if (blank($value)) {
                                $validator->errors()->add(
                                    "responses.$question.details.$index.$field",
                                    'This field is required.'
                                );
                            }
                        }
                    }
                }
            },
        ];
    }
}