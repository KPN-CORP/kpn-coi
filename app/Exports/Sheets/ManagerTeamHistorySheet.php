<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Carbon;
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

                // submitted_at is stored in UTC (app timezone is UTC). The UI
                // renders it with new Date().getHours(), i.e. the viewer's local
                // timezone (Asia/Jakarta for KPN), so convert here too or the
                // export shows a time 7 hours behind the screen.
                $row['submitted_at']
                    ? Carbon::parse($row['submitted_at'], 'UTC')->setTimezone('Asia/Jakarta')->format('d-m-Y H:i:s')
                    : '-',

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
