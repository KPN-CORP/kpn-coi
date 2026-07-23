<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Services\DeclarationScopeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __construct(
        private readonly DeclarationScopeService $scope
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $perPage = (int) ($request->per_page ?? 10);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 10;
        }

        $user = $request->user();

        // The per-user set is tiny (one row per period), so the 2025 pin rule
        // is applied in memory rather than in awkward JSON SQL.
        //
        // Every 2025 row is shown, conflict or not -- all of them require a
        // supporting document, and any still missing one is pinned to the top.
        //
        // Ownership is matched on (user_id, type), never user_id alone: the two
        // id spaces overlap. It also carries any earlier non-employee account
        // this person was converted from, so a conversion never hides history.
        $visible = $this->scope->applyOwnership(
            CoiDeclaration::query()->with('responses'),
            $user
        )->get();

        $periods = $visible
            ->pluck('period')
            ->unique()
            ->sortDesc()
            ->values();

        $filtered = $request->filled('period')
            ? $visible->where('period', (int) $request->period)
            : $visible;

        $sort = in_array(
            $request->sort,
            ['period', 'status', 'submitted_at', 'created_at'],
            true
        )
            ? $request->sort
            : null;

        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        // Rule 5: a 2025 "Yes" still missing its attachment is pending -> pin
        // it to the very top regardless of sort. Below the pin, apply the
        // requested column sort, falling back to newest-period-first.
        $sorted = $filtered
            ->sort(function (CoiDeclaration $a, CoiDeclaration $b) use ($sort, $direction) {
                $pending = $this->isPendingUpload($b) <=> $this->isPendingUpload($a);

                if ($pending !== 0) {
                    return $pending;
                }

                if ($sort) {
                    $cmp = $this->sortValue($a, $sort) <=> $this->sortValue($b, $sort);

                    if ($cmp !== 0) {
                        return $direction === 'desc' ? -$cmp : $cmp;
                    }
                }

                if ($a->period !== $b->period) {
                    return $b->period <=> $a->period;
                }

                return $b->updated_at <=> $a->updated_at;
            })
            ->values();

        $page = (int) $request->input('page', 1);

        $paginated = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render(
            'Employee/History',
            [
                'declarations' => TeamDeclarationResource::collection(
                    $paginated
                ),

                'filters' => [
                    'period' => $request->period,
                    'sort' => $sort,
                    'direction' => $direction,
                ],

                'periods' => $periods,

                'companyNames' => \App\Models\Companies::query()
                    ->pluck('contribution_level', 'contribution_level_code'),
            ]
        );
    }

    private function isLegacy(CoiDeclaration $declaration): bool
    {
        return (int) $declaration->period === CoiDeclaration::LEGACY_PERIOD;
    }

    private function sortValue(CoiDeclaration $declaration, string $sort): int|string
    {
        return match ($sort) {
            'period' => (int) $declaration->period,
            'status' => (string) ($declaration->status?->value ?? ''),
            'submitted_at' => optional($declaration->submitted_at)->timestamp ?? 0,
            'created_at' => optional($declaration->created_at)->timestamp ?? 0,
            default => 0,
        };
    }

    /**
     * Every 2025 row needs a supporting document, whether or not a conflict
     * was declared. Until it is uploaded the row counts as pending.
     */
    private function isPendingUpload(CoiDeclaration $declaration): bool
    {
        if (! $this->isLegacy($declaration)) {
            return false;
        }

        $response = $declaration->responses
            ->firstWhere('question_key', CoiDeclaration::LEGACY_CONFLICT_KEY);

        return ! data_get($response?->response_value, 'attachment');
    }
}