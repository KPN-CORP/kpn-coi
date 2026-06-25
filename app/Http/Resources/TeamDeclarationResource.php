<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamDeclarationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'period' => $this->period,

            'status' => $this->status,

            'submitted_at' => $this->submitted_at,

            'type' => $this->type,

            'employee' => [
                'id' => $this->user?->id,
                'name' => $this->employee?->fullname,
                'ktp' => $this->employee?->ktp,
                'employee_id' => $this->employee?->employee_id,
                'designation' => $this->employee?->designation_name,
                'level' => $this->employee?->job_level,
                'business_unit' => $this->employee?->group_company,
            ],

            'has_conflict' => $this->responses
                ->contains(
                    fn ($response) =>
                        data_get(
                            $response->response_value,
                            'answer'
                        ) === true
                ),

            'questions' => $this->responses
                ->map(fn ($response) => [
                    'key' => $response->question_key,
                    'answer' => data_get(
                        $response->response_value,
                        'answer',
                        false
                    ),
                    'details' => data_get(
                        $response->response_value,
                        'details',
                        []
                    ),
                ]),
        ];
    }
}