<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\FormReportExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employee\FormController;
use App\Models\FormDeclaration;
use App\Models\FormType;
use App\Services\DataScopeService;
use App\Services\FormDeclarationService;
use App\Services\FormReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class FormReportController extends Controller
{
    private const SORTABLE = ['name', 'employee_id', 'business_unit', 'status', 'signed_at'];

    public function __construct(
        private readonly FormReportService $report,
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Request $request): Response
    {
        $formType = FormType::selectedOrDefault($request->input('form_type'));
        $period = $this->period($request);
        $filters = $this->filters($request);

        $all = $formType
            ? $this->report->adminRows($request->user(), $formType->id, $period, [...$filters, 'status' => null])
            : collect();

        $rows = $this->sorted($this->report->filterStatus($all, $filters['status']), $request);

        $perPage = (int) $request->input('per_page', 20);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        $page = max(1, (int) $request->input('page', 1));

        return Inertia::render('Admin/Forms/Report', [
            'rows' => new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            ),
            'stats' => $this->report->stats($all),
            'filters' => [
                ...$filters,
                'form_type' => $formType?->id,
                'period' => $period,
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction', 'asc'),
            ],
            'formTypes' => FormType::options(),
            'periods' => $this->report->periods($formType?->id),
            'businessUnitOptions' => $this->dataScope->businessUnitOptions($request->user()),
            'contributionLevelOptions' => $this->dataScope->contributionLevelOptions($request->user()),
        ]);
    }

    public function export(Request $request)
    {
        $formType = FormType::selectedOrDefault($request->input('form_type'));

        abort_unless($formType, 404);

        $period = $this->period($request);

        $rows = $this->sorted(
            $this->report->adminRows($request->user(), $formType->id, $period, $this->filters($request)),
            $request
        );

        return Excel::download(
            new FormReportExport($rows, $formType->name_en, $period),
            "{$formType->name_en} Report {$period}.xlsx"
        );
    }

    public function pdf(Request $request, FormDeclaration $declaration, FormDeclarationService $forms)
    {
        abort_unless($this->report->canView($request->user(), $declaration), 403);

        return FormController::pdfResponse($forms, $declaration, $request->string('locale')->toString());
    }

    private function period(Request $request): int
    {
        return $request->filled('period')
            ? (int) $request->period
            : (int) now()->year;
    }

    /**
     * @return array{type: ?string, business_unit: ?string, contribution_level: ?string, search: ?string, status: ?string}
     */
    private function filters(Request $request): array
    {
        return [
            'type' => in_array($request->type, ['employee', 'non_employee'], true) ? $request->type : null,
            'business_unit' => $request->input('business_unit'),
            'contribution_level' => $request->input('contribution_level'),
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];
    }

    private function sorted(Collection $rows, Request $request): Collection
    {
        $sort = $request->input('sort');

        if (! in_array($sort, self::SORTABLE, true)) {
            return $rows;
        }

        $value = fn (array $row) => mb_strtolower((string) ($row[$sort] ?? ''));

        return ($request->input('direction') === 'desc'
            ? $rows->sortByDesc($value)
            : $rows->sortBy($value)
        )->values();
    }
}
