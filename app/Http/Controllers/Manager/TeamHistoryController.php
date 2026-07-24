<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Exports\ManagerTeamHistoryExport;
use App\Http\Controllers\Controller;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Services\ManagerTeamHistoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class TeamHistoryController extends Controller
{
    public function __construct(
        private ManagerTeamHistoryService $teamHistoryService
    ) {}

    /**
     * The columns the table may be sorted by. Anything else is ignored rather
     * than passed through -- the sort runs on array keys, so an unknown one
     * would silently order by null.
     */
    private const SORTABLE = [
        'period',
        'name',
        'designation',
        'level',
        'business_unit',
        'status',
        'has_conflict',
    ];

    public function index(Request $request): Response
    {

        // Blank rather than absent when the period select is on "All Period",
        // and "" casts to 0 -- which matches no declaration at all and renders
        // the whole team as pending in period 0.
        $period = (int) ($request->filled('period')
            ? $request->period
            : now()->year);

        $perPage = (int) ($request->per_page ?? 10);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 10;
        }

        $sort = $request->string('sort')->toString();

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = null;
        }

        $direction = $request->string('direction')->toString() === 'desc'
            ? 'desc'
            : 'asc';

        // Whether the form was submitted at all. Whether the submission
        // declares a conflict is a separate axis, filtered further down.
        $status = in_array($request->status, ['submitted', 'pending'], true)
            ? $request->status
            : null;

        $declarationStatus = $request->string('declaration_status')->toString();

        if (! in_array($declarationStatus, ['clear', 'conflict'], true)) {
            $declarationStatus = null;
        }

        $latestSubmission = $request->boolean('latest_submission', true);

        $search = $request->string('search')->toString();

        $records = $this->teamQuery($request)
            ->with([
                'user',
                'user.declarations' => fn ($query) => $query
                    ->where('period', $period)
                    ->with('responses')
                    ->latest(),
            ])
            ->when(
                $request->filled('business_unit'),
                fn ($query) => $query->where(
                    'group_company',
                    $request->business_unit
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
            ->get()
            // One row per declaration, not per employee: submitting again
            // creates another row rather than replacing the last one, so a
            // reportee can hold several in a period. Collapsing them here
            // would hide all but an arbitrary one and leave the latest
            // submission toggle below with nothing to collapse.
            ->flatMap(function ($employee) use ($period) {

                $declarations = $employee->user?->declarations
                    ?? collect();

                if ($declarations->isEmpty()) {
                    return [
                        $this->row($employee, $period, null),
                    ];
                }

                return $declarations->map(
                    fn ($declaration) => $this->row(
                        $employee,
                        $period,
                        $declaration
                    )
                );
            });

        if ($status) {

            $records = $records
                ->where('status', $status)
                ->values();
        }

        if ($declarationStatus) {

            $records = match ($declarationStatus) {

                'conflict' => $records->where('has_conflict', true),

                // Mirrors what the table renders: a row with no submission
                // shows N/A, not Clear, and has_conflict is false for those
                // too -- so they must not land in this bucket.
                'clear' => $records->filter(
                    fn ($row) => $row['declaration'] !== null
                        && $row['has_conflict'] === false
                ),

                default => $records,
            };

            $records = $records->values();
        }

        if ($latestSubmission) {

            $records = $records
                ->sortByDesc(fn ($row) => $row['submitted_at'])
                ->groupBy(fn ($row) => $row['employee_id'])
                ->map(fn ($group) => $group->first())
                ->values();
        }

        if ($sort) {

            // Strings compare case-insensitively so "adi" does not sort after
            // "Zulfikar"; anything else (period, the booleans) sorts as is.
            $sortValue = fn ($row) => is_string($row[$sort] ?? null)
                ? mb_strtolower($row[$sort])
                : ($row[$sort] ?? null);

            $records = ($direction === 'desc'
                ? $records->sortByDesc($sortValue)
                : $records->sortBy($sortValue)
            )->values();
        }

        $page = (int) $request->input('page', 1);

        $paginated = new LengthAwarePaginator(
            // values() matters: forPage() keeps the original keys, so from page
            // two on the slice would serialize as a JSON object instead of an
            // array and the table would render empty.
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render(
            'Manager/TeamHistory',
            [
                'declarations' => $paginated,

                'filters' => [
                    'period' => $period,
                    'status' => $status,
                    'declaration_status' => $declarationStatus,
                    'business_unit' => $request->business_unit,
                    'search' => $search,
                    'latest_submission' => $latestSubmission,
                    'per_page' => $perPage,
                    'sort' => $sort,
                    'direction' => $direction,
                ],

                'periods' => CoiDeclaration::query()
                    ->select('period')
                    ->distinct()
                    ->orderByDesc('period')
                    ->pluck('period'),

                // Taken from the whole team rather than from the rows on
                // screen: derived from the filtered set, picking a unit would
                // leave it as the only option and there would be no way to
                // switch to another one.
                'businessUnitOptions' => $this->teamQuery($request)
                    ->whereNotNull('group_company')
                    ->distinct()
                    ->orderBy('group_company')
                    ->pluck('group_company'),
            ]
        );
    }

    /**
     * One table row: a reportee, plus the declaration it is being shown for.
     * A null declaration is someone who has not submitted for the period.
     */
    private function row(
        Employee $employee,
        int $period,
        ?CoiDeclaration $declaration
    ): array {
        $hasConflict = $declaration?->responses?->contains(
            fn ($response) => data_get(
                $response->response_value,
                'answer'
            ) === true
        ) ?? false;

        return [
            'id' => $declaration?->id ?? $employee->id,

            // Unique per row, not per employee -- several declarations in one
            // period would otherwise share a key and Vue would reuse the DOM
            // node of whichever it rendered first.
            'row_id' => $declaration
                ? "employee-{$employee->id}-{$declaration->id}"
                : "employee-{$employee->id}",

            'period' => $declaration->period ?? $period,

            'name' => $employee->fullname,

            'employee_id' => $employee->employee_id,

            'designation' => $employee->designation_name,
            'level' => $employee->job_level,
            'business_unit' => $employee->group_company,

            'ktp' => $employee->ktp,

            'status' => $declaration
                ? $declaration->status->value
                : 'pending',

            'has_conflict' => $hasConflict,

            'submitted_at' => $declaration?->submitted_at,

            'declaration' => $declaration
                ? [
                    'id' => $declaration->id,

                    'period' => $declaration->period,

                    'status' => $declaration->status,

                    'submitted_at' => $declaration->submitted_at,

                    'responses' => $declaration->responses,
                ]
                : null,
        ];
    }

    /**
     * Everyone reporting to the signed-in manager, on either level.
     */
    private function teamQuery(Request $request): Builder
    {
        return Employee::query()
            ->where(function ($query) use ($request) {
                $query->where(
                    'manager_l1_id',
                    $request->user()->employee_id
                )->orWhere(
                    'manager_l2_id',
                    $request->user()->employee_id
                );
            })
            ->whereNull('deleted_at');
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new ManagerTeamHistoryExport(
                $this->teamHistoryService->getData($request)
            ),
            'Team History.xlsx'
        );
    }

    private function hasConflict(
        CoiDeclaration $declaration
    ): bool {
        return $declaration->responses
            ->contains(
                fn ($response) => data_get(
                    $response->response_value,
                    'answer'
                ) === true
            );
    }
}
