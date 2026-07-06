<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $declarations = CoiDeclaration::query()
            ->withCount('responses')
            ->where('user_id', $request->user()->id)
            ->when(
                $request->filled('period'),
                fn ($query) => $query->where(
                    'period',
                    (int) $request->period
                )
            )
            ->latest('period')
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'Employee/History',
            [
                'declarations' => TeamDeclarationResource::collection(
                    $declarations
                ),

                'filters' => [
                    'period' => $request->period,
                ],

                'periods' => CoiDeclaration::query()
                    ->where('user_id', $request->user()->id)
                    ->distinct()
                    ->orderByDesc('period')
                    ->pluck('period')
                    ->values(),
            ]
        );
    }
}