<?php

namespace App\Exports\Sheets;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class SummarySheet implements FromArray, WithTitle
{
    public function __construct(
        protected array $dashboard
    ) {}

    public function array(): array
    {
        $dashboard = $this->dashboard;

        return [

            ['Compliance Dashboard'],

            [],

            ['Reporting Period',
                $dashboard['filters']['period']
                    ?? 'All'],

            [],

            ['Metric', 'Value'],

            ['Total Employee',
                $dashboard['stats']['total']],

            ['Submitted',
                $dashboard['stats']['submitted']],

            ['Pending',
                $dashboard['stats']['pending']],

            ['Conflict',
                $dashboard['stats']['conflict']],

        ];
    }

    public function title(): string
    {
        return 'Summary';
    }
}