<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import Center history: one row per upload -- who, when, the stored file and
 * the outcome of every spreadsheet row. user_id is a kpncorp users.id (admins
 * are employees), a soft link with no foreign key across the two databases;
 * the name is kept too so the history still reads after the account goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('import_logs')) {
            return;
        }

        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->unsignedBigInteger('form_type_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('locale', 2)->default('en');
            $table->string('status', 30);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('already_signed_count')->default(0);
            $table->unsignedInteger('not_signed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('message')->nullable();
            $table->longText('results')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
