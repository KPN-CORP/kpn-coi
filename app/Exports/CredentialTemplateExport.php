<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\DataScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CredentialTemplateExport implements FromCollection, WithEvents, WithHeadings
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

            // Read back as the company_name slug; one of the Business Unit's
            // companies (see the Companies sheet), by name or code.
            'Company Name',

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

                'PT TH Indo Plantations',

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

                'PT Agrinusa Persada Mulia',

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
                        '"'.implode(',', $this->businessUnits).'"'
                    );
                }

                $this->addCompanies($event);
            },
        ];
    }

    /**
     * A "Companies" reference sheet (Business Unit, Company Name, Code) and a
     * Company Name dropdown on column H fed from it. 100+ names are far past
     * the 255 characters an inline list validation allows, hence the sheet.
     * The dropdown lists every company; which unit a company belongs to is
     * checked on import (KPN Corporation may take any of them).
     */
    private function addCompanies(AfterSheet $event): void
    {
        $companies = app(DataScopeService::class)
            ->contributionLevelOptions(Auth::guard('web')->user())
            ->sortBy([['business_unit', 'asc'], ['name', 'asc']])
            ->values();

        $main = $event->sheet->getDelegate();
        $spreadsheet = $main->getParent();

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Companies');
        $sheet->fromArray(['Business Unit', 'Company Name', 'Code'], null, 'A1');
        $sheet->fromArray(
            $companies->map(fn (array $company) => [
                $company['business_unit'],
                $company['name'],
                $company['code'],
            ])->all(),
            null,
            'A2'
        );
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Opening the file should land on the sheet to fill in.
        $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($main));

        if ($companies->isEmpty()) {
            return;
        }

        $range = '\'Companies\'!$B$2:$B$'.($companies->count() + 1);

        for ($row = 2; $row <= 1000; $row++) {
            $validation = $main->getCell("H{$row}")->getDataValidation();

            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setFormula1($range);
        }
    }
}
