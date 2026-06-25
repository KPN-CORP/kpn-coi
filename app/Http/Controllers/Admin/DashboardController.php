<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Services\DataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $period = now()->year;
        $totalEmployees = Employee::where('deleted_at', null)->count();

        $totalDeclarations = CoiDeclaration::count();

        $conflictDeclarations = CoiDeclaration::query()
            ->whereHas('responses', function ($query) {
                $query->whereJsonContains(
                    'response_value->answer',
                    true
                );
            })
            ->count();

        $query = CoiDeclaration::query()
            ->with([
                'user',
                'responses',
            ]);

        app(DataScopeService::class)
            ->apply(
                $query,
                Auth::user()
            );

        $query->when(
            $request->filled('period'),
            fn ($query) => $query->where(
                'period',
                (int) $request->period
            )
        );

        $query->when(
            $request->filled('status'),
            fn ($query) => $query->where(
                'status',
                $request->status
            )
        );

        $declarations = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render(
            'Admin/Dashboard',
            [
                'stats' => [
                    'total' => $totalEmployees,
                    'pending' => $totalEmployees - $totalDeclarations,
                    'submitted' => $totalDeclarations,
                    'conflict' => $conflictDeclarations,
                ],
                'declarations' => TeamDeclarationResource::collection(
                    $declarations
                ),
                'filters' => [
                    'period' => $request->period,
                    'status' => $request->status,
                ],
                'charts' => [
                    'status' => [
                        'submitted' => $totalDeclarations,
                        'pending' => $totalEmployees - $totalDeclarations,
                    ],

                    'business_units' => [
                        [
                            'name' => 'Corporation',
                            'submitted' => 450,
                            'pending' => 12,
                        ],
                    ],
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