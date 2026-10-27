<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveFormTypeRequest extends FormRequest
{
    /**
     * Icons offered in the form type editor (Font Awesome solid). Kept to a
     * fixed list so the stored class is always one the app ships.
     */
    public const ICONS = [
        'fa-solid fa-file-signature',
        'fa-solid fa-file-contract',
        'fa-solid fa-file-shield',
        'fa-solid fa-handshake',
        'fa-solid fa-scale-balanced',
        'fa-solid fa-shield-halved',
        'fa-solid fa-clipboard-check',
        'fa-solid fa-user-shield',
        'fa-solid fa-lock',
        'fa-solid fa-file-lines',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_id' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_id' => ['nullable', 'string', 'max:1000'],
            'icon' => ['required', 'in:'.implode(',', self::ICONS)],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_en' => 'name (English)',
            'name_id' => 'name (Indonesian)',
            'description_en' => 'description (English)',
            'description_id' => 'description (Indonesian)',
        ];
    }
}
