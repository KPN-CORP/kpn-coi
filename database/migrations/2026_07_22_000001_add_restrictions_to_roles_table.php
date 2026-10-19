<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * roles.restrictions (the role's data restrictions: work_area_code,
 * group_company, contribution_level_code -- see DataScopeService) was added to
 * the existing databases by hand, so no migration created it and a database
 * built from migrations alone had no such column. That broke
 * 2026_07_23_000000_normalize_group_company_restrictions_on_roles, which reads
 * it.
 *
 * Dated just before that migration so it runs first on a fresh database.
 * Skips the column where it already exists (every database set up by hand),
 * where it is longtext holding JSON, as App\Models\Role casts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('roles', 'restrictions')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->longText('restrictions')->nullable();
        });
    }

    public function down(): void
    {
        // Leave the column alone: on the hand-built databases it predates this
        // migration and holds every role's restrictions.
    }
};
