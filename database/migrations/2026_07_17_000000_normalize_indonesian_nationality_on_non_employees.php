<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nationality now stores a country name ("Indonesia", "Malaysia") to match
     * the country list the form selects from. Older rows hold the demonym
     * "Indonesian"; normalize them so the column is uniform.
     *
     * Rows with a null/empty nationality are deliberately left alone: they are
     * ambiguous (imported users never had one set, and a create-path bug also
     * wrote empty for Indonesians), so guessing could label the wrong country.
     */
    public function up(): void
    {
        if (! Schema::hasTable('non_employees')) {
            return;
        }

        DB::table('non_employees')
            ->whereRaw('LOWER(TRIM(nationality)) = ?', ['indonesian'])
            ->update(['nationality' => 'Indonesia']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('non_employees')) {
            return;
        }

        DB::table('non_employees')
            ->whereRaw('LOWER(TRIM(nationality)) = ?', ['indonesia'])
            ->update(['nationality' => 'Indonesian']);
    }
};
