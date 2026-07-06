<?php

declare(strict_types=1);

namespace App\Imports;

use App\Services\CredentialImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NonEmployeeUserImport implements
    ToCollection,
    WithHeadingRow
{
    public function __construct(
        protected CredentialImportService $service
    ) {
    }

    public function collection(
        Collection $rows
    ): void {

        $this->service->import($rows);

    }
}