<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A `person` is one real human, grouping together every identity they have
 * held in this app: a non-employee login, and one or more employee stints
 * across rehires (each rehire mints a fresh employee_id in the HRIS).
 *
 * This table owns nothing but the surrogate id. It exists so declaration
 * identities can point at a stable, app-generated grouping key instead of
 * racing on max(id)+1. It lives on the default (app) connection -- the
 * identities it groups span two databases, so no foreign key can reference it
 * from kpncorp anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
