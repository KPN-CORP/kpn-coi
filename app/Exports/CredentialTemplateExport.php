<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CredentialTemplateExport implements
    FromCollection,
    WithHeadings
{
    public function headings(): array
    {
        return [

            'name',

            'email',

            'citizen_number',

            'gender',

            'address',

        ];
    }

    public function collection(): Collection
    {
        return collect([
            [

                'John Doe',

                'john.doe@email.com',

                '3171234567890001',

                'Male',

                'Jakarta',

            ],

            [

                'Jane Doe',

                'jane.doe@email.com',

                '3171234567890002',

                'Female',

                'Bandung',

            ],

        ]);
    }
}