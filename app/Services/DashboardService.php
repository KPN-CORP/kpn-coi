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
        $employeeQuery = Employee::query()
        ->whereNull('deleted_at')
        ->when(
            $request->filled('business_unit')
            && $request->type === 'employee',
            fn ($query) => $query->where(
                'group_company',
                $request->business_unit
            )
        );

        $nonEmployeeQuery = NonEmployee::query();

        $nonEmployeeQuery->when(
            $request->filled('business_unit')
            && $request->type === 'non_employee',
            fn ($query) => $query->where(
                'group_company',
                $request->business_unit
            )
        );

        $declarationQuery = CoiDeclaration::query();

        $this->dataScopeService->apply(
            $declarationQuery,
            Auth::user()
        );

        $declarationQuery
            ->when(
                $request->filled('period'),
                fn ($query) => $query->where(
                    'period',
                    (int) $request->period
                )
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

            $userIds = (clone $nonEmployeeQuery)
                ->pluck('id');

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

        $query = CoiDeclaration::query()
            ->with([
                'user',
                'responses',
            ]);

        $this->dataScopeService->apply(
            $query,
            Auth::user()
        );

        $query->when(
            $request->filled('period'),
            fn ($query) => $query->where(
                'period',
                (int) $request->period
            )
        );

        $query->when(
            $request->filled('status'),
            fn ($query) => $query->where(
                'status',
                $request->status
            )
        );

        $query->when(
            $request->filled('business_unit'),
            function ($query) use ($request) {

                $userIds = Employee::query()
                    ->where('group_company', $request->business_unit)
                    ->pluck('id');

                $query->whereIn('user_id', $userIds);
            }
        );

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
                'coiDeclaration' => fn ($query) => $query->when(
                    $request->filled('period'),
                    fn ($query) => $query->where(
                        'period',
                        (int) $request->period
                    )
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

            $businessUnitOptions = Employee::query()
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
                'period' => $request->period,
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
    ): array
    {
        $type = $request->type ?? 'employee';
        
        if ($type === 'employee') {

            $rows = (clone $employeeQuery)
                ->select('id', 'group_company')
                ->whereNotNull('group_company')
                ->with([
                    'coiDeclaration' => fn ($query) => $query->when(
                        $request->filled('period'),
                        fn ($query) => $query->where(
                            'period',
                            (int) $request->period
                        )
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
                        'label' => 'Pending',

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
                'Pending',
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