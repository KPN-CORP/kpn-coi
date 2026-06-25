<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'employee_id' => $this->employee_id,

            'name' => $this->name,

            'email' => $this->email,

            'citizen_number' => $this->citizen_number,

            'role' => $this->role,
        ];
    }
}