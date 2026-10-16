<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CommunicationEvent;
use App\Services\Communications\CommunicationOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

class ProcessCommunicationEvent implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeUnique
{
    use Dispatchable;
    use Queueable;
    public $tries = 1;
    public $timeout = 60;
    public $uniqueFor = 3600;
    public function uniqueId(): string { return $this->eventId; }
    public function __construct(public string $eventId)
    {
        $this->onQueue('communications');
    }
    public function handle(CommunicationOutbox $out): void
    {
        try {
            $out->process($this->eventId);
        } catch(Throwable) {
            CommunicationEvent::whereKey($this->eventId)->whereNull('processed_at')->increment('attempts', 1, ['failure_code' => 'processing_failed']);
        }
    }
}
