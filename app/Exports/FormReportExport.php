<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\FormReportService;
use App\Services\FormVariableService;
use App\Support\FormPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per person from FormReportService -- light enough to build
 * in the request, unlike the COI export which carries every answer.
 */
class FormReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly string $formTypeName,
        private readonly int|string $period,
    ) {}

    public function title(): string
    {
        // Excel caps sheet titles at 31 characters.
        return mb_substr("{$this->formTypeName} {$this->period}", 0, 31);
    }

    public function array(): array
    {
        $statusLabels = [
            FormReportService::STATUS_SIGNED => 'Signed',
            // Same wording as the screens (FormStatusBadge).
            FormReportService::STATUS_DRAFT => 'Draft saved',
            FormReportService::STATUS_PENDING => 'Not started',
            FormReportService::STATUS_NO_TEMPLATE => 'No Template',
        ];

        $sheet = [[
            'Period',
            'Employee ID',
            'Full Name',
            'Type',
            'Business Unit',
            'Company',
            'Designation',
            'Work Location',
            'Template',
            'Status',
            'Signed Name',
            'Signed At',
            'Signed Language',
        ]];

        foreach ($this->rows as $row) {
            $sheet[] = [
                FormPeriod::label((int) $row['period']),
                $row['employee_id'] ?: '-',
                $row['name'],
                $row['type'] === 'employee' ? 'Employee' : 'Non Employee',
                $row['business_unit'] ?? '-',
                $row['contribution_level'] ?? '-',
                $row['designation'] ?? '-',
                $row['work_location'] ?? '-',
                $row['template_title']['en'] ?? '-',
                $statusLabels[$row['status']] ?? $row['status'],
                $row['signed_name'] ?? '-',
                $row['signed_at']
                    ? Carbon::parse($row['signed_at'])->timezone(FormVariableService::DISPLAY_TIMEZONE)->format('d-m-Y H:i:s')
                    : '-',
                $row['signed_locale'] ? strtoupper($row['signed_locale']) : '-',
            ];
        }

        return $sheet;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
