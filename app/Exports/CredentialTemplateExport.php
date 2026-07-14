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

    public function headings(): array
    {
        return [

            'Fullname',

            'Email',

            'Citizen Number / Passport Number',

            'Business Unit',

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

                '3171234567890001',

                'KPN Corporation',

                '17-01-2020',

                'Jl. Rasuna Said...',

            ],

            [

                'Jane Doe',

                'jane.doe@email.com',

                '3171234567890002',

                'Plantations',

                '20-05-2025',

                'Jl. Menteng Dalam...',

            ],

        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $event->sheet
                    ->getStyle('C2:C1000')
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER);

                for ($row = 2; $row <= 1000; $row++) {

                    $validation = $event->sheet
                        ->getCell("D{$row}")
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