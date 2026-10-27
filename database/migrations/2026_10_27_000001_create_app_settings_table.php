<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Small key/value settings an admin changes from the screens -- first of
 * all the Conflict of Interest menu order, which sits among the form types'
 * own sort_order (Master Settings -> Form Type). See App\Models\AppSetting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_settings')) {
            return;
        }

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
