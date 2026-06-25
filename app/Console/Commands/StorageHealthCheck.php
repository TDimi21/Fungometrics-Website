<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UploadS3File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One-shot check that player photos will save where they should and be publicly
 * readable. Run after any deploy / .env change:
 *
 *   php artisan storage:healthcheck
 *   php artisan storage:healthcheck --keep   (don't delete the test object)
 *
 * It reports the active disk, writes a tiny public test object to the players/
 * folder, prints the URL, and tells you whether that URL is reachable.
 */
class StorageHealthCheck extends Command
{
    protected $signature = 'storage:healthcheck {--keep : Leave the test object in place}';

    protected $description = 'Verify player-photo storage: active disk, write, public URL, and reachability';

    public function handle(): int
    {
        $default = (string) config('filesystems.default');
        $disk = $default === 's3' ? 's3' : 'public';

        $this->line("filesystems.default : <info>{$default}</info>");
        $this->line("photo upload disk   : <info>{$disk}</info>");

        if ($disk === 's3') {
            $this->line('s3.bucket           : '.(config('filesystems.disks.s3.bucket') ?: '<comment>(empty)</comment>'));
            $this->line('s3.region           : '.(config('filesystems.disks.s3.region') ?: '<comment>(empty)</comment>'));
            $this->line('s3.public_url       : '.(config('filesystems.disks.s3.public_url') ?: '<comment>(empty)</comment>'));
            $this->line('AWS key present     : '.(config('filesystems.disks.s3.key') ? 'yes' : '<comment>NO</comment>'));
        } else {
            $this->warn('Photos are NOT going to S3. Set FILESYSTEM_DISK=s3 if S3 is intended.');
            $this->line('public.url          : '.(config('filesystems.disks.public.url') ?: '<comment>(empty)</comment>'));
        }

        $key = 'players/_healthcheck-'.time().'.txt';
        try {
            $ok = Storage::disk($disk)->put($key, 'fmtrx-storage-healthcheck', ['visibility' => 'public']);
            if (!$ok) {
                $this->error("WRITE FAILED to disk [{$disk}] — check credentials/permissions.");
                return self::FAILURE;
            }
            Storage::disk($disk)->setVisibility($key, 'public');

            $url = $disk === 's3' ? UploadS3File::publicS3Url($key) : Storage::disk($disk)->url($key);
            $this->info("WRITE OK → {$url}");
            $this->line('Now curl that URL — you want HTTP 200. A 403/404 means the object isn\'t publicly readable.');

            if (!$this->option('keep')) {
                Storage::disk($disk)->delete($key);
                $this->line('(test object deleted; pass --keep to leave it)');
            }
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('STORAGE ERROR: '.$e->getMessage());
            $this->line('If disk is s3, this is almost always bad/missing AWS credentials, bucket, or region.');
            return self::FAILURE;
        }
    }
}
