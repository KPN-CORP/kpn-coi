<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\FormReportExport;
use App\Http\Controllers\Controller;
use App\Models\FormDeclaration;
use App\Models\FormType;
use App\Services\DataScopeService;
use App\Services\FormDeclarationService;
use App\Services\FormPdfService;
use App\Services\FormReportService;
use App\Support\EmployeeStatus;
use App\Support\FormPeriod;
use Illuminate\Http\JsonResponse;
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
        abort_unless($request->user()?->can($formType->permissionName()), 403);

        $period = self::period($request, $formType->id);

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

        $label = $period ? (string) $period : 'All Periods';

        return Excel::download(
            new FormReportExport($rows, $formType->name_en, $label),
            "{$formType->name_en} Report {$label}.xlsx"
        );
    }

    public function pdf(Request $request, FormDeclaration $declaration, FormPdfService $pdf)
    {
        $this->authorizeDeclaration($request, $declaration);

        return $pdf->download($declaration, $request->string('locale')->toString());
    }

    /**
     * One signed declaration for the report's View pop-up, in both languages
     * and from the values snapshotted at signing -- what the employee's own
     * history shows.
     */
    public function document(Request $request, FormDeclaration $declaration, FormDeclarationService $forms): JsonResponse
    {
        $this->authorizeDeclaration($request, $declaration);

        // A draft has no signature or snapshot to show.
        abort_unless($declaration->isSigned(), 404);

        return response()->json([
            'id' => $declaration->id,
            'signed_name' => $declaration->signed_name,
            'signed_at' => $declaration->signed_at?->toIso8601String(),
            // The pop-up is headed by the form type, not the template's name.
            'form_type' => $declaration->formType?->summary()['name'],
            ...$forms->renderBilingual($declaration->template, $forms->signedValues($declaration)),
        ]);
    }

    private function authorizeDeclaration(Request $request, FormDeclaration $declaration): void
    {
        abort_unless(
            $declaration->formType
                && $request->user()?->can($declaration->formType->permissionName())
                && $this->report->canView($request->user(), $declaration),
            403
        );
    }

    /**
     * The report period; null = "all" (every period). This year by default.
     */
    /**
     * The report year (the `period` filter is a year); null = every year.
     * Defaults to the year of the latest started month with a template.
     */
    public static function period(Request $request, ?int $formTypeId = null): ?int
    {
        if ($request->input('period') === 'all') {
            return null;
        }

        return $request->filled('period')
            ? FormPeriod::yearFrom($request->period)
            : app(FormReportService::class)->defaultYear($formTypeId);
    }

    /**
     * On by default, like the COI report's toggle.
     */
    public static function latestSubmission(Request $request): bool
    {
        return $request->boolean('latest_submission', true);
    }

    /**
     * @return array{type: ?string, business_unit: ?string, contribution_level: ?string, search: ?string, status: ?string, employee_status: string}
     */
    public static function filters(Request $request): array
    {
        return [
            'type' => in_array($request->type, ['employee', 'non_employee'], true) ? $request->type : null,
            'business_unit' => $request->input('business_unit'),
            'contribution_level' => $request->input('contribution_level'),
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'employee_status' => EmployeeStatus::from($request->input('employee_status')),
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
