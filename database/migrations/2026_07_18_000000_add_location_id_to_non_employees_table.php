<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference to the location the non-employee is based at, picked per
     * business unit.
     *
     * No foreign key: locations lives in the kpncorp database, not this one.
     * Both sit on the same MySQL instance in staging, so a cross-schema FK
     * would technically work today -- but the databases are expected to be
     * separated on the real server, which would break it. The link is soft and
     * validated in application code, like users.employee_id -> kpncorp.employees.
     *
     * Guarded because non_employees has no creating migration -- it exists only
     * in the deployed databases.
     */
    public function up(): void
    {
        if (! Schema::hasTable('non_employees')) {
            return;
        }

        if (Schema::hasColumn('non_employees', 'location_id')) {
            return;
        }

        Schema::table('non_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')
                ->nullable()
                ->after('group_company');

            $table->index('location_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('non_employees')) {
            return;
        }

        if (! Schema::hasColumn('non_employees', 'location_id')) {
            return;
        }

        Schema::table('non_employees', function (Blueprint $table) {
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
