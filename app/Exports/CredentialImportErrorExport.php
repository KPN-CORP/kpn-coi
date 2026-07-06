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

            'Name',

            'Email',

            'Citizenship Number',

            'Gender',

            'Address',

            'Error',

        ];
    }

    public function collection(): Collection
    {
        return $this->errors;
    }
}