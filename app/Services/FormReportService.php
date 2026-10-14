<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Companies;
use App\Models\Employee;
use App\Models\FormDeclaration;
use App\Models\FormTemplate;
use App\Models\NonEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who has and has not signed one form type for a period -- one row per
 * person. Shared by the admin report, its Excel export, the form dashboard and
 * the manager's team view; each caller decides which people are in scope by
 * handing over its own people queries.
 *
 * Signatures live in the app database and people in kpncorp (employees) or
 * locally (non_employees), so they are matched in memory on (type, user_id),
 * never joined. Each type is keyed on its own id: employee rows on
 * employees.id (= kpncorp users.id), non-employee rows on non_employees.user_id.
 */
class FormReportService
{
    public const STATUS_SIGNED = 'signed';

    public const STATUS_PENDING = 'pending';

    /** Read and agreed to, saved as a draft -- not signed yet. */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_NO_TEMPLATE = 'no_template';

    public function __construct(
        private readonly DataScopeService $dataScope,
        private readonly FormTemplateService $templates,
    ) {}

    /**
     * Admin rows, narrowed by the viewer's role restrictions and the filters.
     *
     * @param  array{type?: ?string, business_unit?: ?string, contribution_level?: ?string, search?: ?string, status?: ?string}  $filters
     */
    public function adminRows(?User $viewer, int $formTypeId, int $period, array $filters = []): Collection
    {
        $type = $filters['type'] ?? null;

        $narrow = function (Builder $query) use ($viewer, $period, $filters): Builder {
            return $query
                ->tap(fn (Builder $q) => $this->dataScope->applyToPeople($q, $viewer))
                ->tap(fn (Builder $q) => $this->joinedBy($q, $period))
                ->when(
                    filled($filters['business_unit'] ?? null),
                    fn (Builder $q) => $q->where('group_company', $filters['business_unit'])
                )
                ->when(
                    filled($filters['contribution_level'] ?? null),
                    fn (Builder $q) => $q->where('contribution_level_code', $filters['contribution_level'])
                )
                ->when(
                    filled($filters['search'] ?? null),
                    fn (Builder $q) => $q->where(
                        fn (Builder $inner) => $inner
                            ->where('fullname', 'like', '%'.$filters['search'].'%')
                            ->orWhere('employee_id', 'like', '%'.$filters['search'].'%')
                    )
                );
        };

        $rows = $this->buildRows(
            $formTypeId,
            $period,
            $type === 'non_employee' ? null : $narrow(Employee::query()->whereNull('deleted_at')),
            $type === 'employee' ? null : $narrow(NonEmployee::query()),
        );

        return $this->filterStatus($rows, $filters['status'] ?? null);
    }

    /**
     * Kept separate so a caller can take its stats from the unfiltered rows --
     * otherwise filtering to "signed" would report a 100% signing rate.
     */
    public function filterStatus(Collection $rows, ?string $status): Collection
    {
        return in_array($status, [self::STATUS_SIGNED, self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_NO_TEMPLATE], true)
            ? $rows->where('status', $status)->values()
            : $rows;
    }

    /**
     * Only people who had joined by the end of the period count toward it --
     * the same rule as the COI report and dashboard. Unknown join dates stay.
     */
    public function joinedBy(Builder $query, int $period): Builder
    {
        return $query->where(
            fn (Builder $inner) => $inner
                ->whereNull('date_of_joining')
                ->orWhereYear('date_of_joining', '<=', $period)
        );
    }

    public function buildRows(int $formTypeId, int $period, ?Builder $employees, ?Builder $nonEmployees): Collection
    {
        $available = FormTemplate::query()
            ->available()
            ->where('form_type_id', $formTypeId)
            ->where('period', $period)
            ->get();

        $titles = FormTemplate::withTrashed()
            ->where('form_type_id', $formTypeId)
            ->where('period', $period)
            ->get(['id', 'title_en', 'title_id'])
            ->keyBy('id');

        $declarations = FormDeclaration::query()
            ->where('form_type_id', $formTypeId)
            ->where('period', $period)
            ->get()
            ->keyBy(fn (FormDeclaration $declaration) => $declaration->type.'-'.$declaration->user_id);

        $companyNames = Companies::query()
            ->whereNotNull('contribution_level_code')
            ->pluck('contribution_level', 'contribution_level_code');

        // Resolving a template per person repeats for every member of a unit,
        // so cache it per unit.
        $templateFor = [];

        $row = function (string $type, int $userId, object $person, ?string $workLocation) use (
            $declarations, $available, $titles, $companyNames, &$templateFor
        ): array {
            $unit = $person->group_company ?: null;

            $declaration = $declarations->get($type.'-'.$userId);

            // array_key_exists, not ??=: "no template" (null) is a valid answer
            // worth caching too.
            if (! array_key_exists((string) $unit, $templateFor)) {
                $templateFor[(string) $unit] = $this->templates->resolve($available, $unit);
            }

            $template = $declaration
                ? $titles->get($declaration->template_id)
                : $templateFor[(string) $unit];

            return [
                'key' => $type.'-'.$userId,
                'type' => $type,
                'user_id' => $userId,
                'name' => $person->fullname,
                'employee_id' => $person->employee_id ?: '',
                'business_unit' => $unit,
                'contribution_level' => $companyNames[$person->contribution_level_code] ?? null,
                'designation' => $person->designation_name ?? null,
                'work_location' => $workLocation,
                'status' => match (true) {
                    $declaration?->isSigned() === true => self::STATUS_SIGNED,
                    $declaration !== null => self::STATUS_DRAFT,
                    $template !== null => self::STATUS_PENDING,
                    default => self::STATUS_NO_TEMPLATE,
                },
                'template_id' => $template?->id,
                'template_title' => $template
                    ? ['en' => $template->title_en, 'id' => $template->title_id]
                    : null,
                // Only a signature has a document to open.
                'declaration_id' => $declaration?->isSigned() ? $declaration->id : null,
                'signed_name' => $declaration?->signed_name,
                'signed_at' => $declaration?->signed_at?->toIso8601String(),
                'agreed_at' => $declaration?->agreed_at?->toIso8601String(),
                'signed_locale' => $declaration?->locale,
            ];
        };

        $rows = collect();

        if ($employees) {
            $rows = $rows->concat(
                $employees
                    ->get(['id', 'employee_id', 'fullname', 'group_company', 'contribution_level_code', 'designation_name', 'office_area'])
                    ->map(fn (Employee $employee) => $row(
                        FormDeclaration::TYPE_EMPLOYEE,
                        (int) $employee->id,
                        $employee,
                        $employee->office_area ?: null
                    ))
            );
        }

        if ($nonEmployees) {
            $rows = $rows->concat(
                $nonEmployees
                    // A profile never linked to a login cannot sign.
                    ->whereNotNull('user_id')
                    ->with('location')
                    ->get()
                    ->map(fn (NonEmployee $person) => $row(
                        FormDeclaration::TYPE_NON_EMPLOYEE,
                        (int) $person->user_id,
                        $person,
                        $person->office_area ?: ($person->location?->area ?: null)
                    ))
            );
        }

        return $rows
            ->sortBy(fn (array $item) => mb_strtolower((string) $item['name']))
            ->values();
    }

    /**
     * Totals over a set of rows. "No template" people are excluded from the
     * signing rate -- there is nothing for them to sign.
     *
     * @return array{total: int, signed: int, pending: int, draft: int, no_template: int, rate: float}
     */
    public function stats(Collection $rows): array
    {
        $signed = $rows->where('status', self::STATUS_SIGNED)->count();
        $draft = $rows->where('status', self::STATUS_DRAFT)->count();
        // "Not signed" includes drafts: a draft is a promise, not a signature.
        $pending = $rows->where('status', self::STATUS_PENDING)->count() + $draft;

        return [
            'total' => $rows->count(),
            'signed' => $signed,
            'pending' => $pending,
            'draft' => $draft,
            'no_template' => $rows->where('status', self::STATUS_NO_TEMPLATE)->count(),
            'rate' => ($signed + $pending) > 0
                ? round($signed / ($signed + $pending) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * May this admin open the signed document of this person? Same rule as the
     * report rows: the person must fall inside the admin's role restrictions.
     */
    public function canView(?User $viewer, FormDeclaration $declaration): bool
    {
        if (! $this->dataScope->isRestricted($viewer)) {
            return true;
        }

        $allowed = $declaration->type === FormDeclaration::TYPE_EMPLOYEE
            ? $this->dataScope->allowedEmployeeIds($viewer)
            : $this->dataScope->allowedNonEmployeeUserIds($viewer);

        return $allowed->map(fn ($id) => (int) $id)->contains((int) $declaration->user_id);
    }

    /**
     * Periods worth offering in a filter: every template period of the form
     * type plus this year.
     *
     * @return Collection<int, int>
     */
    public function periods(?int $formTypeId = null): Collection
    {
        return FormTemplate::query()
            ->when($formTypeId, fn (Builder $query) => $query->where('form_type_id', $formTypeId))
            ->distinct()
            ->pluck('period')
            ->map(fn ($period) => (int) $period)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values();
    }
}
