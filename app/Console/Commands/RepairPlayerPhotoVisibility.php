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
        {--dry-run : Report changes without writing to S3 or the database}';

    protected $description = 'Make stored player photos public and replace malformed storage URLs with canonical S3 URLs';

    public function handle(): int
    {
        $query = Profile::query()
            ->whereNotNull('picture')
            ->where('picture', '!=', '');

        if ($playerId = trim((string) $this->option('player'))) {
            $query->where('user_id', $playerId);
        }

        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $repaired = 0;
        $failed = 0;

        $query->chunkById(100, function ($profiles) use (
            $dryRun,
            &$checked,
            &$repaired,
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
                        $this->warn("Missing {$profile->user_id}: {$key}");
                        $failed++;
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
        $this->line("Checked: {$checked}; repaired: {$repaired}; failed: {$failed}");

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
