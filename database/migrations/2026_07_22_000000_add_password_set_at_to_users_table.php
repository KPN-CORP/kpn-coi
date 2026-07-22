<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks when a user set their own password (via the self-service magic
     * link). Null means they never have — those users are still eligible for a
     * one-time reset link; once set, further resets go through an admin.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'password_set_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')
                ->nullable()
                ->after('password');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'password_set_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
