<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeclarationIdentity;
use App\Models\Person;
use App\Services\IdentityLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Materialise the person groupings for declarations that already exist.
 *
 * This reads coi_declarations to find every distinct declarant (user_id, type),
 * resolves each one's connected component (rehire chain + non-employee
 * conversions) via IdentityLinkService, and writes the grouping into
 * declaration_identities. It never writes, moves or deletes a coi_declarations
 * row -- a baseline checksum of that table is asserted unchanged at the end.
 *
 * Safe to run repeatedly: every write is an upsert keyed on (type, user_id).
 * Run --dry-run first to review the orphan/ambiguity report before committing.
 */
class BackfillIdentities extends Command
{
    protected $signature = 'identities:backfill
        {--dry-run : Resolve and report only; write nothing}
        {--fresh : Wipe declaration_identities + persons first (never coi_declarations)}';

    protected $description = 'Backfill person groupings for existing declarations (rehires + conversions).';

    public function handle(IdentityLinkService $links): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');

        if ($dryRun) {
            $this->warn('DRY RUN: no rows will be written.');
        }

        // Proof the declaration table is never mutated by this command.
        $baseline = $this->declarationChecksum();

        if ($fresh && ! $dryRun) {
            if (! $this->confirm('Wipe ALL declaration_identities and persons, then rebuild? (coi_declarations is untouched)', false)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
            DeclarationIdentity::query()->delete();
            Person::query()->delete();
            $this->info('Cleared declaration_identities + persons.');
        }

        // Distinct declarants straight off the table so soft-deleted rows and
        // rows whose account no longer exists are still grouped.
        $seeds = DB::connection('mysql')
            ->table('coi_declarations')
            ->select('user_id', 'type')
            ->distinct()
            ->get();

        $this->info("Found {$seeds->count()} distinct declarant identities.");

        $processed = [];
        $orphans = [];
        $personsTouched = [];
        $groups = 0;
        $identitiesResolved = 0;

        $bar = $this->output->createProgressBar($seeds->count());
        $bar->start();

        foreach ($seeds as $seed) {
            $seedKey = $seed->type.':'.(int) $seed->user_id;

            // A component resolved through an earlier seed already covers this
            // one -- skip the redundant walk and the duplicate group.
            if (isset($processed[$seedKey])) {
                $bar->advance();

                continue;
            }

            $resolved = $links->resolveFromSeed($seed->type, (int) $seed->user_id);

            foreach ($resolved['identities'] as $identity) {
                $processed[$identity['type'].':'.$identity['user_id']] = true;
                $identitiesResolved++;
            }

            foreach ($resolved['orphans'] as $orphan) {
                $orphans[$orphan] = ($orphans[$orphan] ?? 0) + 1;
            }

            if (! $dryRun) {
                $person = $links->persistGroup($resolved['identities'], $resolved['primary']);
                if ($person !== null) {
                    $personsTouched[(int) $person->id] = true;
                }
            }

            $groups++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // The table must be byte-for-byte as we found it.
        $after = $this->declarationChecksum();
        if ($after !== $baseline) {
            $this->error('coi_declarations checksum changed -- aborting. This should never happen.');
            $this->line('before: '.json_encode($baseline));
            $this->line('after:  '.json_encode($after));

            return self::FAILURE;
        }
        $this->info('coi_declarations checksum unchanged (no declaration data touched).');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Distinct declarants', (string) $seeds->count()],
                ['Groups resolved', (string) $groups],
                ['Identities resolved', (string) $identitiesResolved],
                ['Persons written', $dryRun ? '0 (dry run)' : (string) count($personsTouched)],
                ['Orphan employee_ids', (string) count($orphans)],
            ]
        );

        if ($orphans !== []) {
            $this->warn('Orphan employee_ids referenced by a rehire chain but absent from employees:');
            foreach (array_slice(array_keys($orphans), 0, 20) as $orphan) {
                $this->line("  - {$orphan}");
            }
            if (count($orphans) > 20) {
                $this->line('  ... and '.(count($orphans) - 20).' more.');
            }
            $this->writeReport($orphans);
        }

        if (! $dryRun) {
            $badPrimary = DB::connection('mysql')
                ->table('declaration_identities')
                ->select('person_id')
                ->groupBy('person_id')
                ->havingRaw('SUM(is_primary) <> 1')
                ->count();

            if ($badPrimary > 0) {
                $this->warn("{$badPrimary} person(s) do not have exactly one primary identity -- review.");
            } else {
                $this->info('Every person has exactly one primary identity.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Count + id-sum per type, read straight off the table (bypassing the
     * soft-delete scope) so any accidental write would move the numbers.
     *
     * @return array<string, array{count: int, sum: int}>
     */
    private function declarationChecksum(): array
    {
        return DB::connection('mysql')
            ->table('coi_declarations')
            ->selectRaw('type, COUNT(*) as cnt, COALESCE(SUM(id), 0) as sum_id')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->type => [
                    'count' => (int) $row->cnt,
                    'sum' => (int) $row->sum_id,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, int>  $orphans
     */
    private function writeReport(array $orphans): void
    {
        $path = storage_path('app/identity_backfill_orphans_'.now()->format('Ymd_His').'.json');
        file_put_contents($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'orphans' => $orphans,
        ], JSON_PRETTY_PRINT));

        $this->line("Full orphan report written to: {$path}");
    }
}
