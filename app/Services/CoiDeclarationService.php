<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeclarationStatus;
use App\Models\CoiDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CoiDeclarationService
{
    public function saveDraft(
        User $user,
        array $responses,
        int $period,
        string $type
    ): CoiDeclaration {
        return DB::transaction(function () use (
            $user,
            $responses,
            $period,
            $type
        ) {
            $declaration = CoiDeclaration::query()
                ->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'period' => $period,
                    ],
                    [
                        'status' => DeclarationStatus::Draft,
                        'type' => $type,
                    ]
                );

            $declaration->update([
                'type' => $type,
            ]);

            $declaration->responses()->delete();

            foreach ($responses as $key => $response) {
                $declaration->responses()->create([
                    'question_key' => $key,
                    'response_value' => $response,
                ]);
            }

            return $declaration;
        });
    }

    public function submit(
        User $user,
        array $responses,
        int $period,
        string $type
    ): CoiDeclaration {
        $declaration = $this->saveDraft(
            user: $user,
            responses: $responses,
            period: $period,
            type: $type,
        );

        $declaration->update([
            'status' => DeclarationStatus::Submitted,
            'submitted_at' => now(),
            'type' => $type,
        ]);

        return $declaration;
    }

}