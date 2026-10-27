<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Sheets\FormDeclarationImportInstructionsSheet;
use App\Exports\Sheets\FormDeclarationImportSheet;
use App\Models\FormType;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The Import Center template for a form type: an empty data sheet (read by
 * FormDeclarationImport) and an instructions sheet. Examples live only on
 * the instructions sheet so they can never be imported by accident.
 */
class FormDeclarationImportTemplateExport implements WithMultipleSheets
{
    public function __construct(
        protected FormType $formType
    ) {}

    public function sheets(): array
    {
        return [
            new FormDeclarationImportSheet,
            new FormDeclarationImportInstructionsSheet($this->formType),
        ];
    }
}
