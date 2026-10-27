<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormType;
use App\Models\User;
use App\Support\FormPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The admin dashboard for one form type, in the same shape as the COI one
 * (DashboardService::getDashboardData) so a single page and PDF show either:
 * stats.submitted / stats.pending are "signed" / "not signed" here, and the
 * fourth card is drafts instead of conflicts.
 */
class FormDashboardService
{
    public function __construct(
        private readonly FormReportService $report,
        private readonly DataScopeService $dataScope,
    ) {}

    public function getDashboardData(Request $request, FormType $formType, User $viewer): array
    {
        // Same type semantics as the COI dashboard: 'all' = both, anything
        // else (including the first load) = employees.
        $type = match ($request->input('type')) {
            'employee', 'non_employee' => $request->input('type'),
            'all' => null,
            default => 'employee',
        };

        // A year: every month with a template in it, each person counted
        // once by their newest signature or draft (as the report's Latest
        // Submission).
        $year = $request->filled('period')
            ? FormPeriod::yearFrom($request->period)
            : $this->report->defaultYear($formType->id);

        $businessUnit = $request->input('business_unit');

        $rows = $this->report->adminReportRows($viewer, $formType->id, $year, [
            'type' => $type,
            'business_unit' => $businessUnit,
        ], true);

        $stats = $this->report->stats($rows);

        return [
            'stats' => [
                'total' => $stats['total'],
                'submitted' => $stats['signed'],
                // Drafts are not signatures, so they count as not signed.
                'pending' => $stats['not_signed'],
                'draft' => $stats['draft'],
                'not_started' => $stats['not_started'],
            ],
            'barChart' => $this->barChart($rows),
            'filters' => [
                'period' => (string) $year,
                'business_unit' => $businessUnit,
                'type' => $request->input('type'),
            ],
            'businessUnitOptions' => $this->dataScope->businessUnitOptions($viewer),
            'periods' => $this->report->years($formType->id),
        ];
    }

    /**
     * Signed / not signed per business unit. People with no business unit
     * have no bar to fall under and are left out, as on the COI chart.
     */
    private function barChart(Collection $rows): array
    {
        $byUnit = $rows
            ->filter(fn (array $row) => filled($row['business_unit']))
            ->groupBy('business_unit')
            ->sortKeys();

        $notSigned = [FormReportService::STATUS_PENDING, FormReportService::STATUS_DRAFT];

        return [
            'title' => 'Signed by Business Unit',
            'labels' => $byUnit->keys()->values(),
            'datasets' => [
                [
                    'label' => 'Signed',
                    'backgroundColor' => '#AB2F2B',
                    'borderRadius' => 4,
                    'data' => $byUnit
                        ->map(fn (Collection $group) => $group->where('status', FormReportService::STATUS_SIGNED)->count())
                        ->values(),
                ],
                [
                    'label' => 'Not Signed',
                    'backgroundColor' => '#E2E8F0',
                    'borderRadius' => 4,
                    'data' => $byUnit
                        ->map(fn (Collection $group) => $group->whereIn('status', $notSigned)->count())
                        ->values(),
                ],
            ],
        ];
    }
}
