<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CoiDeclaration;
use App\Models\Employee;
use Illuminate\Http\Request;

class ManagerTeamHistoryService
{
    public function getData(Request $request): array
    {
        $period = (int) ($request->period ?? now()->year);

        $employees = Employee::query()
            ->with([
                'user',
                'user.declarations' => fn ($query) => $query
                    ->where('period', $period)
                    ->with('responses'),
            ])
            ->where(function ($query) use ($request) {
                $query->where(
                    'manager_l1_id',
                    $request->user()->employee_id
                )->orWhere(
                    'manager_l2_id',
                    $request->user()->employee_id
                );
            })
            ->whereNull('deleted_at')
            ->get();

        $records = $employees
            ->map(function ($employee) use ($period) {

                $declaration = $employee
                    ->user
                    ?->declarations
                    ?->first();

                $hasConflict = $declaration?->responses?->contains(
                    fn ($response) =>
                        data_get(
                            $response->response_value,
                            'answer'
                        ) === true
                ) ?? false;

                return [

                    'id' => $employee->id,

                    'period' => $declaration->period ?? $period,

                    'name' => $employee->fullname,

                    'employee_id' => $employee->employee_id,

                    'designation' => $employee->designation_name,

                    'business_unit' => $employee->group_company,

                    'ktp' => $employee->ktp,

                    'status' => $declaration
                        ? $declaration->status->value
                        : 'pending',

                    'has_conflict' => $hasConflict,

                    'submitted_at' => $declaration?->submitted_at,

                    'declaration' => $declaration,

                ];

            });

        // Filter Business Unit
        if ($request->filled('business_unit')) {

            $records = $records
                ->where(
                    'business_unit',
                    $request->business_unit
                );
        }

        // Filter Status
        if ($request->filled('status')) {

            $records = match ($request->status) {

                'pending' => $records->where(
                    'status',
                    'pending'
                ),

                'submitted' => $records->where(
                    'status',
                    'submitted'
                ),

                'reviewed' => $records->where(
                    'status',
                    'reviewed'
                ),

                'draft' => $records->where(
                    'status',
                    'draft'
                ),

                'conflict' => $records->where(
                    'has_conflict',
                    true
                ),

                default => $records,

            };
        }

        $records = $records->values();

        return [

            'declarations' => $records,

            'filters' => [

                'period' => $request->period,

                'status' => $request->status,

                'business_unit' => $request->business_unit,

            ],

            'periods' => CoiDeclaration::query()
                ->select('period')
                ->distinct()
                ->orderByDesc('period')
                ->pluck('period'),

            'businessUnitOptions' => $employees
                ->pluck('group_company')
                ->filter()
                ->unique()
                ->sort()
                ->values(),

        ];
    }
}