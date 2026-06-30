<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlayerFitness;
use App\Services\AthleticPerformanceIndexService;
use Illuminate\Console\Command;
use Throwable;

class BackfillAthleticScores extends Command
{
    protected $signature = 'fmtrx:backfill-athletic-scores {--dry-run : List players that would be recomputed without saving}';
    protected $description = 'Recompute each player\'s athletic performance score from their latest assessment so overall_api_score / strength_score are populated on the fitness row.';

    public function handle(AthleticPerformanceIndexService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // One latest assessment per user (newest by fitness_date, then created_at).
        $userIds = PlayerFitness::query()
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No player fitness records found.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Backfilling athletic scores for {$userIds->count()} player(s)...");

        $ok = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($userIds as $userId) {
            $latest = PlayerFitness::query()
                ->where('user_id', $userId)
                ->orderByDesc('fitness_date')
                ->orderByDesc('created_at')
                ->first();

            if (! $latest) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  would recompute user {$userId} (assessment {$latest->id})");
                $ok++;
                continue;
            }

            try {
                // calculateAndSave coalesces history and mirrors the canonical
                // overall_api_score + strength_score back onto $latest.
                $service->calculateAndSave($latest);
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("  failed user {$userId}: {$e->getMessage()}");
            }
        }

        $this->info("Done. recomputed={$ok} skipped={$skipped} failed={$failed}");

        return self::SUCCESS;
    }
}
