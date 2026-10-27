<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\FormTemplate;
use App\Services\FormTemplateService;
use App\Support\FormPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $service = app(FormTemplateService::class);

        return [
            'form_type_id' => [
                'required',
                'integer',
                Rule::exists('form_types', 'id')->whereNull('deleted_at'),
            ],
            // The month the form is for, as two columns.
            'period_year' => FormPeriod::yearRules(),
            'period_month' => FormPeriod::monthRules(),
            'title_en' => ['required', 'string', 'max:255'],
            'title_id' => ['required', 'string', 'max:255'],
            'content_en' => ['required', 'string', 'max:500000'],
            'content_id' => ['required', 'string', 'max:500000'],
            // A unit-restricted admin must name their units: "all units" would
            // reach people outside what their role lets them see.
            'business_units' => [
                $service->isUnitRestricted($this->user()) ? 'required' : 'nullable',
                'array',
            ],
            'business_units.*' => [
                'string',
                Rule::in($service->allowedUnits($this->user())),
            ],
            'publish_at' => ['required', 'date'],
            'is_active' => ['boolean'],
            'header_en' => ['nullable', 'string', 'max:100000'],
            'header_id' => ['nullable', 'string', 'max:100000'],
            'footer_en' => ['nullable', 'string', 'max:100000'],
            'footer_id' => ['nullable', 'string', 'max:100000'],
            'paper_size' => ['required', Rule::in(array_keys(FormTemplate::PAPER_SIZES))],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            // The header and footer live inside the top/bottom margins, so
            // those are allowed to be deeper than the side margins.
            'margin_top' => ['required', 'numeric', 'min:5', 'max:80'],
            'margin_bottom' => ['required', 'numeric', 'min:5', 'max:80'],
            'margin_left' => ['required', 'numeric', 'min:5', 'max:60'],
            'margin_right' => ['required', 'numeric', 'min:5', 'max:60'],
            'font_family' => ['required', Rule::in(array_keys(config('forms.fonts')))],
            'font_size' => ['required', 'numeric', Rule::in(config('forms.font_sizes'))],
            'line_height' => ['required', 'numeric', 'min:1', 'max:3'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title_en' => 'template name (English)',
            'title_id' => 'template name (Indonesian)',
            'content_en' => 'content (English)',
            'content_id' => 'content (Indonesian)',
            'form_type_id' => 'form type',
            'business_units' => 'business unit',
            'publish_at' => 'publish date',
            'header_en' => 'header (English)',
            'header_id' => 'header (Indonesian)',
            'footer_en' => 'footer (English)',
            'footer_id' => 'footer (Indonesian)',
            'paper_size' => 'paper size',
            'margin_top' => 'top margin',
            'margin_bottom' => 'bottom margin',
            'margin_left' => 'left margin',
            'margin_right' => 'right margin',
            'font_family' => 'font',
            'font_size' => 'font size',
            'line_height' => 'line spacing',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // The editor always leaves markup (an empty <p>) behind, so
                // "required" alone lets a blank document through.
                foreach (['content_en', 'content_id'] as $field) {
                    if (trim(strip_tags((string) $this->input($field))) === '') {
                        $validator->errors()->add($field, __('validation.required', [
                            'attribute' => $this->attributes()[$field],
                        ]));
                    }
                }

                if (! $this->boolean('is_active')) {
                    return;
                }

                $template = $this->route('template');

                $conflicts = app(FormTemplateService::class)->conflicts(
                    (int) $this->input('form_type_id'),
                    FormPeriod::make((int) $this->input('period_year'), (int) $this->input('period_month')),
                    array_values(array_unique($this->input('business_units') ?? [])),
                    $template instanceof FormTemplate ? $template->id : null,
                );

                if ($conflicts->isNotEmpty()) {
                    $validator->errors()->add(
                        'business_units',
                        'Another active template already covers this business unit for the period: '
                            .$conflicts->pluck('title_en')->implode(', ')
                            .'. Deactivate it first or choose other business units.'
                    );
                }
            },
        ];
    }
}
