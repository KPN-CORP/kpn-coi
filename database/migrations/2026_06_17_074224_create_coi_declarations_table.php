<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coi_declarations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->year('period');

            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'revision_required',
            ])->default('draft');

            $table->timestamp('submitted_at')->nullable();

            $table->timestamp('reviewed_at')->nullable();

            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['user_id', 'period']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coi_declarations');
    }
};