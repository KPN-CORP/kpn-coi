<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FormType;
use App\Services\FormReportService;
use App\Support\FormPeriod;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class TeamFormController extends Controller
{
    public function __construct(
        private readonly FormReportService $report,
    ) {}

    /**
     * Form status (for one form type) of everyone reporting to this manager
     * (L1 or L2), for one period or all of them.
     * Role data restrictions do not apply here, same as Team History: the
     * reporting line is the scope.
     */
    public function index(Request $request, FormType $formType): Response
    {
        abort_unless($formType->is_active, 404);

        // The `period` filter is a year (every month with a template in it),
        // or "all" for every year.
        $allPeriods = $request->input('period') === 'all';
        $year = ! $allPeriods && $request->filled('period')
            ? FormPeriod::yearFrom($request->period)
            : $this->report->defaultYear($formType->id);

        // On by default, like the COI Team History toggle.
        $latestSubmission = $request->boolean('latest_submission', true);

        $managerId = $request->user()->employee_id;

        $search = $request->string('search')->toString();

        $team = Employee::query()
            ->whereNull('deleted_at')
            ->where(
                fn ($query) => $query
                    ->where('manager_l1_id', $managerId)
                    ->orWhere('manager_l2_id', $managerId)
            );

        $people = fn (int $forPeriod) => [
            $this->report->joinedBy(clone $team, $forPeriod)
                ->when(
                    filled($search),
                    fn ($query) => $query->where(
                        fn ($inner) => $inner
                            ->where('fullname', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%")
                    )
                )
                ->when(
                    $request->filled('business_unit'),
                    fn ($query) => $query->where('group_company', $request->business_unit)
                ),
            null,
        ];

        $rows = $this->report->buildRowsAcross($formType->id, $this->report->monthsIn($formType->id, $allPeriods ? null : $year), $people);

        $status = FormReportService::statusFilter($request->status);
        $rows = $this->report->filterStatus($rows, $status);

        // Applied after the status filter, as on the COI Team History: "latest
        // signed" means the newest signature, not the newest row.
        if ($latestSubmission) {
            $rows = $this->report->latestPerPerson($rows);
        }

        $sort = in_array($request->sort, FormReportService::SORTABLE, true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $rows = $this->report->sort($rows, $sort, $direction);

        $perPage = (int) $request->input('per_page', 10);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 10;
        }

        $page = max(1, (int) $request->input('page', 1));

        return Inertia::render('Manager/TeamForms', [
            'rows' => new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            ),
            'filters' => [
                'form_type' => $formType->id,
                'period' => $allPeriods ? 'all' : $year,
                'search' => $search,
                'status' => $status,
                'business_unit' => $request->business_unit,
                'latest_submission' => $latestSubmission,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'formType' => $formType->summary(),
            // Switching type on the page moves to that type's team URL.
            'formTypes' => array_values(array_filter(FormType::options(), fn (array $type) => $type['is_active'])),
            'periods' => $this->report->years($formType->id),
            'businessUnitOptions' => (clone $team)
                ->whereNotNull('group_company')
                ->distinct()
                ->orderBy('group_company')
                ->pluck('group_company'),
        ]);
    }
}
