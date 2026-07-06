<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeReportResource;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Models\NonEmployee;
use App\Services\DataScopeService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $period = (int) (
            $request->period
            ?? now()->year
        );

        $records = app(
            ReportService::class
        )->getDeclarations(
            period: $period,
            status: $request->status,
            type: $request->type,
            businessUnit: $request->business_unit,
            search: $request->search,
            user: Auth::user(),
        );

        return Inertia::render(
            'Admin/Report',
            [
                'declarations' => $records,

                'filters' => [
                    'period' => $request->period,
                    'status' => $request->status,
                    'type' => $request->type,
                    'search' => $request->search,
                    'business_unit' => $request->business_unit,
                ],
                'businessUnitOptions' => Employee::query()
                    ->whereNotNull('group_company')
                    ->distinct()
                    ->orderBy('group_company')
                    ->pluck('group_company'),

                'periods' => CoiDeclaration::query()
                    ->distinct()
                    ->orderByDesc('period')
                    ->pluck('period')
                    ->values(),
            ]
        );
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new ReportExport(
                app(ReportService::class)->getDeclarations(
                    period: (int) ($request->period ?? now()->year),
                    status: $request->status,
                    search: $request->search,
                    type: $request->type,
                    businessUnit: $request->business_unit,
                    user: Auth::user(),
                )
            ),
            'COI Report.xlsx'
        );
    }
}