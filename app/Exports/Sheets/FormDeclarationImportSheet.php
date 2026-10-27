<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FormDeclarationImportSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    private const ROWS = 2000;

    /**
     * Read back by heading (FormDeclarationImport, WithHeadingRow slugs), so
     * renaming one breaks the import.
     */
    public function headings(): array
    {
        return [
            'Employee ID or Email',
            'Year',
            'Month',
            'Signed',
            'Signed Date',
        ];
    }

    public function array(): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 32, 'B' => 10, 'C' => 10, 'D' => 12, 'E' => 20];
    }

    public function title(): string
    {
        return 'Import';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = self::ROWS + 1;

                $sheet->getStyle('A1:E1')->getFont()->setBold(true);
                $sheet->freezePane('A2');

                // Keep IDs as text: numeric NIKs would otherwise lose leading
                // zeros. Dates as text too, so dd-mm-yyyy is read as typed.
                foreach (['A', 'E'] as $column) {
                    $sheet->getStyle("{$column}2:{$column}{$last}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                }

                $year = new DataValidation;
                $year->setType(DataValidation::TYPE_WHOLE);
                $year->setOperator(DataValidation::OPERATOR_BETWEEN);
                $year->setFormula1('2000');
                $year->setFormula2('2100');
                $this->configure($year, 'Year', 'Required, e.g. 2026.');
                $sheet->setDataValidation("B2:B{$last}", $year);

                $this->dropdown($sheet, "C2:C{$last}", range(1, 12), 'Month', 'Optional: 1-12. Empty = the whole year.');
                $this->dropdown($sheet, "D2:D{$last}", ['Yes', 'No'], 'Signed', 'Choose Yes or No.');
            },
        ];
    }

    /** One validation over the whole range rather than one per cell. */
    private function dropdown(Worksheet $sheet, string $range, array $options, string $title, string $prompt): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"'.implode(',', $options).'"');
        $this->configure($validation, $title, $prompt);

        $sheet->setDataValidation($range, $validation);
    }

    private function configure(DataValidation $validation, string $title, string $prompt): void
    {
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle($title);
        $validation->setPrompt($prompt);
    }
}
