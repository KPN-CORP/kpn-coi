<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Location;
use App\Services\DataScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CredentialTemplateExport implements FromCollection, WithEvents, WithHeadings
{
    /** Last row of the import sheet that gets the dropdowns. */
    private const LAST_ROW = 1000;

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

            // One of the Business Unit's office areas (see the Office
            // Locations sheet).
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

                '10000000001',

                '081234567890',

                'Indonesia',

                '3171234567890001',

                'KPN Corporation',

                'PT TH Indo Plantations',

                $this->sampleOffice('KPN Corporation'),

                '17-01-2020',

                'Jl. Rasuna Said...',

            ],

            [

                'Jane Doe',

                'jane.doe@email.com',

                '10000000002',

                '081298765432',

                'Malaysia',

                'A12345678',

                'Plantations',

                'PT Agrinusa Persada Mulia',

                $this->sampleOffice('Plantations'),

                '20-05-2025',

                'Jl. Menteng Dalam...',

            ],

        ]);
    }

    /**
     * A real office location of $businessUnit for the sample rows, so they
     * pass the import's Office Location check if left in.
     */
    private function sampleOffice(string $businessUnit): string
    {
        return (string) Location::officeAreaOptions()
            ->firstWhere('business_unit', $businessUnit)['office_area'] ?? '';
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
                        ->getStyle("{$column}2:{$column}".self::LAST_ROW)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                }

                // The text format only applies to what is typed later: the
                // sample rows were already written, and digit-only samples
                // (NIK, KTP) went in as numbers -- a 16-digit KTP then loses
                // its last digit to Excel's 15-digit precision. Store them as
                // text, the way an admin's entries will be.
                $sheet = $event->sheet->getDelegate();
                $samples = $this->collection();

                foreach ($samples->keys() as $index) {
                    foreach (['C' => 2, 'D' => 3, 'F' => 5] as $column => $field) {
                        $sheet->getCell($column.($index + 2))->setValueExplicit(
                            (string) $samples[$index][$field],
                            DataType::TYPE_STRING
                        );
                    }
                }

                $this->addReferenceSheets($event->sheet->getDelegate());
            },
        ];
    }

    /**
     * Gives the template the same choices as the Add/Edit Credential form:
     * Business Unit from the units the admin may assign, then Company Name
     * and Office Location limited to that unit.
     *
     * The choices live on two reference sheets, "Companies" and "Office
     * Locations", grouped by Business Unit -- 100+ names are far past the 255
     * characters an inline list validation allows, and the grouping is what
     * lets one OFFSET/MATCH formula per cell pick out the row's unit's block.
     * KPN Corporation, the group head, owns every company, so its block lists
     * all of them (DataScopeService::companyOptionsFor(), which the import
     * validates against too).
     */
    private function addReferenceSheets(Worksheet $main): void
    {
        $dataScope = app(DataScopeService::class);
        $viewer = Auth::guard('web')->user();

        $businessUnits = $dataScope->businessUnitOptions($viewer);

        $companies = $businessUnits
            ->flatMap(fn (string $unit) => $dataScope->companyOptionsFor($viewer, $unit)
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (array $company) => [$unit, $company['name'], $company['code']]))
            ->values();

        $locations = Location::officeAreaOptions()->groupBy('business_unit');

        $offices = $businessUnits
            ->flatMap(fn (string $unit) => collect($locations->get($unit, []))
                ->pluck('office_area')
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (string $area) => [$unit, $area]))
            ->values();

        $this->referenceSheet($main, 'Companies', ['Business Unit', 'Company Name', 'Code'], $companies);
        $this->referenceSheet($main, 'Office Locations', ['Business Unit', 'Office Location'], $offices);

        // Opening the file should land on the sheet to fill in.
        $spreadsheet = $main->getParent();
        $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($main));

        for ($row = 2; $row <= self::LAST_ROW; $row++) {
            if ($businessUnits->isNotEmpty()) {
                $this->listValidation($main, "G{$row}", '"'.$businessUnits->implode(',').'"');
            }

            if ($companies->isNotEmpty()) {
                $this->listValidation($main, "H{$row}", $this->dependentList('Companies', $row));
            }

            if ($offices->isNotEmpty()) {
                $this->listValidation($main, "I{$row}", $this->dependentList('Office Locations', $row));
            }
        }
    }

    private function referenceSheet(Worksheet $main, string $title, array $headings, Collection $rows): void
    {
        $sheet = $main->getParent()->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headings, null, 'A1');

        if ($rows->isNotEmpty()) {
            $sheet->fromArray($rows->all(), null, 'A2');
        }

        $lastColumn = chr(ord('A') + count($headings) - 1);

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}".max(2, $rows->count() + 1));

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Column B of $sheet, limited to the block whose Business Unit (column A)
     * equals this row's Business Unit (column G). Relies on the sheet being
     * grouped by unit. With no unit picked MATCH fails and Excel offers
     * nothing -- as the form offers nothing before a unit is chosen.
     */
    private function dependentList(string $sheet, int $row): string
    {
        $units = "'{$sheet}'!\$A:\$A";

        return "OFFSET('{$sheet}'!\$B\$1,MATCH(\$G{$row},{$units},0)-1,0,COUNTIF({$units},\$G{$row}),1)";
    }

    private function listValidation(Worksheet $sheet, string $cell, string $formula): void
    {
        $validation = $sheet->getCell($cell)->getDataValidation();

        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Invalid value');
        $validation->setError('Pick a value from the list. Choose the Business Unit first.');
        $validation->setFormula1($formula);
    }
}
