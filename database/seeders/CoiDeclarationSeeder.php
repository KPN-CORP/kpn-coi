<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CoiDeclaration;
use App\Models\CoiResponse;
use App\Models\User;
use Illuminate\Database\Seeder;

class CoiDeclarationSeeder extends Seeder
{
    public function run(): void
    {
        $employee = User::query()
            ->where('email', 'employee@kpn.com')
            ->first();

        if (! $employee) {
            return;
        }

        $declaration = CoiDeclaration::query()
            ->updateOrCreate(
                [
                    'user_id' => $employee->id,
                    'period' => now()->year,
                ],
                [
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]
            );

        $responses = [
            'business_affiliation' => [
                'answer' => true,
                'details' => [
                    [
                        'entity_name' => 'PT Sample Vendor',
                        'relationship' => 'Owner',
                    ],
                ],
            ],

            'professional_relationship' => [
                'answer' => false,
                'details' => [],
            ],

            'equity_ownership' => [
                'answer' => false,
                'details' => [],
            ],

            'gifts_benefits' => [
                'answer' => true,
                'details' => [
                    [
                        'giver_name' => 'PT Vendor A',
                        'amount' => '500000',
                    ],
                ],
            ],

            'family_relationship' => [
                'answer' => false,
                'details' => [],
            ],

            'external_activities' => [
                'answer' => false,
                'details' => [],
            ],
        ];

        foreach ($responses as $questionKey => $responseValue) {
            CoiResponse::query()->updateOrCreate(
                [
                    'coi_declaration_id' => $declaration->id,
                    'question_key' => $questionKey,
                ],
                [
                    'response_value' => $responseValue,
                ]
            );
        }
    }
}