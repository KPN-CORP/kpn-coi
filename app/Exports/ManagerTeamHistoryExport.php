<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\ManagerTeamHistorySheet;

class ManagerTeamHistoryExport implements WithMultipleSheets
{
    public function __construct(
        protected array $data
    ) {}

    public function sheets(): array
    {
        return [

            new ManagerTeamHistorySheet(
                $this->data
            ),

        ];
    }
}
