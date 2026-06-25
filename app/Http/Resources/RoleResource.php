<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'restrictions' => $this->restrictions,

            'permissions' => $this->permissions
                ->pluck('name')
                ->values(),

            'users_count' => $this->users_count,

            'users' => $this->whenLoaded(
                'users',
                fn () => $this->users->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
            ),
        ];
    }
}