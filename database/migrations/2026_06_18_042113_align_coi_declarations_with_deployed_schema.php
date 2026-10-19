<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings coi_declarations, as the migrations above build it, in line with the
 * deployed databases, which were changed by hand:
 *
 * - `type` (employee | non_employee) says which database user_id points at
 *   (see CLAUDE.md "Declaration identity"); `version` and `sign` are read by
 *   the declaration form and PDF.
 * - The foreign key user_id -> users goes: employee declarations point at
 *   kpncorp.users, which a key on the local users table would reject.
 * - The unique (user_id, period) key goes: an employee and a non-employee can
 *   share a user_id number, and submitting again adds a row in the same
 *   period.
 *
 * Every step checks first, so it is a no-op on the deployed databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('coi_declarations'))
            ->filter(fn (array $key) => $key['columns'] === ['user_id']);

        foreach ($foreignKeys as $key) {
            Schema::table('coi_declarations', fn (Blueprint $table) => $table->dropForeign($key['name']));
        }

        $uniqueUserPeriod = collect(Schema::getIndexes('coi_declarations'))
            ->first(fn (array $index) => $index['unique'] && ! $index['primary'] && $index['columns'] === ['user_id', 'period']);

        if ($uniqueUserPeriod) {
            Schema::table('coi_declarations', fn (Blueprint $table) => $table->dropUnique($uniqueUserPeriod['name']));
        }

        Schema::table('coi_declarations', function (Blueprint $table) {
            if (! Schema::hasColumn('coi_declarations', 'version')) {
                $table->integer('version')->nullable()->default(1)->after('admin_notes');
            }

            if (! Schema::hasColumn('coi_declarations', 'type')) {
                $table->enum('type', ['employee', 'non_employee'])->nullable()->after('deleted_at');
            }

            if (! Schema::hasColumn('coi_declarations', 'sign')) {
                $table->text('sign')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        // Not reversible on the deployed databases, where these columns hold
        // live data and the dropped keys never existed.
    }
};
