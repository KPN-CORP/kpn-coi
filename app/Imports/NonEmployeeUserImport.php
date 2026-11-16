<?php

declare(strict_types=1);

namespace App\Imports;

use App\Services\CredentialImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class NonEmployeeUserImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithMultipleSheets
{
    /**
     * What CredentialImportService::import() reported, read back by the
     * controller once Excel::import() returns.
     *
     * @var array{errors: list<array<string, mixed>>, results: list<array<string, mixed>>}
     */
    public array $outcome = ['errors' => [], 'results' => []];

    public function __construct(
        protected CredentialImportService $service
    ) {}

    /**
     * Only the first sheet holds users. The template's "Companies" and
     * "Office Locations" sheets are reference lists; without this every
     * sheet would be imported as user rows.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(
        Collection $rows
    ): void {

        $this->outcome = $this->service->import($rows);

    }
}
