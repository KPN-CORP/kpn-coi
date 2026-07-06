<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ManagerTeamHistorySheet implements FromArray, WithTitle
{
    public function __construct(
        protected array $data
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

            'Designation',

            'Business Unit',

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

        foreach ($this->data['declarations'] as $row) {

            $responseMap = collect(
                $row['declaration']?->responses ?? []
            )->keyBy('question_key');

            $record = [

                $row['employee_id'],

                $row['name'],

                $row['designation'],

                $row['business_unit'],

                $row['period'],

                ucfirst($row['status']),

                $row['submitted_at'],

            ];

            foreach ($questions as $question) {

                $response = $responseMap->get(
                    $question['key']
                );

                $record[] = data_get(
                    $response,
                    'response_value.answer'
                )
                    ? '✓'
                    : '-';
            }

            $rows[] = $record;
        }

        return $rows;
    }
}