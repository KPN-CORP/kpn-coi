<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A form's period is stored as two columns, `period_year` and `period_month`.
 *
 * `period` stays, but as a column the database computes from them
 * (period_year * 100 + period_month, e.g. 202610): every per-period rule,
 * filter, sort and index keeps working on one comparable value, and nothing
 * ever writes it. Applied to form_templates and form_declarations; COI is
 * untouched. Guarded so it is safe to re-run.
 */
return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> indexes that include `period`, per table */
    private const PERIOD_INDEXES = [
        'form_templates' => [
            'form_templates_form_type_id_period_is_active_index' => ['form_type_id', 'period', 'is_active'],
        ],
        'form_declarations' => [
            'form_declarations_form_type_id_period_status_index' => ['form_type_id', 'period', 'status'],
            'form_declarations_signer_period_index' => ['user_id', 'type', 'form_type_id', 'period'],
        ],
    ];

    public function up(): void
    {
        foreach (self::PERIOD_INDEXES as $table => $indexes) {
            if (! Schema::hasColumn($table, 'period_year')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedSmallInteger('period_year')->nullable()->after('period');
                    $t->unsignedTinyInteger('period_month')->nullable()->after('period_year');
                });

                // From the YYYYMM value (2026_10_26_000000); a stray plain year
                // becomes January of it.
                DB::table($table)->update([
                    'period_year' => DB::raw('IF(period >= 10000, period DIV 100, period)'),
                    'period_month' => DB::raw('IF(period >= 10000, period MOD 100, 1)'),
                ]);

                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedSmallInteger('period_year')->nullable(false)->change();
                    $t->unsignedTinyInteger('period_month')->nullable(false)->change();
                });
            }

            if ($this->isComputed($table)) {
                continue;
            }

            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();

            // The form_type_id foreign key leans on the period index; give it
            // its own first, or MySQL refuses to drop that index.
            $ownIndex = "{$table}_form_type_id_index";

            if (! in_array($ownIndex, $existing, true)) {
                Schema::table($table, fn (Blueprint $t) => $t->index('form_type_id', $ownIndex));
            }

            Schema::table($table, function (Blueprint $t) use ($indexes, $existing) {
                foreach (array_keys($indexes) as $name) {
                    if (in_array($name, $existing, true)) {
                        $t->dropIndex($name);
                    }
                }

                $t->dropColumn('period');
            });

            Schema::table($table, function (Blueprint $t) use ($indexes) {
                $t->unsignedInteger('period')
                    ->storedAs('period_year * 100 + period_month')
                    ->after('period_month');

                foreach ($indexes as $name => $columns) {
                    $t->index($columns, $name);
                }
            });
        }
    }

    private function isComputed(string $table): bool
    {
        $column = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'period']
        );

        return $column !== null && str_contains(strtoupper((string) $column->EXTRA), 'GENERATED');
    }

    public function down(): void
    {
        // Not reversible without rebuilding the indexes by hand: the year and
        // month columns are now the source of the period.
    }
};
