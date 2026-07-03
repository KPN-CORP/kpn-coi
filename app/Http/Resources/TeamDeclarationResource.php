<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NonEmployeeUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamDeclarationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->type === 'employee'
            ? User::find($this->user_id)
            : NonEmployeeUser::find($this->user_id);

        return [

            'id' => $this->id,

            'period' => $this->period,

            'type' => $this->type,

            'status' => $this->status->value,

            'submitted_at' => $this->submitted_at,

            'employee' => [

                'id' => $owner?->id,

                'name' => $this->type === 'employee'
                    ? $owner?->employee->fullname
                    : $owner?->name,

                'employee_id' => $this->type === 'employee'
                    ? $owner?->employee_id
                    : null,

                'ktp' => $this->type === 'employee'
                    ? $owner?->employee->ktp
                    : $owner?->employee->ktp,

                'designation' => $this->type === 'employee'
                    ? $owner?->designation_name
                    : null,

                'level' => $this->type === 'employee'
                    ? $owner?->job_level
                    : null,

                'business_unit' => $this->type === 'employee'
                    ? $owner?->group_company
                    : null,

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