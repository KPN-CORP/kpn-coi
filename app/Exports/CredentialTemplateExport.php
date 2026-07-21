<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CredentialTemplateExport implements WithEvents,
    FromCollection,
    WithHeadings
{
    protected array $businessUnits = [
        'KPN Corporation',
        'Plantations',
        'Downstream',
        'Cement',
        'Property',
    ];

    // Columns mirror the Add User form. Nationality accepts a country name
    // ("Indonesia" for locals, otherwise the foreign country).
    public function headings(): array
    {
        return [

            'Fullname',

            'Email',

            'NIK',

            'Phone Number',

            'Nationality',

            'Citizen Number / Passport Number',

            'Business Unit',

            'Office Location',

            'Date of Join',

            'Permanent Address',

        ];
    }

    public function collection(): Collection
    {
        return collect([
            [

                'John Doe',

                'john.doe@email.com',

                'EMP001',

                '081234567890',

                'Indonesia',

                '3171234567890001',

                'KPN Corporation',

                'Head Office',

                '17-01-2020',

                'Jl. Rasuna Said...',

            ],

            [

                'Jane Doe',

                'jane.doe@email.com',

                'EMP002',

                '081298765432',

                'Malaysia',

                'A12345678',

                'Plantations',

                'Estate Office',

                '20-05-2025',

                'Jl. Menteng Dalam...',

            ],

        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                // Keep identity/phone columns as text so long digits (16-digit
                // KTP), leading zeros (phone) and alphanumeric passports are
                // preserved instead of being mangled into numbers.
                foreach (['C', 'D', 'F'] as $column) {
                    $event->sheet
                        ->getStyle("{$column}2:{$column}1000")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                }

                // Business Unit dropdown (column G).
                for ($row = 2; $row <= 1000; $row++) {

                    $validation = $event->sheet
                        ->getCell("G{$row}")
                        ->getDataValidation();

                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setFormula1(
                        '"' . implode(',', $this->businessUnits) . '"'
                    );
                }
            },
        ];
    }
}
