<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks signatures loaded through the Import Center (signed on paper or in
 * another system) apart from ones typed in the app, and who imported them.
 * imported_by is a kpncorp users.id -- a soft link, no foreign key across
 * the two databases. Guarded so it is a no-op where the columns were added
 * by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_declarations', function (Blueprint $table) {
            if (! Schema::hasColumn('form_declarations', 'source')) {
                $table->string('source', 20)->default('app')->after('user_agent');
            }

            if (! Schema::hasColumn('form_declarations', 'imported_by')) {
                $table->unsignedBigInteger('imported_by')->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_declarations', function (Blueprint $table) {
            foreach (['imported_by', 'source'] as $column) {
                if (Schema::hasColumn('form_declarations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
