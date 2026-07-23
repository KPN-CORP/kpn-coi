<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\DashboardExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Services\DataScopeService;
use App\Services\DashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    private DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request): Response
    {
        return Inertia::render(
            'Admin/Dashboard',
            $this->dashboardService->getDashboardData($request)
        );
    }

    public function downloadDashboardPdf(Request $request)
    {
        $data = $this->dashboardService->getDashboardData($request);

        $pdf = Pdf::loadView(
            'pdf.dashboard.dashboard',
            [
                ...$this->dashboardService->getDashboardData($request),
                'statusChart' => $request->input('status_chart'),
                'businessUnitChart' => $request->input('business_unit_chart'),
            ]
        )->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download('Commitment Corner Dashboard.pdf');
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
}