<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $declaration =
            $this->user?->declarations
                ?->first();

        $hasConflict =
            $declaration?->responses
                ?->contains(
                    fn ($response) =>
                        (bool) $response->answer
                ) ?? false;

        return [
            'id' => $this->id,

            'employee' => [
                'name' => $this->fullname,
                'employee_id' => $this->employee_id,
            ],

            'period' => request(
                'period',
                now()->year
            ),

            'status' => ! $declaration
                ? 'pending'
                : 'submitted',

            'has_conflict' => $hasConflict,

            'submitted_at' =>
                $declaration?->submitted_at,

            'declaration' => $declaration,
        ];
    }
}
