<?php

namespace App\Exports\Sheets;

use App\Models\CoiDeclaration;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSheet implements FromArray, WithTitle
{
    public function __construct(
        protected Collection $data,
        protected ?int $period = null
    ) {}

    public function title(): string
    {
        return 'Team History';
    }

    public function array(): array
    {
        // 2025 is the historical import: submissions were scanned attachments,
        // not answered questionnaires, so the per-question columns are
        // meaningless and "submitted" means "uploaded an attachment".
        $isLegacyPeriod = $this->period === CoiDeclaration::LEGACY_PERIOD;

        $questions = $isLegacyPeriod
            ? collect()
            : collect(config('coi.questions'));

        $rows = [];

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        $headers = [

            'Employee ID',

            'Employee Name',
            'Business Unit',
            'Employee Status',
            'Designation',
            'Join Date',

            'Declaration Period',

            'Declaration Status',

            $isLegacyPeriod
                ? 'Attachment Status'
                : 'Form Status',

            'Submitted At',

        ];

        foreach ($questions as $question) {

            $headers[] = $question['title']['en'];

        }

        $rows[] = $headers;

        /*
        |--------------------------------------------------------------------------
        | Data
        |--------------------------------------------------------------------------
        */

        foreach ($this->data as $row) {

            $responseMap = collect(
                data_get(
                    $row,
                    'declaration.responses',
                    []
                )
            )->keyBy('question_key');

            if ($isLegacyPeriod) {

                $attachment = data_get(
                    $responseMap->get(
                        CoiDeclaration::LEGACY_CONFLICT_KEY
                    ),
                    'response_value.attachment'
                );

                $formStatus = filled($attachment)
                    ? 'Submitted'
                    : 'Not Submitted';

            } else {

                $formStatus = $row['status'] === 'submitted'
                    ? 'Submitted'
                    : 'Not Submitted';

            }

            $record = [

                $row['employee_id'],

                $row['name'],
                $row['group_company'],
                $row['employee_status'],
                $row['designation'],
                $row['date_of_joining'],

                $row['period'],

                // No submission means there is nothing to judge, so the
                // declaration status stays empty rather than claiming Clear.
                $row['status'] === 'submitted'
                    ? ($row['has_conflict'] ? 'Has Conflict' : 'Clear')
                    : '-',

                $formStatus,

                $row['submitted_at'] ?? '-',

            ];

            foreach ($questions as $question) {

                $response = $responseMap->get(
                    $question['key']
                );

                $record[] = data_get(
                    $response,
                    'response_value.answer',
                    false
                )
                    ? '✓'
                    : '-';
            }

            $rows[] = $record;
        }

        return $rows;
    }
}
