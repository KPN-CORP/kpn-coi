<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\ImportLog;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Every row of one Import Center upload with what happened to it, failed and
 * successful alike. A failed row can be fixed here and the sheet re-uploaded
 * as is: the first five columns match the import template.
 */
class ImportLogReportExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    private const LABELS = [
        ImportLog::ROW_IMPORTED => 'Imported',
        ImportLog::ROW_ALREADY_SIGNED => 'Already signed',
        ImportLog::ROW_NOT_SIGNED => 'Not signed (No)',
        ImportLog::ROW_FAILED => 'Failed',
    ];

    /** Light fill per result, so failures stand out. */
    private const COLOURS = [
        ImportLog::ROW_IMPORTED => 'DCFCE7',
        ImportLog::ROW_ALREADY_SIGNED => 'F1F5F9',
        ImportLog::ROW_NOT_SIGNED => 'F1F5F9',
        ImportLog::ROW_FAILED => 'FEE2E2',
    ];

    public function __construct(
        protected ImportLog $log
    ) {}

    public function headings(): array
    {
        return ['Employee ID or Email', 'Year', 'Month', 'Signed', 'Signed Date', 'Row', 'Name', 'Result', 'Message'];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['identifier'],
            $row['year'],
            $row['month'],
            $row['signed'],
            $row['signed_date'] ?? '',
            $row['row'],
            $row['name'] ?? '',
            self::LABELS[$row['status']] ?? $row['status'],
            $row['message'],
        ], $this->log->results ?? []);
    }

    public function title(): string
    {
        return 'Import Result';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:I1')->getFont()->setBold(true);
                $sheet->freezePane('A2');

                foreach ($this->log->results ?? [] as $index => $row) {
                    $sheet->getStyle('H'.($index + 2))->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::COLOURS[$row['status']] ?? 'FFFFFF');
                }
            },
        ];
    }
}
