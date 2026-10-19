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
    // Row statuses. Signed/draft are the declaration's own status; the other
    // two only exist on report rows (no declaration yet / nothing to sign).
    public const STATUS_SIGNED = FormDeclaration::STATUS_SIGNED;

    /** Read and agreed to, saved as a draft -- not signed yet. */
    public const STATUS_DRAFT = FormDeclaration::STATUS_DRAFT;

    public const STATUS_PENDING = 'pending';

    public const STATUS_NO_TEMPLATE = 'no_template';

    /** Filter value only: not started + draft, i.e. still owes a signature. */
    public const FILTER_NOT_SIGNED = 'not_signed';

    /** Status sorts by progress, not alphabetically. */
    private const STATUS_ORDER = [
        self::STATUS_PENDING => 0,
        self::STATUS_DRAFT => 1,
        self::STATUS_SIGNED => 2,
        self::STATUS_NO_TEMPLATE => 3,
    ];

    public const SORTABLE = ['period', 'name', 'employee_id', 'type', 'level', 'business_unit', 'status', 'signed_at'];

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
     * The admin report's rows: one period, or every period when $period is
     * null, and with $latest one row per person (latestPerPerson()) -- the
     * report's "Latest Submission" toggle.
     */
    public function adminReportRows(?User $viewer, int $formTypeId, ?int $period, array $filters, bool $latest): Collection
    {
        $rows = collect();

        foreach ($period === null ? $this->periods($formTypeId) : [$period] as $each) {
            $rows = $rows->concat($this->adminRows($viewer, $formTypeId, (int) $each, $filters));
        }

        return $latest ? $this->latestPerPerson($rows) : $rows->values();
    }

    /**
     * Kept separate so a caller can take its stats from the unfiltered rows --
     * otherwise filtering to "signed" would report a 100% signing rate.
     */
    public function filterStatus(Collection $rows, ?string $status): Collection
    {
        if ($status === self::FILTER_NOT_SIGNED) {
            return $rows->whereIn('status', [self::STATUS_PENDING, self::STATUS_DRAFT])->values();
        }

        return array_key_exists((string) $status, self::STATUS_ORDER)
            ? $rows->where('status', $status)->values()
            : $rows;
    }

    /**
     * A status filter value the screens accept, or null for "all".
     */
    public static function statusFilter(?string $status): ?string
    {
        return $status === self::FILTER_NOT_SIGNED || array_key_exists((string) $status, self::STATUS_ORDER)
            ? $status
            : null;
    }

    /**
     * Sort report rows. One implementation for the admin report and the
     * manager's team view, so the same column sorts the same way in both.
     */
    public function sort(Collection $rows, ?string $sort, string $direction = 'asc'): Collection
    {
        if (! in_array($sort, self::SORTABLE, true)) {
            return $rows;
        }

        $value = fn (array $row) => match ($sort) {
            'status' => self::STATUS_ORDER[$row['status']] ?? 9,
            'signed_at' => (string) ($row['signed_at'] ?? ''),
            default => mb_strtolower((string) ($row[$sort] ?? '')),
        };

        return ($direction === 'desc' ? $rows->sortByDesc($value) : $rows->sortBy($value))->values();
    }

    /**
     * Rows for several periods at once (an "All Periods" view): buildRows()
     * per period, newest first.
     *
     * @param  iterable<int>  $periods
     * @param  callable(int): array{0: ?Builder, 1: ?Builder}  $people  the employee / non-employee queries for a period
     */
    public function buildRowsAcross(int $formTypeId, iterable $periods, callable $people): Collection
    {
        $rows = collect();

        foreach ($periods as $period) {
            [$employees, $nonEmployees] = $people((int) $period);

            $rows = $rows->concat($this->buildRows($formTypeId, (int) $period, $employees, $nonEmployees));
        }

        return $rows->values();
    }

    /**
     * "Latest submission": one row per person -- their most recent signature
     * or draft, or, with neither, their newest period. Same idea as the COI
     * Team History toggle, which keeps each person's newest submission.
     */
    public function latestPerPerson(Collection $rows): Collection
    {
        return $rows
            ->sortByDesc(fn (array $row) => [
                (string) ($row['signed_at'] ?? $row['agreed_at'] ?? ''),
                $row['period'],
            ])
            ->unique(fn (array $row) => $row['type'].'-'.$row['user_id'])
            ->values();
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
            ->select(FormTemplate::SUMMARY_COLUMNS)
            ->available()
            ->where('form_type_id', $formTypeId)
            ->where('period', $period)
            ->get();

        $titles = FormTemplate::withTrashed()
            ->where('form_type_id', $formTypeId)
            ->where('period', $period)
            ->get(['id', 'title_en', 'title_id', 'is_active', 'deleted_at'])
            ->keyBy('id');

        // Only what a row shows -- never the `variables` snapshot. A person
        // can hold several rows in a period: one per template version.
        $declarations = FormDeclaration::query()
            ->where('form_type_id', $formTypeId)
            ->where('period', $period)
            ->get(['id', 'type', 'user_id', 'status', 'template_id', 'signed_name', 'signed_at', 'agreed_at', 'locale'])
            ->groupBy(fn (FormDeclaration $declaration) => $declaration->type.'-'.$declaration->user_id);

        // Same rule as FormDeclarationService::isSuperseded(): a row stops
        // counting once its template is switched off and another applies.
        $replaced = function (FormDeclaration $declaration, ?FormTemplate $applicable) use ($titles): bool {
            $template = $titles->get($declaration->template_id);

            $stillOn = $template && $template->is_active && ! $template->trashed();

            return $applicable !== null && $applicable->id !== $declaration->template_id && ! $stillOn;
        };

        $companyNames = Companies::query()
            ->whereNotNull('contribution_level_code')
            ->pluck('contribution_level', 'contribution_level_code');

        // Resolving a template per person repeats for every member of a unit,
        // so cache it per unit.
        $templateFor = [];

        $row = function (string $type, int $userId, object $person, ?string $workLocation) use (
            $period, $declarations, $available, $titles, $companyNames, $replaced, &$templateFor
        ): array {
            $unit = $person->group_company ?: null;

            // array_key_exists, not ??=: "no template" (null) is a valid answer
            // worth caching too.
            if (! array_key_exists((string) $unit, $templateFor)) {
                $templateFor[(string) $unit] = $this->templates->resolve($available, $unit);
            }

            $applicable = $templateFor[(string) $unit];

            $mine = $declarations->get($type.'-'.$userId) ?? collect();

            // Rows on the template that applies now come first.
            $valid = $mine
                ->reject(fn (FormDeclaration $declaration) => $replaced($declaration, $applicable))
                ->sortByDesc(fn (FormDeclaration $declaration) => $declaration->template_id === $applicable?->id);

            $declaration = $valid->firstWhere('status', FormDeclaration::STATUS_SIGNED)
                ?? $valid->firstWhere('status', FormDeclaration::STATUS_DRAFT);

            // Signed an older version that has since been replaced: they owe a
            // new signature, and the old one is kept for the record.
            $supersededSignature = $declaration?->isSigned()
                ? null
                : $mine
                    ->filter(fn (FormDeclaration $old) => $old->isSigned() && $replaced($old, $applicable))
                    ->sortByDesc('signed_at')
                    ->first();

            $template = $declaration
                ? $titles->get($declaration->template_id)
                : $applicable;

            return [
                'key' => $type.'-'.$userId.'-'.$period,
                'period' => $period,
                'type' => $type,
                'user_id' => $userId,
                'name' => $person->fullname,
                'employee_id' => $person->employee_id ?: '',
                'business_unit' => $unit,
                'contribution_level' => $companyNames[$person->contribution_level_code] ?? null,
                'designation' => $person->designation_name ?? null,
                // HRIS job level; non-employee profiles have none.
                'level' => $person->job_level ?? null,
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
                'resign_required' => $supersededSignature !== null,
                // The replaced signature's document, still downloadable.
                'superseded_declaration_id' => $supersededSignature?->id,
            ];
        };

        $rows = collect();

        if ($employees) {
            $rows = $rows->concat(
                $employees
                    ->get(['id', 'employee_id', 'fullname', 'group_company', 'contribution_level_code', 'designation_name', 'job_level', 'office_area'])
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
     * @return array{total: int, signed: int, draft: int, not_started: int, not_signed: int, no_template: int, rate: float}
     */
    public function stats(Collection $rows): array
    {
        $signed = $rows->where('status', self::STATUS_SIGNED)->count();
        $draft = $rows->where('status', self::STATUS_DRAFT)->count();
        $notStarted = $rows->where('status', self::STATUS_PENDING)->count();
        // "Not signed" = still owes a signature, drafts included: a draft is
        // a promise, not a signature. Same grouping as FILTER_NOT_SIGNED.
        $notSigned = $notStarted + $draft;

        return [
            'total' => $rows->count(),
            'signed' => $signed,
            'draft' => $draft,
            'not_started' => $notStarted,
            'not_signed' => $notSigned,
            'no_template' => $rows->where('status', self::STATUS_NO_TEMPLATE)->count(),
            // People with nothing to sign are left out of the rate.
            'rate' => ($signed + $notSigned) > 0
                ? round($signed / ($signed + $notSigned) * 100, 1)
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

        // One person, one exists() -- not every allowed id in the company.
        return $declaration->type === FormDeclaration::TYPE_EMPLOYEE
            ? $this->dataScope->applyToPeople(Employee::query()->whereNull('deleted_at'), $viewer)
                ->whereKey($declaration->user_id)
                ->exists()
            : $this->dataScope->applyToPeople(NonEmployee::query(), $viewer)
                ->where('user_id', $declaration->user_id)
                ->exists();
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
