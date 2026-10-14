<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FormType;
use App\Services\FormReportService;
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
     * (L1 or L2).
     * Role data restrictions do not apply here, same as Team History: the
     * reporting line is the scope.
     */
    public function index(Request $request, FormType $formType): Response
    {
        abort_unless($formType->is_active, 404);

        $period = $request->filled('period') ? (int) $request->period : (int) now()->year;

        $managerId = $request->user()->employee_id;

        $search = $request->string('search')->toString();

        $team = Employee::query()
            ->whereNull('deleted_at')
            ->where(
                fn ($query) => $query
                    ->where('manager_l1_id', $managerId)
                    ->orWhere('manager_l2_id', $managerId)
            );

        // Direct reports name this manager as L1; everyone else in the team
        // reaches them through L2 only.
        $directIds = (clone $team)
            ->where('manager_l1_id', $managerId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $line = in_array($request->line, ['direct', 'indirect'], true) ? $request->line : null;

        $rows = $this->report->buildRows(
            $formType->id,
            $period,
            $this->report->joinedBy(clone $team, $period)
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
                )
                ->when(
                    $line === 'direct',
                    fn ($query) => $query->where('manager_l1_id', $managerId)
                )
                ->when(
                    $line === 'indirect',
                    fn ($query) => $query->where(
                        fn ($inner) => $inner
                            ->whereNull('manager_l1_id')
                            ->orWhere('manager_l1_id', '!=', $managerId)
                    )
                ),
            null
        )->map(fn (array $row) => [
            ...$row,
            'relation' => $directIds->has($row['user_id']) ? 'direct' : 'indirect',
        ]);

        // Counts for the status tabs: taken before the status filter so every
        // tab shows its own size.
        $stats = $this->report->stats($rows);

        $status = in_array($request->status, ['not_signed', 'signed', 'draft', 'pending', 'no_template'], true)
            ? $request->status
            : null;

        $rows = match ($status) {
            // "Not signed" = not started + saved as draft.
            'not_signed' => $rows->whereIn('status', ['pending', 'draft'])->values(),
            null => $rows,
            default => $rows->where('status', $status)->values(),
        };

        $sort = in_array($request->sort, ['name', 'status', 'signed_at', 'business_unit'], true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        // Status sorts by how far along someone is, not alphabetically.
        $statusOrder = ['pending' => 0, 'draft' => 1, 'signed' => 2, 'no_template' => 3];
        $sortValue = fn (array $row) => match ($sort) {
            'status' => $statusOrder[$row['status']] ?? 9,
            'signed_at' => $row['signed_at'] ?? '',
            default => mb_strtolower((string) ($row[$sort] ?? '')),
        };

        $rows = ($direction === 'desc' ? $rows->sortByDesc($sortValue) : $rows->sortBy($sortValue))->values();

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
            'stats' => $stats,
            'filters' => [
                'form_type' => $formType->id,
                'period' => $period,
                'search' => $search,
                'status' => $status,
                'business_unit' => $request->business_unit,
                'line' => $line,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'formType' => $formType->summary(),
            // Switching type on the page moves to that type's team URL.
            'formTypes' => array_values(array_filter(FormType::options(), fn (array $type) => $type['is_active'])),
            'periods' => $this->report->periods($formType->id),
            'businessUnitOptions' => (clone $team)
                ->whereNotNull('group_company')
                ->distinct()
                ->orderBy('group_company')
                ->pluck('group_company'),
        ]);
    }
}
