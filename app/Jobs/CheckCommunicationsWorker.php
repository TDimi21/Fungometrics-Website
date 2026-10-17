<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

// A harmless queue probe: never invokes mail or reads recipient data.
class CheckCommunicationsWorker implements ShouldQueue
{
    use Dispatchable, Queueable;
    public $tries = 1;
    public $timeout = 15;
    public function __construct(public string $probeId) { $this->onQueue('communications'); }
    public function handle(): void
    {
        Cache::put('fmtrx.communications.worker_probe', ['id' => $this->probeId, 'completed_at' => now()->toIso8601String()], 3600);
    }
}
