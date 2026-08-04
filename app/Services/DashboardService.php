<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeclarationStatus;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Models\NonEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    public function __construct(
        protected DataScopeService $dataScopeService
    ) {}

    public function getDashboardData(Request $request): array
    {
        // null internally = "All Types". The UI sends the literal 'all' for it
        // (NOT an empty string -- Laravel's ConvertEmptyStringsToNull middleware
        // would turn '' into null, which is indistinguishable from the absent
        // first-load case that must default to employee).
        $type = match ($request->input('type')) {
            'employee', 'non_employee' => $request->input('type'),
            'all' => null,
            default => 'employee',
        };

        $period = $request->period == null
            ? (string) now()->year
            : $request->period;

        $viewer = Auth::user();

        $businessUnit = $request->business_unit;

        // Only people who had already joined by the end of the selected period
        // count toward that period's totals. Rows without a join date are kept
        // so they are never silently dropped.
        $joinedInPeriod = fn ($query) => $query->where(
            fn ($query) => $query
                ->whereNull('date_of_joining')
                ->orWhereYear('date_of_joining', '<=', (int) $period)
        );

        // Role data restrictions gate every count and the chart, so they are
        // applied to the people queries these derive from. The business unit
        // filter now applies to both people tables regardless of the type.
        $employeeQuery = Employee::query()
            ->whereNull('deleted_at')
            ->tap($joinedInPeriod)
            ->tap(fn ($query) => $this->dataScopeService->applyToPeople($query, $viewer))
            ->when(
                filled($businessUnit),
                fn ($query) => $query->where('group_company', $businessUnit)
            );

        $nonEmployeeQuery = NonEmployee::query()
            ->tap($joinedInPeriod)
            ->tap(fn ($query) => $this->dataScopeService->applyToPeople($query, $viewer))
            ->when(
                filled($businessUnit),
                fn ($query) => $query->where('group_company', $businessUnit)
            );

        // One row per person with their submission facts, for the selected
        // type(s). "All Types" concatenates both, so every count and the
        // per-business-unit chart below stay correct in all three modes.
        $people = collect();

        if ($type !== 'non_employee') {
            $people = $people->concat(
                $this->peopleSubmission((clone $employeeQuery), (int) $period, 'employee')
            );
        }

        if ($type !== 'employee') {
            $people = $people->concat(
                $this->peopleSubmission((clone $nonEmployeeQuery), (int) $period, 'non_employee')
            );
        }

        $total = $people->count();
        $submitted = $people->where('submitted', true)->count();
        $conflict = $people->where('conflict', true)->count();
        $pending = $total - $submitted;

        // Paginated declaration list for the selected type(s). Scoping is
        // already enforced by constraining to the ids resolved from the scoped
        // people queries above.
        $employeeIds = (clone $employeeQuery)->pluck('id');

        $nonEmployeeUserIds = (clone $nonEmployeeQuery)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $declarations = CoiDeclaration::query()
            ->where('period', (int) $period)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->status)
            )
            ->where(function ($query) use ($type, $employeeIds, $nonEmployeeUserIds) {

                // user_id points at a different database per type, so each type
                // is matched against its own id list -- never a shared one.
                $employee = fn ($inner) => $inner
                    ->where('type', 'employee')
                    ->whereIn('user_id', $employeeIds);

                $nonEmployee = fn ($inner) => $inner
                    ->where('type', 'non_employee')
                    ->whereIn('user_id', $nonEmployeeUserIds);

                match ($type) {
                    'employee' => $query->where($employee),
                    'non_employee' => $query->where($nonEmployee),
                    default => $query->where($employee)->orWhere($nonEmployee),
                };
            })
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

        return [

            'stats' => [
                'total' => $total,
                'pending' => $pending,
                'submitted' => $submitted,
                'conflict' => $conflict,
            ],

            'declarations' => TeamDeclarationResource::collection($declarations),

            'rawDeclarations' => $declarations->items(),

            'filters' => [
                'period' => $period,
                'status' => $request->status,
                'business_unit' => $businessUnit,
                // Echo the raw request value so an empty string keeps the
                // "All Types" option selected on the way back.
                'type' => $request->input('type'),
            ],

            'businessUnitOptions' => $this->dataScopeService
                ->businessUnitOptions($viewer),

            'barChart' => $this->getBarChart($people),

            'charts' => [
                'status' => [
                    'submitted' => $submitted,
                    'pending' => $pending,
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

    /**
     * One row per person (employee or non-employee) carrying whether they
     * submitted a declaration for the period and whether any of it declares a
     * conflict. Keyed to the correct type so the id-space overlap between the
     * two people tables never mixes a stranger's declaration in.
     */
    private function peopleSubmission(Builder $query, int $period, string $type): Collection
    {
        return $query
            ->with([
                // A draft is not a submission: it must fall under "not
                // submitted" alongside people with no declaration at all.
                // Excluding drafts here keeps both the submitted/pending
                // counts and the conflict count based only on real
                // submissions (submit deletes the draft, so a submitted
                // person keeps only the Submitted row anyway).
                'coiDeclaration' => fn ($relation) => $relation
                    ->where('period', $period)
                    ->where('type', $type)
                    ->where('status', '!=', DeclarationStatus::Draft)
                    ->with('responses'),
            ])
            ->get()
            ->map(function ($person) {

                $declarations = $person->coiDeclaration;

                return [
                    'group_company' => $person->group_company,
                    'submitted' => $declarations->isNotEmpty(),
                    'conflict' => $declarations->contains(
                        fn ($declaration) => $declaration->responses->contains(
                            fn ($response) => data_get($response->response_value, 'answer') === true
                        )
                    ),
                ];
            });
    }

    /**
     * Submitted / not-submitted per business unit across whatever people were
     * selected (employee, non-employee, or both). People with no group_company
     * are left out -- they have no bar to fall under.
     */
    private function getBarChart(Collection $people): array
    {
        $byBusinessUnit = $people
            ->filter(fn ($person) => filled($person['group_company']))
            ->groupBy('group_company')
            ->sortKeys();

        return [

            'title' => 'Submission by Business Unit',

            'labels' => $byBusinessUnit->keys()->values(),

            'datasets' => [

                [
                    'label' => 'Submitted',
                    'backgroundColor' => '#AB2F2B',
                    'borderRadius' => 4,
                    'data' => $byBusinessUnit
                        ->map(fn ($group) => $group->where('submitted', true)->count())
                        ->values(),
                ],

                [
                    'label' => 'Not Submitted',
                    'backgroundColor' => '#E2E8F0',
                    'borderRadius' => 4,
                    'data' => $byBusinessUnit
                        ->map(fn ($group) => $group->where('submitted', false)->count())
                        ->values(),
                ],

            ],

        ];
    }
}
