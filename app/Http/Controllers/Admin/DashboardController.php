<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\DashboardExport;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\FormDashboardService;
use App\Services\FormSelectionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * One dashboard for every declaration: the "Form" filter picks Conflict of
 * Interest or one form type (Pakta Integritas, ...). Both are served in the
 * same shape, so the page and its PDF stay one implementation.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly FormDashboardService $formDashboard,
        private readonly FormSelectionService $forms,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard', $this->data($request));
    }

    public function downloadDashboardPdf(Request $request)
    {
        $data = $this->data($request);

        $pdf = Pdf::loadView(
            'pdf.dashboard.dashboard',
            [
                ...$data,
                'statusChart' => $request->input('status_chart'),
                'businessUnitChart' => $request->input('business_unit_chart'),
            ]
        )->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);

        return $pdf->download(sprintf('Commitment Corner Dashboard - %s.pdf', $data['formName']['en']));
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new DashboardExport(
                $this->dashboardService->getDashboardData($request)
            ),
            'Commitment Corner Dashboard.xlsx'
        );
    }

    /**
     * The selected form's dashboard plus what the Form filter offers.
     */
    private function data(Request $request): array
    {
        $user = $request->user();

        $options = $this->forms->options($user, 'dashboard.view', 'form_dashboard.view');

        $formType = $this->forms->selected($request, $options);

        $data = $formType
            ? $this->formDashboard->getDashboardData($request, $formType, $user)
            : $this->dashboardService->getDashboardData($request);

        return [...$data, ...$this->forms->props($formType, $options)];
    }
}
