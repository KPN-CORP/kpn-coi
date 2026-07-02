<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeReportResource;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Services\DataScopeService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

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
                ],

                'periods' => CoiDeclaration::query()
                    ->distinct()
                    ->orderByDesc('period')
                    ->pluck('period')
                    ->values(),
            ]
        );
    }
}