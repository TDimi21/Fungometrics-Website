<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlayerFitness;
use App\Services\AthleticPerformanceIndexService;
use Illuminate\Console\Command;
use Throwable;

class BackfillAthleticScores extends Command
{
    protected $signature = 'fmtrx:backfill-athletic-scores {--dry-run : List assessments that would be recomputed without saving} {--latest-only : Only recompute each player\'s newest assessment}';
    protected $description = 'Recompute athletic performance scores so overall_api_score / strength_score are populated on EVERY fitness row (canonical single source of truth for app + web). Use --latest-only for the old latest-per-player behaviour.';

    public function handle(AthleticPerformanceIndexService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $latestOnly = (bool) $this->option('latest-only');

        // Every assessment is scored so historical rows (the app time-series and
        // web trend) also carry the canonical strength_score — not just the latest.
        $query = PlayerFitness::query()
            ->orderBy('user_id')
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at');

        if ($query->count() === 0) {
            $this->info('No player fitness records found.');

            return self::SUCCESS;
        }

        $ok = 0;
        $skipped = 0;
        $failed = 0;
        $seenUsers = [];

        $this->info(($dryRun ? '[dry-run] ' : '') . 'Backfilling athletic scores' . ($latestOnly ? ' (latest per player)' : ' (all assessments)') . '...');

        $query->chunkById(200, function ($assessments) use ($service, $dryRun, $latestOnly, &$ok, &$skipped, &$failed, &$seenUsers): void {
            foreach ($assessments as $assessment) {
                $userId = (string) $assessment->user_id;

                if ($latestOnly) {
                    // Rows arrive newest-first per user; skip all but the first.
                    if (isset($seenUsers[$userId])) {
                        $skipped++;
                        continue;
                    }
                    $seenUsers[$userId] = true;
                }

                if ($dryRun) {
                    $this->line("  would recompute user {$userId} (assessment {$assessment->id})");
                    $ok++;
                    continue;
                }

                try {
                    // Mirrors the canonical overall_api_score + strength_score
                    // back onto this assessment's fitness row.
                    $service->calculateAndSave($assessment);
                    $ok++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->warn("  failed assessment {$assessment->id} (user {$userId}): {$e->getMessage()}");
                }
            }
        });

        $this->info("Done. recomputed={$ok} skipped={$skipped} failed={$failed}");

        return self::SUCCESS;
    }
}
