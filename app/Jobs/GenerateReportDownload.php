<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\ReportExport;
use App\Models\ReportDownload;
use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateReportDownload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Generating a large workbook is memory-heavy; give the job room and
     * plenty of time so it never fails the way the web request did.
     */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $reportDownloadId
    ) {}

    public function handle(ReportService $reportService): void
    {
        @ini_set('memory_limit', '1024M');

        $download = ReportDownload::find($this->reportDownloadId);

        if (! $download) {
            return;
        }

        $download->update([
            'status' => ReportDownload::STATUS_PROCESSING,
        ]);

        try {
            $filters = $download->filters ?? [];

            $records = $reportService->getAllDeclarations(
                period: (int) ($filters['period'] ?? now()->year),
                status: $filters['status'] ?? null,
                search: $filters['search'] ?? null,
                type: $filters['type'] ?? null,
                businessUnit: $filters['business_unit'] ?? null,
                latestSubmission: (bool) ($filters['latest_submission'] ?? true),
                declarationStatus: $filters['declaration_status'] ?? null,
            );

            $fileName = 'COI Report '
                .now()->format('Ymd_His')
                .'.xlsx';

            $relativePath = 'exports/report_'
                .$download->id
                .'_'
                .now()->format('YmdHis')
                .'.xlsx';

            Excel::store(
                new ReportExport($records),
                $relativePath,
                'local'
            );

            $download->update([
                'status' => ReportDownload::STATUS_COMPLETED,
                'file_path' => $relativePath,
                'file_name' => $fileName,
                'completed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Report export failed', [
                'report_download_id' => $download->id,
                'message' => $e->getMessage(),
            ]);

            $download->update([
                'status' => ReportDownload::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        ReportDownload::where('id', $this->reportDownloadId)->update([
            'status' => ReportDownload::STATUS_FAILED,
            'error' => $exception->getMessage(),
        ]);
    }
}
