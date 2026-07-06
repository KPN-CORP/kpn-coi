<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeclarationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'created_at' => $this->created_at,
            'responses_count' => $this->responses_count,
            'responses' => $this->responses
                ->mapWithKeys(fn ($response) => [
                    $response->question_key => $response->response_value,
                ]),
        ];
    }
}