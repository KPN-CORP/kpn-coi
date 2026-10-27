<?php

declare(strict_types=1);

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads the first sheet of an Import Center form file into heading-keyed
 * rows (employee_id_or_email, signed, signed_date, language). The template's
 * second sheet is instructions and is ignored. The rows are handed to
 * FormDeclarationImportService by the controller, which needs its result.
 */
class FormDeclarationImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithMultipleSheets
{
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
