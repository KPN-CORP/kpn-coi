<?php

namespace App\Exports;

use App\Exports\Sheets\BusinessUnitSheet;
use App\Exports\Sheets\SubmissionSheet;
use App\Exports\Sheets\SummarySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DashboardExport implements WithMultipleSheets
{
    public function __construct(
        protected array $dashboard
    ) {
    }

    
    public function sheets(): array
    {
        return [
            new SummarySheet($this->dashboard),
            new BusinessUnitSheet($this->dashboard),
            new SubmissionSheet($this->dashboard),
        ];
    }
}