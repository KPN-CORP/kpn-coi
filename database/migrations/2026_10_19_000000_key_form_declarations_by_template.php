<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per person per *template* instead of per form type and period.
 *
 * A signed template is locked, so changing its wording means duplicating it
 * and switching the copy on instead. Everyone who signed the old version then
 * signs the new one in the same period, and the old signature is kept (shown
 * as no longer valid) -- which the old per-period key could not hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_declarations', function (Blueprint $table) {
            // Lookups by person, form and period (pending list, history).
            $table->index(['user_id', 'type', 'form_type_id', 'period'], 'form_declarations_signer_period_index');
            $table->unique(['user_id', 'type', 'template_id'], 'form_declarations_template_signer_unique');
            $table->dropUnique('form_declarations_signer_unique');
        });
    }

    public function down(): void
    {
        // Fails while anyone holds two rows in one period, which is the point:
        // rolling back would have to drop re-signed history first.
        Schema::table('form_declarations', function (Blueprint $table) {
            $table->unique(['user_id', 'type', 'form_type_id', 'period'], 'form_declarations_signer_unique');
            $table->dropUnique('form_declarations_template_signer_unique');
            $table->dropIndex('form_declarations_signer_period_index');
        });
    }
};
