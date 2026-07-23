<?php

use App\Models\BusinessUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The group company data restriction used to be saved as
 * master_bisnisunits.kode_bisnis ("BU03"), but the tables it is matched
 * against -- kpncorp.employees and non_employees -- store the unit name
 * ("Cement"), so those roles could never match a single row. The role form now
 * offers names; this moves the roles already saved onto the same value space so
 * the form shows real chips instead of raw codes.
 *
 * DataScopeService still accepts either form, so a role missed here (a unit
 * absent from master_bisnisunits) keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite(
            BusinessUnit::query()
                ->pluck('nama_bisnis', 'kode_bisnis')
                ->all()
        );
    }

    public function down(): void
    {
        $this->rewrite(
            BusinessUnit::query()
                ->pluck('kode_bisnis', 'nama_bisnis')
                ->all()
        );
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function rewrite(array $translations): void
    {
        if ($translations === []) {
            return;
        }

        foreach (DB::table('roles')->whereNotNull('restrictions')->get() as $role) {

            $restrictions = json_decode($role->restrictions, true);

            $current = $restrictions['group_company'] ?? null;

            if (! is_array($restrictions) || ! is_array($current)) {
                continue;
            }

            $translated = array_values(
                array_unique(
                    array_map(
                        fn ($value) => $translations[(string) $value] ?? $value,
                        $current
                    )
                )
            );

            if ($translated === $current) {
                continue;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'restrictions' => json_encode([
                        ...$restrictions,
                        'group_company' => $translated,
                    ]),
                ]);
        }
    }
};
