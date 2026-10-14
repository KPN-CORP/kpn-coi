<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\FormDeclarationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SignFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'in:en,id'],
            'signed_name' => ['required', 'string', 'max:255'],
            'agree' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('signed_name')) {
                    return;
                }

                // The page checks this too; enforced here so a stale page or a
                // hand-crafted request cannot sign under another name.
                $matches = app(FormDeclarationService::class)->nameMatches(
                    $this->user(),
                    (string) $this->input('signed_name')
                );

                if (! $matches) {
                    $validator->errors()->add(
                        'signed_name',
                        $this->input('locale') === 'id'
                            ? 'Nama lengkap tidak sesuai.'
                            : 'The full name does not match.'
                    );
                }
            },
        ];
    }
}
