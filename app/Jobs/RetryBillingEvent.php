<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BillingEvent;
use App\Services\Billing\BillingEventProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryBillingEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public string $eventId)
    {
        $this->onQueue('billing');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return config('billing_events.retry_delays_seconds', [30, 120, 600, 1800, 3600]);
    }

    public function handle(BillingEventProcessor $processor): void
    {
        $event = BillingEvent::query()->findOrFail($this->eventId);
        if (null !== $event->processed_at || in_array($event->processing_status, ['terminal_failure', 'dead_letter'], true)) {
            return;
        }
        $processor->retryFailed($event);
    }
}
