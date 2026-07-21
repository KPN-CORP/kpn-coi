<?php

namespace App\Exports;

use App\Exports\Sheets\ReportSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements WithMultipleSheets
{
    public function __construct(
        protected Collection $data,
        protected ?int $period = null
    ) {}

    public function sheets(): array
    {
        return [

            new ReportSheet(
                $this->data,
                $this->period
            ),

        ];
    }
}