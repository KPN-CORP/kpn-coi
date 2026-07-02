<?php

namespace App\Exports\Sheets;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class SubmissionSheet implements FromArray, WithTitle
{
    public function __construct(
        protected array $dashboard
    ) {}

    public function array(): array
    {
        $dashboard = $this->dashboard;

        $rows = [];

        $type = $dashboard['rawDeclarations'][0]->type ?? 'employee';

        $rows[] = $type === 'employee'
            ? [
                'Employee ID',
                'Employee Name',
                'Status',
            ]
            : [
                'Number ID',
                'Fullname',
                'Status',
            ];

        foreach ($dashboard['rawDeclarations'] as $row) {

            if ($row->type === 'employee') {

                $identifier = $row->user?->employee?->employee_id;
                $name = $row->user?->employee?->fullname;
                $organization = $row->user?->employee?->group_company;

            } else {

                $identifier = $row->nonEmployeeUser?->employee?->ktp;
                $name = $row->nonEmployeeUser?->employee?->fullname;
                $organization = $row->nonEmployeeUser?->company;

            }

            $rows[] = [
                $identifier,
                $name,
                ucfirst($row->status->value),
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Submissions';
    }
}