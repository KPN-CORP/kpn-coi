<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\FormReportExport;
use App\Http\Controllers\Controller;
use App\Models\FormDeclaration;
use App\Models\FormType;
use App\Services\DataScopeService;
use App\Services\FormPdfService;
use App\Services\FormReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class FormReportController extends Controller
{
    public function __construct(
        private readonly FormReportService $report,
        private readonly DataScopeService $dataScope,
    ) {}

    /**
     * Merged into the main report (its Form filter); kept for old links.
     */
    public function index(Request $request): RedirectResponse
    {
        $formType = FormType::selectedOrDefault($request->input('form_type'));

        return redirect()->route('admin.report', [
            ...$request->except('form_type'),
            'form' => $formType?->code,
        ]);
    }

    public function export(Request $request)
    {
        $formType = FormType::selectedOrDefault($request->input('form_type'));

        abort_unless($formType, 404);

        $period = self::period($request);

        $rows = $this->sorted(
            $this->report->adminReportRows(
                $request->user(),
                $formType->id,
                $period,
                self::filters($request),
                self::latestSubmission($request)
            ),
            $request
        );

        $label = $period ?? 'All Periods';

        return Excel::download(
            new FormReportExport($rows, $formType->name_en, $label),
            "{$formType->name_en} Report {$label}.xlsx"
        );
    }

    public function pdf(Request $request, FormDeclaration $declaration, FormPdfService $pdf)
    {
        abort_unless($this->report->canView($request->user(), $declaration), 403);

        return $pdf->download($declaration, $request->string('locale')->toString());
    }

    /**
     * The report period; null = "all" (every period). This year by default.
     */
    public static function period(Request $request): ?int
    {
        if ($request->input('period') === 'all') {
            return null;
        }

        return $request->filled('period')
            ? (int) $request->period
            : (int) now()->year;
    }

    /**
     * On by default, like the COI report's toggle.
     */
    public static function latestSubmission(Request $request): bool
    {
        return $request->boolean('latest_submission', true);
    }

    /**
     * @return array{type: ?string, business_unit: ?string, contribution_level: ?string, search: ?string, status: ?string}
     */
    public static function filters(Request $request): array
    {
        return [
            'type' => in_array($request->type, ['employee', 'non_employee'], true) ? $request->type : null,
            'business_unit' => $request->input('business_unit'),
            'contribution_level' => $request->input('contribution_level'),
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];
    }

    private function sorted(Collection $rows, Request $request): Collection
    {
        return $this->report->sort(
            $rows,
            $request->input('sort'),
            $request->input('direction') === 'desc' ? 'desc' : 'asc'
        );
    }
}
