<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link non_employees to its owning user explicitly.
     *
     * Previously the two rows were assumed to share the same primary key
     * (non_employees.id == users.id), which was never enforced and silently
     * resolved to the wrong person once the sequences drifted apart.
     *
     * The table is guarded because non_employees has no creating migration --
     * it exists only in the deployed databases.
     */
    public function up(): void
    {
        if (! Schema::hasTable('non_employees')) {
            return;
        }

        if (Schema::hasColumn('non_employees', 'user_id')) {
            return;
        }

        Schema::table('non_employees', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('non_employees')) {
            return;
        }

        if (! Schema::hasColumn('non_employees', 'user_id')) {
            return;
        }

        Schema::table('non_employees', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
