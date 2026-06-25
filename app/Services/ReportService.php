<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\NonEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportService
{
    public function getDeclarations(
        int $period,
        ?string $status,
        ?string $search,
        User $user
    ) {
        $employees = $this->employeeRecords(
            $period
        );

        $nonEmployees = $this->nonEmployeeRecords(
            $period
        );

        $records = $employees
            ->concat($nonEmployees);

        if ($search) {
            $records = $records->filter(
                fn ($item) =>
                    str_contains(
                        strtolower(
                            $item['name']
                        ),
                        strtolower(
                            $search
                        )
                    )
            );
        }

        if ($status === 'submitted') {
            $records = $records->where(
                'status',
                'submitted'
            );
        }

        if ($status === 'pending') {
            $records = $records->where(
                'status',
                'pending'
            );
        }

        if ($status === 'conflict') {
            $records = $records->where(
                'has_conflict',
                true
            );
        }

        return new LengthAwarePaginator(
            $records
                ->values()
                ->forPage(
                    request('page', 1),
                    20
                ),
            $records->count(),
            20
        );
    }
    private function employeeRecords(
        int $period
    ): Collection {
    
        return Employee::query()
            ->with([
                'user',
                'user.declarations' => fn ($query)
                    => $query
                        ->where(
                            'period',
                            $period
                        )
                        ->where('type', 'employee')
                        ->with('responses'),
            ])
            ->where('deleted_at', null)
            ->get()
            ->map(function ($employee) use ($period) {
    
                $declaration =
                    $employee
                        ->user
                        ?->declarations
                        ?->first();
    
                $hasConflict = $declaration?->responses?->contains(
                    fn ($response) => data_get($response->response_value, 'answer') === true
                ) ?? false;
    
                return [
                    'id' => $employee->id,

                    'type' => 'employee',

                    'period' => $declaration->period ?? $period,

                    'row_id' => "employee-{$employee->id}",

                    'name' => $employee->fullname,

                    'ktp' => $employee->ktp,

                    'employee_id' => $employee->employee_id,

                    'status' => $declaration
                        ? 'submitted'
                        : 'pending',

                    'has_conflict' => $hasConflict,

                    'submitted_at' => $declaration?->submitted_at,

                    'declaration' => $declaration
                    ? [
                        'id' => $declaration->id,

                        'type' => 'employee',

                        'period' => $declaration->period,

                        'employee' => [
                            'id' => $employee->id,
                            'name' => $employee?->fullname,
                            'employee_id' => $employee?->employee_id,
                        ],


                        'status' => $declaration->status,

                        'submitted_at' => $declaration->submitted_at,

                        'responses' => $declaration->responses,
                    ]
                    : null,
                ];
            });
    }
    
    private function nonEmployeeRecords(
        int $period
    ): Collection {
    
        return NonEmployee::query()
            ->with([
                'user',
                'user.declarations' => fn ($query)
                    => $query
                        ->where(
                            'period',
                            $period
                        )
                        ->where('type', 'non_employee')
                        ->with('responses'),
            ])
            ->get()
            ->map(function ($employee) use ($period) {
    
                $declaration =
                    $employee
                        ->user
                        ?->declarations
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
