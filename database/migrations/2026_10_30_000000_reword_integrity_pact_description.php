<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The form type page is now a signing history (pending tasks moved to My
     * Commitment), so its description says so, matching the COI History page.
     * Only the text seeded by 2026_10_14_000000 is replaced: a description an
     * admin has since edited on Master Settings is left alone.
     */
    public function up(): void
    {
        $this->reword(
            ['Read and sign the integrity pact that applies to you.', 'Baca dan tanda tangani pakta integritas yang berlaku untuk Anda.'],
            ['Your Integrity Pact signing records.', 'Riwayat penandatanganan Pakta Integritas Anda.'],
        );
    }

    public function down(): void
    {
        $this->reword(
            ['Your Integrity Pact signing records.', 'Riwayat penandatanganan Pakta Integritas Anda.'],
            ['Read and sign the integrity pact that applies to you.', 'Baca dan tanda tangani pakta integritas yang berlaku untuk Anda.'],
        );
    }

    /**
     * @param  array{0: string, 1: string}  $from  [en, id]
     * @param  array{0: string, 1: string}  $to  [en, id]
     */
    private function reword(array $from, array $to): void
    {
        DB::table('form_types')
            ->where('code', 'integrity-pact')
            ->where('description_en', $from[0])
            ->update(['description_en' => $to[0]]);

        DB::table('form_types')
            ->where('code', 'integrity-pact')
            ->where('description_id', $from[1])
            ->update(['description_id' => $to[1]]);
    }
};
