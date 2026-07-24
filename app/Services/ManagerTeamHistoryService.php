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
        // "" (the "All Period" option) casts to 0 and matches no declaration,
        // so the export would come back with every row pending.
        $period = (int) ($request->filled('period')
            ? $request->period
            : now()->year);

        $search = $request->string('search')->toString();

        $employees = Employee::query()
            ->with([
                'user',
                'user.declarations' => fn ($query) => $query
                    ->where('period', $period)
                    ->with('responses')
                    ->latest(),
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
            ->when(
                filled($search),
                fn ($query) => $query->where(
                    fn ($inner) => $inner
                        ->where('fullname', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                )
            )
            ->get();

        // One row per declaration, as on screen: a reportee who submitted more
        // than once in a period holds several, and the latest submission
        // toggle below is what collapses them.
        $records = $employees
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

        // Filter Business Unit
        if ($request->filled('business_unit')) {

            $records = $records
                ->where(
                    'business_unit',
                    $request->business_unit
                );
        }

        // Form status: whether anything was submitted at all.
        if (in_array($request->status, ['submitted', 'pending'], true)) {

            $records = $records->where(
                'status',
                $request->status
            );
        }

        // Declaration status: whether the submission declares a conflict. A
        // row with no submission is neither -- it shows N/A on screen.
        if (in_array($request->declaration_status, ['clear', 'conflict'], true)) {

            $records = $request->declaration_status === 'conflict'
                ? $records->where('has_conflict', true)
                : $records->filter(
                    fn ($row) => $row['declaration'] !== null
                        && $row['has_conflict'] === false
                );
        }

        if ($request->boolean('latest_submission', true)) {

            $records = $records
                ->sortByDesc(fn ($row) => $row['submitted_at'])
                ->groupBy(fn ($row) => $row['employee_id'])
                ->map(fn ($group) => $group->first());
        }

        $records = $records->values();

        return [

            'declarations' => $records,

            'filters' => [

                'period' => $request->period,

                'status' => $request->status,

                'declaration_status' => $request->declaration_status,

                'search' => $search,

                'latest_submission' => $request->boolean('latest_submission', true),

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

    /**
     * One export row. `declaration` stays the model here -- the sheet reads
     * its responses relation directly.
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
    }
}
