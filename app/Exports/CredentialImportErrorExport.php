<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CredentialImportErrorExport implements
    FromCollection,
    WithHeadings
{
    public function __construct(
        protected Collection $errors
    ) {
    }

    public function headings(): array
    {
        return [

            'Row',

            'Fullname',

            'Email',

            'Citizenship Number / Passport Number',

            'Business Unit',

            'Date Of Join',

            'Permanent Address',

            'Error',

        ];
    }

    public function collection(): Collection
    {
        return $this->errors;
    }
}