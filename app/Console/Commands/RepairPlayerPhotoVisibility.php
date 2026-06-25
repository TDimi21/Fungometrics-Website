<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Profile;
use App\Services\UploadS3File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RepairPlayerPhotoVisibility extends Command
{
    protected $signature = 'players:repair-photo-visibility
        {--player= : Repair one player/user UUID}
        {--prune-missing : Null out picture references whose S3 object no longer exists}
        {--dry-run : Report changes without writing to S3 or the database}';

    protected $description = 'Make stored player photos public, replace malformed storage URLs with canonical S3 URLs, and optionally clear references to missing files';

    public function handle(): int
    {
        $query = Profile::query()
            ->whereNotNull('picture')
            ->where('picture', '!=', '');

        if ($playerId = trim((string) $this->option('player'))) {
            $query->where('user_id', $playerId);
        }

        $dryRun = (bool) $this->option('dry-run');
        $pruneMissing = (bool) $this->option('prune-missing');
        $checked = 0;
        $repaired = 0;
        $pruned = 0;
        $failed = 0;

        $query->chunkById(100, function ($profiles) use (
            $dryRun,
            $pruneMissing,
            &$checked,
            &$repaired,
            &$pruned,
            &$failed,
        ): void {
            foreach ($profiles as $profile) {
                $checked++;
                $key = $this->playerPhotoKey((string) $profile->picture);
                if ($key === null) {
                    $this->line("Skipped {$profile->user_id}: unsupported photo URL");
                    continue;
                }

                try {
                    if (!Storage::disk('s3')->exists($key)) {
                        // The DB points at an object that was never persisted (legacy
                        // uploads that returned a URL even when the S3 write failed).
                        // Optionally clear it so the app/web fall back to a clean
                        // placeholder instead of a broken image.
                        if ($pruneMissing) {
                            if (!$dryRun) {
                                $profile->forceFill(['picture' => null])->save();
                            }
                            $this->warn(($dryRun ? 'Would prune ' : 'Pruned ')."{$profile->user_id}: {$key} (missing)");
                            $pruned++;
                        } else {
                            $this->warn("Missing {$profile->user_id}: {$key}");
                            $failed++;
                        }
                        continue;
                    }

                    $canonicalUrl = UploadS3File::publicS3Url($key);
                    if (!$dryRun) {
                        Storage::disk('s3')->setVisibility($key, 'public');
                        $profile->forceFill(['picture' => $canonicalUrl])->save();
                    }

                    $this->info(
                        ($dryRun ? 'Would repair ' : 'Repaired ').
                        "{$profile->user_id}: {$canonicalUrl}",
                    );
                    $repaired++;
                } catch (Throwable $exception) {
                    $this->error("Failed {$profile->user_id}: {$exception->getMessage()}");
                    $failed++;
                }
            }
        }, 'id');

        $this->newLine();
        $this->line("Checked: {$checked}; repaired: {$repaired}; pruned: {$pruned}; failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function playerPhotoKey(string $url): ?string
    {
        $path = parse_url(trim($url), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        if (!preg_match('~(?:^|/)players/(.+)$~i', $path, $matches)) {
            return null;
        }

        $filename = ltrim((string) ($matches[1] ?? ''), '/');

        return $filename !== '' ? 'players/'.$filename : null;
    }
}
