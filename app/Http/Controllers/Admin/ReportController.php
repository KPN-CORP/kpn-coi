<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportDownload;
use App\Models\CoiDeclaration;
use App\Models\Employee;
use App\Models\ReportDownload;
use App\Services\DataScopeService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $period = (int) (
            $request->period
            ?? now()->year
        );

        $latestSubmission = $request->boolean(
            'latest_submission',
            true
        );

        $perPage = (int) ($request->per_page ?? 20);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        $sort = $request->string('sort')->toString();

        if (! in_array($sort, [
            'period',
            'type',
            'name',
            'employee_id',
            'status',
            'has_conflict',
            'submitted_at',
        ], true)) {
            $sort = null;
        }

        $direction = $request->string('direction')->toString() === 'desc'
            ? 'desc'
            : 'asc';

        // Whether the submission declares a conflict -- a separate axis from
        // the form status. More values are expected here later.
        $declarationStatus = $request->string('declaration_status')->toString();

        if (! in_array($declarationStatus, ['clear', 'conflict'], true)) {
            $declarationStatus = null;
        }

        $records = app(
            ReportService::class
        )->getDeclarations(
            period: $period,
            status: $request->status,
            type: $request->type,
            businessUnit: $request->business_unit,
            search: $request->search,
            user: Auth::user(),
            latestSubmission: $latestSubmission,
            perPage: $perPage,
            sort: $sort,
            direction: $direction,
            declarationStatus: $declarationStatus,
        );

        return Inertia::render(
            'Admin/Report',
            [
                'declarations' => $records,

                'filters' => [
                    'period' => $request->period,
                    'status' => $request->status,
                    'declaration_status' => $declarationStatus,
                    'type' => $request->type,
                    'search' => $request->search,
                    'business_unit' => $request->business_unit,
                    'latest_submission' => $latestSubmission,
                    'per_page' => $perPage,
                    'sort' => $sort,
                    'direction' => $direction,
                ],
                // Scoped like the rows behind it: a restricted admin must not
                // be offered a business unit they cannot open.
                'businessUnitOptions' => app(DataScopeService::class)
                    ->applyToPeople(Employee::query(), Auth::user())
                    ->whereNotNull('group_company')
                    ->distinct()
                    ->orderBy('group_company')
                    ->pluck('group_company'),

                'periods' => CoiDeclaration::query()
                    ->distinct()
                    ->orderByDesc('period')
                    ->pluck('period')
                    ->values(),

                'companyNames' => \App\Models\Companies::query()
                    ->pluck('contribution_level', 'contribution_level_code'),
            ]
        );
    }

    /**
     * Queue a background job that builds the .xlsx export, so a large
     * dataset never exhausts the web request's memory / time limit.
     */
    public function export(Request $request): JsonResponse
    {
        $this->pruneExpiredExports();

        $download = ReportDownload::create([
            'user_id' => Auth::id(),
            'status' => ReportDownload::STATUS_PENDING,
            'filters' => [
                'period' => (int) ($request->period ?? now()->year),
                'status' => $request->status,
                'declaration_status' => $request->declaration_status,
                'type' => $request->type,
                'business_unit' => $request->business_unit,
                'search' => $request->search,
                'latest_submission' => $request->boolean('latest_submission', true),
            ],
        ]);

        GenerateReportDownload::dispatch($download->id);

        return response()->json([
            'id' => $download->id,
            'status' => $download->status,
        ]);
    }

    /**
     * Polled by the UI while the export is being generated.
     */
    public function exportStatus(ReportDownload $reportDownload): JsonResponse
    {
        abort_unless(
            $reportDownload->user_id === Auth::id(),
            403
        );

        return response()->json([
            'id' => $reportDownload->id,
            'status' => $reportDownload->status,
            'ready' => $reportDownload->isCompleted(),
            'error' => $reportDownload->isFailed()
                ? 'The export could not be generated. Please try again.'
                : null,
            'download_url' => $reportDownload->isCompleted()
                ? route('admin.report.export.download', $reportDownload)
                : null,
        ]);
    }

    public function exportDownload(ReportDownload $reportDownload): StreamedResponse
    {
        abort_unless(
            $reportDownload->user_id === Auth::id(),
            403
        );

        abort_unless(
            $reportDownload->isCompleted()
                && $reportDownload->file_path
                && Storage::disk('local')->exists($reportDownload->file_path),
            404
        );

        return Storage::disk('local')->download(
            $reportDownload->file_path,
            $reportDownload->file_name ?? 'COI Report.xlsx'
        );
    }

    /**
     * Download a single declaration as PDF (admin — any declaration),
     * in the requested locale (id / en).
     */
    public function exportPdf(Request $request, CoiDeclaration $declaration)
    {
        $locale = $request->string('locale')->toString();

        if (! in_array($locale, ['en', 'id'], true)) {
            $locale = 'en';
        }

        $declaration->load([
            'responses',
        ]);

        // The declarant is resolved by type via declarant(); eager-loading
        // user.employee here would resolve a non-employee row against kpncorp,
        // which is the wrong database and can match an unrelated person.

        $pdf = Pdf::loadView(
            'pdf.declaration',
            [
                'declaration' => $declaration,
                'locale' => $locale,
                'companyNames' => \App\Models\Companies::query()
                    ->pluck('contribution_level', 'contribution_level_code')
                    ->toArray(),
            ]
        )->setOptions([
            'isPhpEnabled' => true,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);

        return $pdf->download(
            sprintf(
                'COI-Declaration-%s-%s-%s.pdf',
                $declaration->period,
                strtoupper($locale),
                $declaration->created_at->format('Ymd_His')
            )
        );
    }

    /**
     * Stream the 2025 supporting document for a declaration so an admin can
     * open it from the report. Admin-scoped (no owner check, unlike the
     * employee-facing route).
     */
    public function attachment(CoiDeclaration $declaration): StreamedResponse
    {
        abort_unless(
            $declaration->type === 'employee'
                && (int) $declaration->period === CoiDeclaration::LEGACY_PERIOD,
            404
        );

        $response = $declaration->responses()
            ->where('question_key', CoiDeclaration::LEGACY_CONFLICT_KEY)
            ->first();

        $path = data_get($response?->response_value, 'attachment');

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    /**
     * Remove generated exports (and their files) older than 24 hours.
     */
    private function pruneExpiredExports(): void
    {
        ReportDownload::where('created_at', '<', now()->subDay())
            ->get()
            ->each
            ->purge();
    }
}