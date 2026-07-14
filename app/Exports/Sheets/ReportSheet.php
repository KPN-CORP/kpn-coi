<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSheet implements FromArray, WithTitle
{
    public function __construct(
        protected Collection $data
    ) {}

    public function title(): string
    {
        return 'Team History';
    }

    public function array(): array
    {
        $questions = collect(
            config('coi.questions')
        );

        $rows = [];

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        $headers = [

            'Employee ID',

            'Employee Name',
            
            'Period',

            'Status',

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

            $record = [

                $row['employee_id'],

                $row['name'],

                $row['period'],

                ucfirst($row['status']),

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