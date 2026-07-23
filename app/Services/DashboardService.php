<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    public function __construct(
        protected DataScopeService $dataScopeService
    ) {
    }

    public function getDashboardData(Request $request): array
    {
        $type = $request->type ?? 'employee';
        $period = $request->period == null
            ? (string) now()->year
            : $request->period;

        $viewer = Auth::user();

        // Only people who had already joined by the end of the selected
        // period count toward that period's totals. Rows without a join
        // date are kept so they are never silently dropped.
        $joinedInPeriod = fn ($query) => $query->where(
            fn ($query) => $query
                ->whereNull('date_of_joining')
                ->orWhereYear('date_of_joining', '<=', (int) $period)
        );

        // The role's data restrictions gate every count and list below, so they
        // are applied to the people queries the stats and charts are derived
        // from -- not only to the declaration list.
        $employeeQuery = Employee::query()
        ->whereNull('deleted_at')
        ->tap($joinedInPeriod)
        ->tap(fn ($query) => $this->dataScopeService->applyToPeople($query, $viewer))
        ->when(
            $request->filled('business_unit')
            && $request->type === 'employee',
            fn ($query) => $query->where(
                'group_company',
                $request->business_unit
            )
        );

        $nonEmployeeQuery = NonEmployee::query()
            ->tap($joinedInPeriod)
            ->tap(fn ($query) => $this->dataScopeService->applyToPeople($query, $viewer));

        $nonEmployeeQuery->when(
            $request->filled('business_unit')
            && $request->type === 'non_employee',
            fn ($query) => $query->where(
                'group_company',
                $request->business_unit
            )
        );

        $declarationQuery = CoiDeclaration::query();

        $this->dataScopeService->applyToDeclarations(
            $declarationQuery,
            $viewer
        );

        $declarationQuery
            ->where(
                'period',
                (int) $period
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->status
                )
            )
            ->where(
                'type',
                $type
            );
        

        if ($request->type === 'non_employee') {

            // A non_employee declaration keys on the login id, not on the
            // profile row id -- non_employees.id and user_id differ.
            $userIds = (clone $nonEmployeeQuery)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            $declarationQuery->whereIn(
                'user_id',
                $userIds
            );
        } else {

            $userIds = (clone $employeeQuery)
                ->pluck('id');

            $declarationQuery->whereIn(
                'user_id',
                $userIds
            );
        }

        $totalDeclarations = (clone $declarationQuery)->distinct('user_id')
            ->count('user_id');


        $conflictDeclarations = (clone $declarationQuery)
            ->whereHas('responses', function ($query) {
                $query->whereJsonContains(
                    'response_value->answer',
                    true
                );
            })
            ->distinct('user_id')
            ->count('user_id');

        $declarations = (clone $declarationQuery)
            ->with([
                'user.employee',
                'nonEmployeeUser',
                'responses',
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $employeeDeclarations = $declarations
            ->getCollection()
            ->where('type', 'employee')
            ->values();

        $nonEmployeeDeclarations = $declarations
            ->getCollection()
            ->where('type', 'non_employee')
            ->values();

        $totalEmployees = match ($type) {

            'employee' => (clone $employeeQuery)->count(),

            'non_employee' => (clone $nonEmployeeQuery)->count(),

            default =>
                (clone $employeeQuery)->count()
                + (clone $nonEmployeeQuery)->count(),

        };

        $businessUnits = (clone $employeeQuery)
            ->select('id', 'group_company')
            ->whereNotNull('group_company')
            ->with([
                'coiDeclaration' => fn ($query) => $query->where(
                    'period',
                    (int) $period
                ),
            ])
            ->get()
            ->groupBy('group_company')
            ->map(function ($employees, $businessUnit) {

                $submitted = $employees
                    ->filter(fn ($employee) => $employee->coiDeclaration->isNotEmpty())
                    ->count();

                return [
                    'label' => $businessUnit,
                    'submitted' => $submitted,
                    'pending' => $employees->count() - $submitted,
                ];
            })
            ->values();

            // Restricted admins must not even be offered a unit they cannot
            // open, so the filter list is scoped like the data behind it.
            $businessUnitOptions = $this->dataScopeService
            ->applyToPeople(Employee::query(), $viewer)
            ->whereNotNull('group_company')
            ->distinct()
            ->orderBy('group_company')
            ->pluck('group_company');

            return [

            'stats' => [
                'total' => $totalEmployees,
                'pending' => $totalEmployees - $totalDeclarations,
                'submitted' => $totalDeclarations,
                'conflict' => $conflictDeclarations,
            ],

            'declarations' => TeamDeclarationResource::collection($declarations),

            'rawDeclarations' => $declarations->items(),

            'filters' => [
                'period' => $period,
                'status' => $request->status,
                'business_unit' => $request->business_unit,
                'type' => $request->type,
            ],

            'businessUnitOptions' => $businessUnitOptions,

            'barChart' => $this->getBarChart(
                $request,
                $employeeQuery,
                $totalEmployees,
                $totalDeclarations,
                (int) $period,
            ),
            'charts' => [
                'status' => [
                    'submitted' => $totalDeclarations,
                    'pending' => $totalEmployees - $totalDeclarations,
                ],
            ],

            'employeeDeclarations' => TeamDeclarationResource::collection(
                $employeeDeclarations
            ),

            'nonEmployeeDeclarations' => TeamDeclarationResource::collection(
                $nonEmployeeDeclarations
            ),

            'periods' => CoiDeclaration::query()
                ->distinct()
                ->orderByDesc('period')
                ->pluck('period')
                ->values(),
        ];
    }

    private function getBarChart(
        Request $request,
        Builder $employeeQuery,
        int $totalEmployees,
        int $totalDeclarations,
        int $period,
    ): array
    {
        $type = $request->type ?? 'employee';

        if ($type === 'employee') {

            $rows = (clone $employeeQuery)
                ->select('id', 'group_company')
                ->whereNotNull('group_company')
                ->with([
                    'coiDeclaration' => fn ($query) => $query->where(
                        'period',
                        $period
                    ),
                ])
                ->get()
                ->groupBy('group_company');

            return [

                'title' => 'Submission by Business Unit',

                'labels' => $rows
                    ->keys()
                    ->values(),

                'datasets' => [

                    [
                        'label' => 'Submitted',

                        'backgroundColor' => '#AB2F2B',

                        'borderRadius' => 4,

                        'data' => $rows
                            ->map(
                                fn ($employees) => $employees
                                    ->filter(
                                        fn ($employee) =>
                                            $employee
                                                ->coiDeclaration
                                                ->isNotEmpty()
                                    )
                                    ->count()
                            )
                            ->values(),
                    ],

                    [
                        'label' => 'Not Submitted',

                        'backgroundColor' => '#E2E8F0',

                        'borderRadius' => 4,

                        'data' => $rows
                            ->map(
                                fn ($employees) =>
                                    $employees->count()
                                    - $employees
                                        ->filter(
                                            fn ($employee) =>
                                                $employee
                                                    ->coiDeclaration
                                                    ->isNotEmpty()
                                        )
                                        ->count()
                            )
                            ->values(),
                    ],

                ],

            ];
        }

        return [

            'title' => 'Submission Status',

            'labels' => [
                'Submitted',
                'Not Submitted',
            ],

            'datasets' => [

                [

                    'label' => 'Declaration',

                    'backgroundColor' => [
                        '#AB2F2B',
                        '#E2E8F0',
                    ],

                    'borderRadius' => 6,

                    'data' => [
                        $totalDeclarations,
                        $totalEmployees - $totalDeclarations,
                    ],

                ],

            ],

        ];
    }
}