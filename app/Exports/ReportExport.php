<?php

namespace App\Exports;

use App\Exports\Sheets\ReportSheet;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements WithMultipleSheets
{
    public function __construct(
        protected LengthAwarePaginator $data
    ) {}

    public function sheets(): array
    {
        return [

            new ReportSheet(
                $this->data
            ),

        ];
    }
}