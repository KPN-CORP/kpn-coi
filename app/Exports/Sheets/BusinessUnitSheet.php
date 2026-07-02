<?php

namespace App\Exports\Sheets;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class BusinessUnitSheet implements FromArray, WithTitle
{
    public function __construct(
        protected array $dashboard
    ) {}

    public function array(): array
    {
        $dashboard = $this->dashboard;

        $chart = $dashboard['barChart'];

        $rows = [];

        foreach ($chart['labels'] as $index => $label) {

            $rows[] = [
                $label,
                $chart['datasets'][0]['data'][$index] ?? 0,
                $chart['datasets'][1]['data'][$index] ?? 0,
            ];

        }

        return $rows;
    }

    public function title(): string
    {
        return 'Business Unit';
    }
}