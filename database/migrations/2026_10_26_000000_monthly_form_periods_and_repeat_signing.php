<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forms move from yearly to monthly periods, and signing becomes repeatable.
 *
 * - `period` on form_templates and form_declarations becomes YYYYMM
 *   (202610 = October 2026) instead of a year. One number keeps every "per
 *   period" rule (one active template per unit, pending, reports) as it was.
 *   Existing yearly templates take the month of their publish date (January
 *   if that falls in another year); declarations follow their template.
 * - People may sign the same template again ("Add / Update"): each submit is
 *   a new row and the newest counts, so the one-row-per-template unique key
 *   goes (kept as a plain index for lookups).
 *
 * COI declarations keep their yearly period; only the form tables change.
 * Guarded so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['form_templates', 'form_declarations'] as $table) {
            if (Schema::getColumnType($table, 'period') !== 'year') {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger('period')->change());
        }

        DB::table('form_templates')
            ->where('period', '<', 10000)
            ->update(['period' => DB::raw('period * 100 + IF(YEAR(publish_at) = period, MONTH(publish_at), 1)')]);

        DB::statement(
            'UPDATE form_declarations d JOIN form_templates t ON t.id = d.template_id
             SET d.period = t.period WHERE d.period < 10000'
        );

        $indexes = collect(Schema::getIndexes('form_declarations'))->keyBy('name');

        if (! $indexes->has('form_declarations_template_signer_index')) {
            Schema::table('form_declarations', fn (Blueprint $t) => $t->index(['user_id', 'type', 'template_id'], 'form_declarations_template_signer_index'));
        }

        if ($indexes->has('form_declarations_template_signer_unique')) {
            Schema::table('form_declarations', fn (Blueprint $t) => $t->dropUnique('form_declarations_template_signer_unique'));
        }
    }

    public function down(): void
    {
        // Not reversible: months and repeated signatures cannot be folded
        // back into yearly, one-per-template rows without losing records.
    }
};
