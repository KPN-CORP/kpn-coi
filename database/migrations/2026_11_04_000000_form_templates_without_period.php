<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Templates no longer belong to a period: a template applies from its
 * publish date until it is switched off, and the year a declaration counts
 * for is stamped on the declaration when it is signed (or imported/uploaded
 * for a past year). So form_templates loses period_year/period_month and the
 * computed period; form_declarations keeps all three.
 *
 * Also: a declaration may now have no template -- a past year's signed
 * document uploaded by the employee (source = upload), whose file path is
 * kept in attachment_path on the private `local` disk.
 *
 * Guarded so it is safe to re-run and a no-op where already applied by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('form_templates', 'period')) {
            $existing = collect(Schema::getIndexes('form_templates'))->pluck('name')->all();

            // The form_type_id foreign key needs an index of its own before
            // the period index can go (2026_10_27_000000 usually made it).
            if (! in_array('form_templates_form_type_id_index', $existing, true)) {
                Schema::table('form_templates', fn (Blueprint $t) => $t->index('form_type_id', 'form_templates_form_type_id_index'));
            }

            Schema::table('form_templates', function (Blueprint $t) use ($existing) {
                if (in_array('form_templates_form_type_id_period_is_active_index', $existing, true)) {
                    $t->dropIndex('form_templates_form_type_id_period_is_active_index');
                }

                $t->dropColumn('period');
            });
        }

        Schema::table('form_templates', function (Blueprint $t) {
            foreach (['period_month', 'period_year'] as $column) {
                if (Schema::hasColumn('form_templates', $column)) {
                    $t->dropColumn($column);
                }
            }
        });

        if (! collect(Schema::getIndexes('form_templates'))->pluck('name')->contains('form_templates_form_type_id_is_active_index')) {
            Schema::table('form_templates', fn (Blueprint $t) => $t->index(['form_type_id', 'is_active'], 'form_templates_form_type_id_is_active_index'));
        }

        if (! Schema::hasColumn('form_declarations', 'attachment_path')) {
            Schema::table('form_declarations', function (Blueprint $t) {
                $t->string('attachment_path')->nullable()->after('imported_by');
            });
        }

        if (! $this->templateIdNullable()) {
            $foreignKeys = collect(Schema::getForeignKeys('form_declarations'))->pluck('name')->all();

            Schema::table('form_declarations', function (Blueprint $t) use ($foreignKeys) {
                if (in_array('form_declarations_template_id_foreign', $foreignKeys, true)) {
                    $t->dropForeign('form_declarations_template_id_foreign');
                }
            });

            Schema::table('form_declarations', function (Blueprint $t) {
                $t->unsignedBigInteger('template_id')->nullable()->change();
                $t->foreign('template_id')->references('id')->on('form_templates')->restrictOnDelete();
            });
        }
    }

    private function templateIdNullable(): bool
    {
        $column = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['form_declarations', 'template_id']
        );

        return $column !== null && strtoupper((string) $column->IS_NULLABLE) === 'YES';
    }

    public function down(): void
    {
        // Templates get a period back from their publish date. Uploads (no
        // template) must be removed by hand before template_id can be made
        // required again, so that part is left as is.
        if (! Schema::hasColumn('form_templates', 'period_year')) {
            Schema::table('form_templates', function (Blueprint $t) {
                $t->unsignedSmallInteger('period_year')->nullable()->after('form_type_id');
                $t->unsignedTinyInteger('period_month')->nullable()->after('period_year');
            });

            DB::table('form_templates')->update([
                'period_year' => DB::raw('YEAR(COALESCE(publish_at, created_at))'),
                'period_month' => DB::raw('MONTH(COALESCE(publish_at, created_at))'),
            ]);

            Schema::table('form_templates', function (Blueprint $t) {
                $t->unsignedSmallInteger('period_year')->nullable(false)->change();
                $t->unsignedTinyInteger('period_month')->nullable(false)->change();
                $t->unsignedInteger('period')->storedAs('period_year * 100 + period_month')->after('period_month');
                $t->index(['form_type_id', 'period', 'is_active'], 'form_templates_form_type_id_period_is_active_index');
            });
        }

        if (Schema::hasColumn('form_declarations', 'attachment_path')) {
            Schema::table('form_declarations', fn (Blueprint $t) => $t->dropColumn('attachment_path'));
        }
    }
};
