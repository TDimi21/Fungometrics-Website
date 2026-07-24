<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupDataHubTemporaryFiles extends Command
{
    protected $signature = 'data-hub:cleanup-temporary-files';

    protected $description = 'Delete abandoned private Data Hub inspection files.';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $deleted = 0;
        foreach ($disk->files('data-hub/tmp') as $file) {
            if ($disk->lastModified($file) < now()->subHour()->timestamp && $disk->delete($file)) {
                ++$deleted;
            }
        }
        $this->info("Deleted {$deleted} abandoned Data Hub temporary file(s).");

        return self::SUCCESS;
    }
}
