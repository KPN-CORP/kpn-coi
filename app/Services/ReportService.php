<?php

namespace App\Services;

use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Models\NonEmployee;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        protected DataScopeService $dataScopeService
    ) {}

    public function getDeclarations(
        int $period,
        ?string $status,
        ?string $search,
        ?string $type,
        ?string $businessUnit,
        ?User $user = null,
        bool $latestSubmission = false,
        int $perPage = 20,
        ?string $sort = null,
        string $direction = 'asc',
        ?string $declarationStatus = null
    ): LengthAwarePaginator {
        $records = $this->buildRecords(
            period: $period,
            status: $status,
            search: $search,
            type: $type,
            businessUnit: $businessUnit,
            latestSubmission: $latestSubmission,
            declarationStatus: $declarationStatus,
            user: $user,
        );

        if ($sort) {
            $sortValue = fn ($row) => is_string($row[$sort] ?? null)
                ? mb_strtolower($row[$sort])
                : ($row[$sort] ?? null);

            $records = ($direction === 'desc'
                ? $records->sortByDesc($sortValue)
                : $records->sortBy($sortValue)
            )->values();
        }

        $page = (int) request('page', 1);

        return new LengthAwarePaginator(
            $records
                ->forPage($page, $perPage)
                ->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Full, unpaginated set of report rows (used for exports).
     */
    public function getAllDeclarations(
        int $period,
        ?string $status,
        ?string $search,
        ?string $type,
        ?string $businessUnit,
        ?User $user = null,
        bool $latestSubmission = false,
        ?string $declarationStatus = null
    ): Collection {
        return $this->buildRecords(
            period: $period,
            status: $status,
            search: $search,
            type: $type,
            businessUnit: $businessUnit,
            latestSubmission: $latestSubmission,
            declarationStatus: $declarationStatus,
            user: $user,
        );
    }

    private function buildRecords(
        int $period,
        ?string $status,
        ?string $search,
        ?string $type,
        ?string $businessUnit,
        bool $latestSubmission,
        ?string $declarationStatus = null,
        ?User $user = null
    ): Collection {
        $employees = $type === 'non_employee'
            ? collect()
            : $this->employeeRecords(
                $period,
                $businessUnit,
                $search,
                $status,
                $declarationStatus,
                $user
            );

        $nonEmployees = $type === 'employee'
            ? collect()
            : $this->nonEmployeeRecords(
                $period,
                $businessUnit,
                $user
            );

        $records = $employees
            ->concat($nonEmployees);

        if ($search) {

            $keyword = strtolower($search);

            $records = $records->filter(

                fn ($item) => str_contains(
                    strtolower($item['name']),
                    $keyword
                )

                    ||

                    str_contains(
                        strtolower($item['employee_id']),
                        $keyword
                    )

            );

        }

        // Form status = whether the form was submitted at all. Whether the
        // submission declares a conflict is a separate axis, filtered below.
        if ($status) {

            $records = match ($status) {

                'submitted' => $records->where(
                    'status',
                    'submitted'
                ),

                'pending' => $records->where(
                    'status',
                    'pending'
                ),

                default => $records,

            };

        }

        if ($declarationStatus) {

            $records = match ($declarationStatus) {

                'conflict' => $records->where(
                    'has_conflict',
                    true
                ),

                // Mirrors what the table renders: a row with no submission
                // shows N/A, not Clear, so it must not land in this bucket --
                // has_conflict is false for those too.
                'clear' => $records->filter(
                    fn ($row) => $row['declaration'] !== null
                        && $row['has_conflict'] === false
                ),

                default => $records,

            };

        }

        if ($type) {
            $records = $records->where(
                'type',
                $type
            );
        }

        if ($latestSubmission) {
            $records = $records
                ->sortByDesc(
                    fn ($item) => $item['submitted_at']
                )
                ->groupBy(
                    fn ($item) => $item['type'].'-'.$item['employee_id']
                )
                ->map(
                    fn ($group) => $group->first()
                );
        }

        return $records->values();
    }

    private function employeeRecords(
        int $period,
        ?string $businessUnit,
        ?string $search = null,
        ?string $status = null,
        ?string $declarationStatus = null,
        ?User $user = null
    ): Collection {

        // Preload declarations for the period once (small dataset) so that
        // status/conflict can be filtered in SQL below — without this the
        // query would have to load every employee and filter in memory.
        $declarations = CoiDeclaration::query()
            ->where('period', $period)
            ->with('responses')
            ->get();

        $submittedUserIds = $declarations
            ->pluck('user_id')
            ->unique()
            ->values();

        $conflictUserIds = $declarations
            ->filter(
                fn ($declaration) => $declaration->responses->contains(
                    fn ($response) => data_get($response->response_value, 'answer') === true
                )
            )
            ->pluck('user_id')
            ->unique()
            ->values();

        return Employee::query()
            ->tap(fn ($query) => $this->dataScopeService->applyToPeople($query, $user))
            ->with([
                'coiDeclaration' => fn ($query) => $query
                    ->where('period', $period)
                    ->with('responses')
                    ->latest(),
            ])
            ->when(
                filled($businessUnit),
                fn ($query) => $query->where(
                    'group_company',
                    $businessUnit
                )
            )
            ->when(
                filled($search),
                fn ($query) => $query->where(
                    fn ($inner) => $inner
                        ->where('fullname', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                )
            )
            ->when(
                $status === 'submitted',
                fn ($query) => $query->whereIn('id', $submittedUserIds)
            )
            ->when(
                $status === 'pending',
                fn ($query) => $query->whereNotIn('id', $submittedUserIds)
            )
            ->when(
                $declarationStatus === 'conflict',
                fn ($query) => $query->whereIn('id', $conflictUserIds)
            )
            // Only narrow to employees who submitted something -- a superset of
            // "clear". Clear is a property of a declaration row, not of the
            // employee: one employee can hold both a conflicting and a clear
            // declaration in the same period, so excluding them here by
            // conflict would drop their clear row too. The exact filter runs
            // in memory in buildRecords().
            ->when(
                $declarationStatus === 'clear',
                fn ($query) => $query->whereIn('id', $submittedUserIds)
            )
            // Mirror the dashboard: only employees who had already joined by
            // the end of the period count toward it. Unknown join dates are
            // kept so they are never silently dropped.
            ->where(
                fn ($query) => $query
                    ->whereNull('date_of_joining')
                    ->orWhereYear('date_of_joining', '<=', $period)
            )
            ->whereNull('deleted_at')
            ->get()
            ->flatMap(function ($employee) use ($period) {

                if ($employee->coiDeclaration->isEmpty()) {
                    return [[
                        'id' => $employee->id,
                        'type' => 'employee',
                        'period' => $period,
                        'row_id' => "employee-{$employee->id}",
                        'name' => $employee->fullname,
                        'ktp' => $employee->ktp,
                        'employee_id' => $employee->employee_id,
                        'employee_status' => $employee->deleted_at === null ? 'Active' : 'Inactive',
                        'designation' => $employee->designation_name ?? '-',
                        'group_company' => $employee->group_company ?? '-',
                        'date_of_joining' => $employee->date_of_joining
                            ? Carbon::parse($employee->date_of_joining)->format('d-m-Y')
                            : '-',
                        'status' => 'pending',
                        'has_conflict' => false,
                        'has_attachment' => false,
                        'submitted_at' => null,
                        'declaration' => null,
                    ]];
                }

                return $employee->coiDeclaration->map(function ($declaration) use ($employee) {

                    $hasConflict = $declaration->responses->contains(
                        fn ($response) => data_get($response->response_value, 'answer') === true
                    );

                    // 2025 rows only: the uploaded supporting document, if any.
                    $hasAttachment = (bool) data_get(
                        $declaration->responses->firstWhere(
                            'question_key',
                            CoiDeclaration::LEGACY_CONFLICT_KEY
                        )?->response_value,
                        'attachment'
                    );

                    return [
                        'id' => $declaration->id,
                        'type' => 'employee',
                        'period' => $declaration->period,
                        'row_id' => "employee-{$employee->id}-{$declaration->id}",
                        'name' => $employee->fullname,
                        'ktp' => $employee->ktp,
                        'employee_id' => $employee->employee_id,
                        'employee_status' => $employee->deleted_at === null ? 'Active' : 'Inactive',
                        'designation' => $employee->designation_name ?? '-',
                        'group_company' => $employee->group_company ?? '-',
                        'date_of_joining' => $employee->date_of_joining
                            ? Carbon::parse($employee->date_of_joining)->format('d-m-Y')
                            : '-',
                        'status' => 'submitted',
                        'has_conflict' => $hasConflict,
                        'has_attachment' => $hasAttachment,
                        'submitted_at' => $declaration->submitted_at,
                        'declaration' => [
                            'id' => $declaration->id,
                            'type' => 'employee',
                            'period' => $declaration->period,
                            'employee' => [
                                'id' => $employee->id,
                                'name' => $employee->fullname,
                                'employee_id' => $employee->employee_id,
                            ],
                            'status' => $declaration->status,
                            'submitted_at' => $declaration->submitted_at,
                            'responses' => $declaration->responses,
                        ],
                    ];
                });
            });
    }

    private function nonEmployeeRecords(
        int $period,
        ?string $businessUnit = null,
        ?User $user = null
    ): Collection {

        return NonEmployee::query()
            ->tap(fn ($query) => $this->dataScopeService->applyToPeople($query, $user))
            // non_employees carries group_company too, so the business unit
            // filter has to run here as well -- narrowing only the employee
            // side would leave every non-employee row in the result.
            ->when(
                filled($businessUnit),
                fn ($query) => $query->where(
                    'group_company',
                    $businessUnit
                )
            )
            // Mirror the dashboard: only people who had already joined by the
            // end of the period count toward it. Unknown join dates are kept.
            ->where(
                fn ($query) => $query
                    ->whereNull('date_of_joining')
                    ->orWhereYear('date_of_joining', '<=', $period)
            )
            ->with([
                'coiDeclaration' => fn ($query) => $query
                    ->where('period', $period)
                    ->with('responses'),
            ])
            ->get()
            ->map(function ($employee) use ($period) {

                $declaration =
                    $employee
                        ->coiDeclaration
                        ?->first();

                $hasConflict = $declaration?->responses?->contains(
                    fn ($response) => data_get($response->response_value, 'answer') === true
                ) ?? false;

                return [
                    'id' => $employee->id,

                    'type' => 'non_employee',

                    'period' => $declaration->period ?? $period,

                    'row_id' => "non_employee-{$employee->id}",

                    'name' => $employee->fullname,

                    'employee_id' => $employee->ktp,

                    'status' => $declaration
                        ? 'submitted'
                        : 'pending',

                    'employee_status' => $employee->deleted_at === null ? 'Active' : 'Inactive',
                    'designation' => $employee->designation_name ?? '-',
                    'group_company' => $employee->group_company ?? '-',
                    'date_of_joining' => $employee->date_of_joining
                        ? Carbon::parse($employee->date_of_joining)->format('d-m-Y')
                        : '-',

                    'has_conflict' => $hasConflict,

                    'submitted_at' => $declaration?->submitted_at,

                    'declaration' => $declaration
                    ? [
                        'id' => $declaration->id,

                        'type' => 'non_employee',

                        'period' => $declaration->period,

                        'employee' => [
                            'id' => $employee->id,
                            'name' => $employee->fullname,
                            'employee_id' => $employee->ktp,
                        ],

                        'status' => $declaration->status,

                        'submitted_at' => $declaration->submitted_at,

                        'responses' => $declaration->responses,
                    ]
                    : null,
                ];
            });
    }
}
