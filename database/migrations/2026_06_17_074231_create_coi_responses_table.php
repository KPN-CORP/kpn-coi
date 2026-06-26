<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coi_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coi_declaration_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('question_key');

            $table->json('response_value');

            $table->timestamps();

            $table->index('question_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coi_responses');
    }
};