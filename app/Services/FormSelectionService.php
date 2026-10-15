<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormType;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The "Form" filter of the admin dashboard and report: Conflict of Interest
 * or one form type. Each page passes the permissions that unlock COI and
 * form types for it.
 */
class FormSelectionService
{
    /** The filter value for the COI declaration; form types use their code. */
    public const COI = 'coi';

    /**
     * What the filter offers. Someone holding neither permission keeps COI,
     * as these pages offered before forms were merged in (their routes have
     * never been permission-gated themselves).
     *
     * @return list<array{value: string, label: array{en: string, id: string}}>
     */
    public function options(?User $user, string $coiPermission, string $formPermission): array
    {
        $options = [];

        $canForms = $user?->can($formPermission) ?? false;

        if (($user?->can($coiPermission) ?? false) || ! $canForms) {
            $options[] = ['value' => self::COI, 'label' => self::coiName()];
        }

        if ($canForms) {
            foreach (FormType::options() as $type) {
                $options[] = ['value' => $type['code'], 'label' => $type['name']];
            }
        }

        return $options;
    }

    /**
     * The requested form if it is on offer, otherwise the first option.
     * Null means COI.
     *
     * @param  list<array{value: string, label: array{en: string, id: string}}>  $options
     */
    public function selected(Request $request, array $options): ?FormType
    {
        $form = collect($options)->contains('value', $request->input('form'))
            ? $request->input('form')
            : ($options[0]['value'] ?? self::COI);

        return $form === self::COI ? null : FormType::query()->where('code', $form)->first();
    }

    /**
     * Props both pages share about the selected form.
     *
     * @param  list<array{value: string, label: array{en: string, id: string}}>  $options
     */
    public function props(?FormType $formType, array $options): array
    {
        return [
            'form' => $formType?->code ?? self::COI,
            'formKind' => $formType ? 'form' : 'coi',
            'formTypeId' => $formType?->id,
            'formName' => $formType ? ['en' => $formType->name_en, 'id' => $formType->name_id] : self::coiName(),
            'formOptions' => $options,
        ];
    }

    /**
     * @return array{en: string, id: string}
     */
    public static function coiName(): array
    {
        return ['en' => 'Conflict of Interest', 'id' => 'Konflik Kepentingan'];
    }
}
