<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Companies;
use App\Models\Employee;
use App\Models\FormDeclaration;
use App\Models\FormTemplate;
use App\Models\NonEmployee;
use App\Models\User;
use App\Support\EmployeeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who has and has not signed one form type for a year -- one row per person.
 * A declaration counts for the year stamped on it (period_year: the year it
 * was signed, or the year an import/upload names); templates have no period.
 * Shared by the admin report, its Excel export, the form dashboard and
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
    public function adminRows(?User $viewer, int $formTypeId, int $year, array $filters = []): Collection
    {
        $type = $filters['type'] ?? null;

        $narrow = function (Builder $query) use ($viewer, $year, $filters): Builder {
            return $query
                ->tap(fn (Builder $q) => $this->dataScope->applyToPeople($q, $viewer))
                ->tap(fn (Builder $q) => $this->joinedBy($q, $year))
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
            $year,
            $type === 'non_employee' ? null : $narrow(EmployeeStatus::apply(Employee::query(), $filters['employee_status'] ?? null)),
            $type === 'employee' ? null : $narrow(EmployeeStatus::apply(NonEmployee::query(), $filters['employee_status'] ?? null)),
        );

        return $this->filterStatus($rows, $filters['status'] ?? null);
    }

    /**
     * The admin report's rows: one year, or every year when $year is null,
     * and with $latest one row per person (latestPerPerson()) -- the
     * report's "Latest Submission" toggle.
     */
    public function adminReportRows(?User $viewer, int $formTypeId, ?int $year, array $filters, bool $latest): Collection
    {
        $rows = collect();

        foreach ($this->yearsFor($formTypeId, $year) as $one) {
            $rows = $rows->concat($this->adminRows($viewer, $formTypeId, $one, $filters));
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
     * Rows for several years at once (an "All Periods" view): buildRows()
     * per year, newest first.
     *
     * @param  iterable<int>  $years
     * @param  callable(int): array{0: ?Builder, 1: ?Builder}  $people  the employee / non-employee queries for a year
     */
    public function buildRowsAcross(int $formTypeId, iterable $years, callable $people): Collection
    {
        $rows = collect();

        foreach ($years as $year) {
            [$employees, $nonEmployees] = $people((int) $year);

            $rows = $rows->concat($this->buildRows($formTypeId, (int) $year, $employees, $nonEmployees));
        }

        return $rows->values();
    }

    /**
     * "Latest submission": one row per person -- their most recent signature
     * or draft, or, with neither, their newest year. Same idea as the COI
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
     * Only people who had joined by the end of the year count toward it --
     * the same rule as the COI report and dashboard. Unknown join dates stay.
     */
    public function joinedBy(Builder $query, int $year): Builder
    {
        return $query->where(
            fn (Builder $inner) => $inner
                ->whereNull('date_of_joining')
                ->orWhereDate('date_of_joining', '<=', "{$year}-12-31")
        );
    }

    public function buildRows(int $formTypeId, int $year, ?Builder $employees, ?Builder $nonEmployees): Collection
    {
        // What a person without a declaration owes: the template that applies
        // now. A past year is filled in by import or upload, not by a
        // template of its own.
        $available = FormTemplate::query()
            ->select(FormTemplate::SUMMARY_COLUMNS)
            ->available()
            ->where('form_type_id', $formTypeId)
            ->get();

        // Only what a row shows -- never the `variables` snapshot. A person
        // can hold several rows in a year: one per signature.
        $declarations = FormDeclaration::query()
            ->where('form_type_id', $formTypeId)
            ->where('period_year', $year)
            ->get(['id', 'type', 'user_id', 'status', 'template_id', 'period_year', 'signed_name', 'signed_at', 'agreed_at', 'locale', 'source'])
            ->groupBy(fn (FormDeclaration $declaration) => $declaration->type.'-'.$declaration->user_id);

        $titles = FormTemplate::withTrashed()
            ->whereKey($declarations->flatten()->pluck('template_id')->filter()->unique()->merge($available->modelKeys())->unique()->values())
            ->get(['id', 'title_en', 'title_id', 'is_active', 'deleted_at'])
            ->keyBy('id');

        // Same rule as FormDeclarationService::isSuperseded(): a row of this
        // year stops counting once its template is switched off and another
        // applies.
        $replaced = fn (FormDeclaration $declaration, ?FormTemplate $applicable): bool => FormDeclarationService::replacedBy(
            $declaration,
            $applicable,
            $titles->get($declaration->template_id)
        );

        $companyNames = Companies::query()
            ->whereNotNull('contribution_level_code')
            ->pluck('contribution_level', 'contribution_level_code');

        // Resolving a template per person repeats for every member of a unit,
        // so cache it per unit.
        $templateFor = [];

        $row = function (string $type, int $userId, object $person, ?string $workLocation) use (
            $year, $declarations, $available, $titles, $companyNames, $replaced, &$templateFor
        ): array {
            $unit = $person->group_company ?: null;

            // array_key_exists, not ??=: "no template" (null) is a valid answer
            // worth caching too.
            if (! array_key_exists((string) $unit, $templateFor)) {
                $templateFor[(string) $unit] = $this->templates->resolve($available, $unit);
            }

            $applicable = $templateFor[(string) $unit];

            $mine = $declarations->get($type.'-'.$userId) ?? collect();

            // The newest signature counts (people may sign again to update);
            // ones on a replaced template never do. Same rule as
            // FormDeclarationService::supersededIds().
            $valid = $mine
                ->reject(fn (FormDeclaration $declaration) => $replaced($declaration, $applicable))
                ->sortByDesc(fn (FormDeclaration $declaration) => [
                    $declaration->signed_at?->getTimestamp() ?? 0,
                    $declaration->id,
                ]);

            $declaration = $valid->firstWhere('status', FormDeclaration::STATUS_SIGNED)
                ?? $valid->firstWhere('status', FormDeclaration::STATUS_DRAFT);

            // Every signature this person holds for the year, newest first:
            // the valid one plus any on versions replaced since. Admins can
            // download each; the signer only sees the valid one as a PDF.
            $signedVersions = $mine
                ->filter(fn (FormDeclaration $row) => $row->isSigned())
                ->sortByDesc('signed_at')
                ->values();

            // An upload has no template: its row shows no template title.
            $template = $declaration
                ? $titles->get($declaration->template_id)
                : $applicable;

            return [
                'key' => $type.'-'.$userId.'-'.$year,
                'period' => $year,
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
                    $applicable !== null => self::STATUS_PENDING,
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
                // app / import / upload; an upload is a file, not a rendered form.
                'source' => $declaration?->isSigned() ? ($declaration->source ?: FormDeclaration::SOURCE_APP) : null,
                // Signed an older version that was replaced: owes a new one.
                'resign_required' => ! $declaration?->isSigned() && $signedVersions->isNotEmpty(),
                'signed_versions' => $signedVersions
                    ->map(fn (FormDeclaration $row) => [
                        'id' => $row->id,
                        'template_title' => ($title = $titles->get($row->template_id))
                            ? ['en' => $title->title_en, 'id' => $title->title_id]
                            : null,
                        'signed_at' => $row->signed_at?->toIso8601String(),
                        'source' => $row->source ?: FormDeclaration::SOURCE_APP,
                        // The one that counts now; the others were replaced.
                        'current' => $row->id === $declaration?->id,
                    ])
                    ->all(),
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
            // Any status: the report lists inactive people too (Employee
            // Status filter), and their signed documents must still open.
            ? $this->dataScope->applyToPeople(Employee::query(), $viewer)
                ->whereKey($declaration->user_id)
                ->exists()
            : $this->dataScope->applyToPeople(NonEmployee::query(), $viewer)
                ->where('user_id', $declaration->user_id)
                ->exists();
    }

    /**
     * Years worth offering in a report filter: from the first year anything
     * was declared for (signed, imported or uploaded) or a template was
     * published, up to this year.
     *
     * @return Collection<int, int>
     */
    public function years(?int $formTypeId = null): Collection
    {
        $current = (int) now()->year;

        $firstDeclared = FormDeclaration::query()
            ->when($formTypeId, fn (Builder $query) => $query->where('form_type_id', $formTypeId))
            ->min('period_year');

        $firstPublished = FormTemplate::query()
            ->when($formTypeId, fn (Builder $query) => $query->where('form_type_id', $formTypeId))
            ->min('publish_at');

        $first = min(
            $current,
            $firstDeclared ? (int) $firstDeclared : $current,
            $firstPublished ? (int) substr((string) $firstPublished, 0, 4) : $current,
        );

        return collect(range($current, $first))->values();
    }

    /**
     * The years a report covers: the one asked for, or every year when $year
     * is null -- newest first.
     *
     * @return Collection<int, int>
     */
    public function yearsFor(int $formTypeId, ?int $year): Collection
    {
        return $year !== null ? collect([$year]) : $this->years($formTypeId);
    }

    /** The year a report opens on: this one -- the year being signed. */
    public function defaultYear(?int $formTypeId = null): int
    {
        return (int) now()->year;
    }
}
