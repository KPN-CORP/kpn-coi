<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\FormType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class FormDeclarationImportInstructionsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    public function __construct(
        protected FormType $formType
    ) {}

    public function array(): array
    {
        return [
            ["Import {$this->formType->name_en} signatures"],
            ['Fill in the "Import" sheet, one row per person and year. Choose the form on the Import Center page before uploading.'],
            [],
            ['Column', 'Required', 'Description'],
            ['Employee ID or Email', 'Yes', 'Employee ID (NIK) or email of an employee or non-employee.'],
            ['Year', 'Yes', 'The year the signature counts for, e.g. 2025 -- this year or an earlier one, from 2025.'],
            ['Signed', 'Yes', 'Yes = record the person as signed for that year. No = leave them unchanged (an existing signature is never removed).'],
            ['Signed Date', 'No', 'When it was signed: dd-mm-yyyy or dd-mm-yyyy HH:mm (Jakarta time). Blank = the time of import. Cannot be in the future.'],
            [],
            ['Notes'],
            ['- Each signature goes on the template that applies to the person now (their business unit, else the all-units template), as when signing in the app, and counts for the Year given -- earlier years need no template of their own.'],
            ['- Rows are imported one by one: rows with an error are listed in the result report -- fix them and upload again.'],
            ['- Uploading the same file twice is safe: people who already hold a signature for that year are skipped.'],
            ['- The signed documents use the language selected in the app (top menu) when uploading.'],
            [],
            ['Examples'],
            ['Employee ID or Email', 'Year', 'Signed', 'Signed Date'],
            ['01126010024', '2025', 'Yes', '15-10-2025'],
            ['jane.doe@kpn-corp.com', '2026', 'Yes', '15-10-2026 09:30'],
            ['01126010099', '2025', 'No', ''],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 26, 'B' => 12, 'C' => 110, 'D' => 12, 'E' => 18];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                foreach (['A4:C4', 'A10', 'A16', 'A17:D17'] as $range) {
                    $sheet->getStyle($range)->getFont()->setBold(true);
                }
            },
        ];
    }
}
