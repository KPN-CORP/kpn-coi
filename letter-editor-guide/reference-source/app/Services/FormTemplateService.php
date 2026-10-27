<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Form templates: which one applies to whom, and the "one active template per
 * business unit per period" rule. Every rule here holds *within one form type*
 * -- an Integrity Pact template never competes with another type's template.
 *
 * A template either names business units or names none, meaning "every unit".
 * Within a period, each unit may be covered by at most one active unit-specific
 * template, plus at most one active all-units template acting as the default.
 * A signer gets their unit's specific template when one exists, otherwise the
 * all-units default -- so a unit can be given its own wording without having to
 * switch the group-wide template off.
 */
class FormTemplateService
{
    public function __construct(
        private readonly DataScopeService $dataScope,
        private readonly FormHtmlSanitizer $sanitizer,
    ) {}

    /**
     * Every available template that applies to this unit -- one per form type
     * per period, specific beating all-units. Pass a form type id to narrow to
     * that type.
     *
     * @return Collection<int, FormTemplate>
     */
    public function applicableAcrossPeriods(?string $businessUnit, ?int $formTypeId = null): Collection
    {
        return FormTemplate::query()
            // Runs for the menu badge on every page: only what resolution and
            // the pending list need, never the longText bodies.
            ->select(FormTemplate::SUMMARY_COLUMNS)
            ->available()
            ->when($formTypeId, fn (Builder $query) => $query->where('form_type_id', $formTypeId))
            ->orderByDesc('period')
            ->get()
            ->groupBy(fn (FormTemplate $template) => $template->form_type_id.'-'.$template->period)
            ->map(fn (Collection $templates) => $this->resolve($templates, $businessUnit))
            ->filter()
            ->values();
    }

    /**
     * Pick from one form type's templates for one period without querying
     * again -- used by the report, which resolves thousands of people against
     * the same handful.
     *
     * @param  Collection<int, FormTemplate>  $templates
     */
    public function resolve(Collection $templates, ?string $businessUnit): ?FormTemplate
    {
        if (filled($businessUnit)) {
            $specific = $templates->first(
                fn (FormTemplate $template) => ! $template->appliesToAllUnits()
                    && $template->appliesTo($businessUnit)
            );

            if ($specific) {
                return $specific;
            }
        }

        return $templates->first(
            fn (FormTemplate $template) => $template->appliesToAllUnits()
        );
    }

    /**
     * Active templates the given settings would collide with. Empty means the
     * template may be saved (or switched on) as is.
     *
     * @param  list<string>  $businessUnits
     * @return Collection<int, FormTemplate>
     */
    public function conflicts(int $formTypeId, int $period, array $businessUnits, ?int $ignoreId = null): Collection
    {
        return FormTemplate::query()
            ->where('form_type_id', $formTypeId)
            ->where('period', $period)
            ->where('is_active', true)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->filter(function (FormTemplate $other) use ($businessUnits) {
                if ($businessUnits === []) {
                    return $other->appliesToAllUnits();
                }

                return array_intersect($businessUnits, $other->business_units ?? []) !== [];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data  validated request data
     */
    public function save(?FormTemplate $template, array $data, User $editor): FormTemplate
    {
        $template ??= new FormTemplate(['created_by' => $editor->id]);

        $template->fill([
            'form_type_id' => (int) $data['form_type_id'],
            'period' => (int) $data['period'],
            'title_en' => trim($data['title_en']),
            'title_id' => trim($data['title_id']),
            'content_en' => $this->sanitizer->clean($data['content_en']),
            'content_id' => $this->sanitizer->clean($data['content_id']),
            'header_en' => $this->sanitizer->clean((string) ($data['header_en'] ?? '')) ?: null,
            'header_id' => $this->sanitizer->clean((string) ($data['header_id'] ?? '')) ?: null,
            'footer_en' => $this->sanitizer->clean((string) ($data['footer_en'] ?? '')) ?: null,
            'footer_id' => $this->sanitizer->clean((string) ($data['footer_id'] ?? '')) ?: null,
            'paper_size' => $data['paper_size'],
            'orientation' => $data['orientation'],
            'margin_top' => (float) $data['margin_top'],
            'margin_right' => (float) $data['margin_right'],
            'margin_bottom' => (float) $data['margin_bottom'],
            'margin_left' => (float) $data['margin_left'],
            'font_family' => $data['font_family'],
            'font_size' => (float) $data['font_size'],
            'line_height' => (float) $data['line_height'],
            'business_units' => array_values(array_unique($data['business_units'] ?? [])) ?: null,
            'publish_at' => $data['publish_at'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'updated_by' => $editor->id,
        ])->save();

        return $template;
    }

    /**
     * True when the admin's role pins business units. Such an admin may only
     * manage templates confined to those units -- an all-units template would
     * reach people they are not allowed to see.
     */
    public function isUnitRestricted(?User $user): bool
    {
        return isset($this->dataScope->restrictionsFor($user)['group_company']);
    }

    /**
     * @return list<string>
     */
    public function allowedUnits(?User $user): array
    {
        return $this->dataScope->businessUnitOptions($user)->all();
    }

    public function canManage(FormTemplate $template, ?User $user): bool
    {
        if (! $this->isUnitRestricted($user)) {
            return true;
        }

        if ($template->appliesToAllUnits()) {
            return false;
        }

        return array_diff($template->business_units, $this->allowedUnits($user)) === [];
    }

    /**
     * Templates a unit-restricted admin can see: those touching their units,
     * plus the all-units defaults that also reach their people (read-only).
     */
    public function visibleTo(Builder $query, ?User $user): Builder
    {
        if (! $this->isUnitRestricted($user)) {
            return $query;
        }

        $units = $this->allowedUnits($user);

        return $query->where(function (Builder $scoped) use ($units) {
            $scoped->whereNull('business_units')
                ->orWhereJsonLength('business_units', 0);

            foreach ($units as $unit) {
                $scoped->orWhereJsonContains('business_units', $unit);
            }
        });
    }
}
