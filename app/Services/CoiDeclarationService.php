<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeclarationStatus;
use App\Models\CoiDeclaration;
use App\Models\NonEmployeeUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CoiDeclarationService
{
    public function saveDraft(
        User|NonEmployeeUser $user,
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
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('period', $period)
                ->where('status', DeclarationStatus::Draft)
                ->first();

            if (! $declaration) {
                $declaration = CoiDeclaration::create([
                    'user_id' => $user->id,
                    'period' => $period,
                    'status' => DeclarationStatus::Draft,
                    'type' => $type,
                ]);
            }

            $this->syncResponses(
                declaration: $declaration,
                responses: $responses,
            );

            return $declaration;
        });
    }

    public function submit(
        User|NonEmployeeUser $user,
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
            $declaration = CoiDeclaration::create([
                'user_id' => $user->id,
                'period' => $period,
                'status' => DeclarationStatus::Submitted,
                'submitted_at' => now(),
                'type' => $type,
                'sign' => $user->name,
            ]);

            $this->syncResponses(
                declaration: $declaration,
                responses: $responses,
            );

            CoiDeclaration::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('period', $period)
                ->where('status', DeclarationStatus::Draft)
                ->delete();

            return $declaration;
        });
    }

    private function syncResponses(
        CoiDeclaration $declaration,
        array $responses,
    ): void {
        $declaration->responses()->delete();

        foreach ($responses as $key => $response) {
            $declaration->responses()->create([
                'question_key' => $key,
                'response_value' => $response,
            ]);
        }
    }
}