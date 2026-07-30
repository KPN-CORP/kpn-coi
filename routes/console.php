<?php

use App\Models\ReportDownload;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove generated report exports (and their files) older than 24 hours.
Schedule::call(function () {
    ReportDownload::where('created_at', '<', now()->subDay())
        ->get()
        ->each
        ->purge();
})->hourly();

// Keep the failed_jobs table from growing without bound. Database queue is the
// only transport, so failures accumulate here until pruned.
Schedule::command('queue:prune-failed --hours=168')->daily();
