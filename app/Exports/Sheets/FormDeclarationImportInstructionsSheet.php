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
            ['Fill in the "Import" sheet, one row per person and period. Choose the form on the Import Center page before uploading.'],
            [],
            ['Column', 'Required', 'Description'],
            ['Employee ID or Email', 'Yes', 'Employee ID (NIK) or email of an employee or non-employee.'],
            ['Year', 'Yes', 'The year of the form, e.g. 2026.'],
            ['Month', 'No', 'The month, 1-12. Empty = the whole year: the person is signed on the latest template of that year that applies to them.'],
            ['Signed', 'Yes', 'Yes = record the person as signed for that year/month. No = leave them unchanged (an existing signature is never removed).'],
            ['Signed Date', 'No', 'When it was signed: dd-mm-yyyy or dd-mm-yyyy HH:mm (Jakarta time). Blank = the time of import. Cannot be in the future.'],
            [],
            ['Notes'],
            ['- Each signature goes on the template that applies to the person (their business unit, else the all-units template) for that year/month, as when signing in the app.'],
            ['- If any row has an error nothing is imported: download the error report, fix the rows and upload again.'],
            ['- Uploading the same file twice is safe: people who already signed that template are skipped.'],
            ['- The signed documents use the language selected in the app (top menu) when uploading.'],
            [],
            ['Examples'],
            ['Employee ID or Email', 'Year', 'Month', 'Signed', 'Signed Date'],
            ['01126010024', '2026', '10', 'Yes', '15-10-2026'],
            ['jane.doe@kpn-corp.com', '2026', '', 'Yes', '15-10-2026 09:30'],
            ['01126010099', '2026', '', 'No', ''],
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

                foreach (['A4:C4', 'A11', 'A17', 'A18:E18'] as $range) {
                    $sheet->getStyle($range)->getFont()->setBold(true);
                }
            },
        ];
    }
}
