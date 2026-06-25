<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeamHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $period = (int) ($request->period ?? now()->year);

        $records = Employee::query()
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
            ->get()
            ->map(function ($employee) use ($period) {

                $declaration = $employee
                    ->user
                    ?->declarations
                    ?->first();

                $hasConflict = $declaration?->responses?->contains(
                    fn ($response)
                        => data_get(
                            $response->response_value,
                            'answer'
                        ) === true
                ) ?? false;

                return [
                    'id' => $employee->id,

                    'row_id' => "employee-{$employee->id}",

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
            });

        return Inertia::render(
            'Manager/TeamHistory',
            [
                'declarations' => $records,

                'filters' => [
                    'period' => $period,
                ],

                'periods' => CoiDeclaration::query()
                    ->distinct()
                    ->orderByDesc('period')
                    ->pluck('period')
                    ->values(),
            ]
        );
    }

    private function hasConflict(
        CoiDeclaration $declaration
    ): bool
    {
        return $declaration->responses
            ->contains(
                fn ($response) =>
                    data_get(
                        $response->response_value,
                        'answer'
                    ) === true
            );
    }
}