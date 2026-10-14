<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_types', function (Blueprint $table) {
            $table->id();
            // URL slug for the employee page (/employee/forms/{code}); fixed
            // once created so bookmarks and links keep working.
            $table->string('code', 50)->unique();
            $table->string('name_en');
            $table->string('name_id');
            $table->text('description_en')->nullable();
            $table->text('description_id')->nullable();
            // Font Awesome class for the employee menu item.
            $table->string('icon', 60)->default('fa-solid fa-file-signature');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // The first form type. Seeded here rather than in a seeder because the
        // cPanel deploy only ever runs migrations (by hand), never seeders.
        DB::table('form_types')->insert([
            'code' => 'integrity-pact',
            'name_en' => 'Integrity Pact',
            'name_id' => 'Pakta Integritas',
            'description_en' => 'Read and sign the integrity pact that applies to you.',
            'description_id' => 'Baca dan tanda tangani pakta integritas yang berlaku untuk Anda.',
            'icon' => 'fa-solid fa-file-signature',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('form_types');
    }
};
