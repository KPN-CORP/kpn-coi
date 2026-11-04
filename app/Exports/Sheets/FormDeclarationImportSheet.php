<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Services\FormDeclarationService;
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
        return ['A' => 32, 'B' => 10, 'C' => 12, 'D' => 20];
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

                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                $sheet->freezePane('A2');

                // Keep IDs as text: numeric NIKs would otherwise lose leading
                // zeros. Dates as text too, so dd-mm-yyyy is read as typed.
                foreach (['A', 'D'] as $column) {
                    $sheet->getStyle("{$column}2:{$column}{$last}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                }

                $year = new DataValidation;
                $year->setType(DataValidation::TYPE_WHOLE);
                $year->setOperator(DataValidation::OPERATOR_BETWEEN);
                $year->setFormula1((string) FormDeclarationService::FIRST_UPLOAD_YEAR);
                $year->setFormula2('2100');
                $this->configure($year, 'Year', 'Required: the year the signature counts for, e.g. 2025.');
                $sheet->setDataValidation("B2:B{$last}", $year);

                $this->dropdown($sheet, "C2:C{$last}", ['Yes', 'No'], 'Signed', 'Choose Yes or No.');
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
