<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamDeclarationResource;
use App\Models\CoiDeclaration;
use App\Models\CoiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $perPage = (int) ($request->per_page ?? 10);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 10;
        }

        $user = $request->user();

        // user_id alone is ambiguous: the employee (kpncorp User) and
        // non-employee (local) id spaces overlap. Scope by type to the kind of
        // account that is signed in so one never sees the other's rows.
        $type = $user instanceof User ? 'employee' : 'non_employee';

        // The per-user set is tiny (one row per period), so the 2025 hide/pin
        // rules are applied in memory rather than in awkward JSON SQL.
        $all = CoiDeclaration::query()
            ->with('responses')
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->get();

        // Rule 6: a 2025 "No" declaration has nothing to act on -> hide it.
        $visible = $all->reject(
            fn (CoiDeclaration $d) => $this->isLegacy($d) && ! $this->hasConflict($d)
        );

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

    private function hasConflict(CoiDeclaration $declaration): bool
    {
        return $declaration->responses->contains(
            fn (CoiResponse $r) => data_get($r->response_value, 'answer') === true
        );
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

    private function isPendingUpload(CoiDeclaration $declaration): bool
    {
        if (! $this->isLegacy($declaration) || ! $this->hasConflict($declaration)) {
            return false;
        }

        $response = $declaration->responses
            ->firstWhere('question_key', CoiDeclaration::LEGACY_CONFLICT_KEY);

        return ! data_get($response?->response_value, 'attachment');
    }
}