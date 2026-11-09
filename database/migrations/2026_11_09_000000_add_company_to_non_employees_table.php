<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company a non-employee works for, picked on the Credentials form
     * from kpncorp.companies: contribution_level_code (the code the
     * Contribution Level restriction and filters match on) plus company_name
     * (the readable name), the same pair kpncorp.employees carries.
     *
     * Both columns already exist on the deployed databases and on a fresh one
     * (2026_07_15_000000_create_non_employees_table); guarded so it is a no-op
     * there and only fills a gap on a hand-built table that lacks them.
     */
    public function up(): void
    {
        if (! Schema::hasTable('non_employees')) {
            return;
        }

        if (! Schema::hasColumn('non_employees', 'company_name')) {
            Schema::table('non_employees', function (Blueprint $table) {
                $table->string('company_name', 50)->nullable()->after('group_company');
            });
        }

        if (! Schema::hasColumn('non_employees', 'contribution_level_code')) {
            Schema::table('non_employees', function (Blueprint $table) {
                $table->string('contribution_level_code')->nullable()->after('company_name');
            });
        }
    }

    public function down(): void
    {
        // Nothing to undo: the columns predate this migration everywhere it
        // matters, and dropping them would lose data written by other code.
    }
};
