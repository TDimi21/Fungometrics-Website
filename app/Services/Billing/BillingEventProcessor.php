<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\ProviderEventHandler;
use App\Models\BillingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BillingEventProcessor
{
    /** @param iterable<ProviderEventHandler> $handlers */
    public function __construct(private iterable $handlers = [])
    {
    }

    /** @param array<string, mixed> $payload */
    public function record(string $provider, string $providerEventId, string $eventType, array $payload): BillingEvent
    {
        return BillingEvent::firstOrCreate(
            ['provider' => $provider, 'provider_event_id' => $providerEventId],
            ['event_type' => $eventType, 'payload' => $payload, 'processing_status' => 'pending']
        );
    }

    public function process(BillingEvent $event): BillingEvent
    {
        try {
            return DB::transaction(function () use ($event): BillingEvent {
                $event = BillingEvent::query()->lockForUpdate()->findOrFail($event->id);
                if (null !== $event->processed_at) {
                    throw ValidationException::withMessages(['event' => 'Billing event was already processed.']);
                }
                $handler = collect($this->handlers)->first(
                    fn (ProviderEventHandler $candidate): bool => $candidate->supports($event->provider, $event->event_type)
                );
                if ( ! $handler) {
                    throw ValidationException::withMessages(['event' => 'No provider event handler is registered.']);
                }

                $handler->handle($event);
                $event->update([
                    'processed_at' => now(),
                    'processing_error' => null,
                    'processing_status' => 'processed',
                    'processing_attempts' => $event->processing_attempts + 1,
                    'last_attempted_at' => now(),
                    'next_retry_at' => null,
                ]);

                return $event->fresh();
            });
        } catch (Throwable $exception) {
            $current = BillingEvent::query()->find($event->id);
            if ($current && null === $current->processed_at) {
                $this->recordFailure($current, $exception);
            }
            throw $exception;
        }
    }

    public function retryFailed(BillingEvent $event): BillingEvent
    {
        if (
            null !== $event->processed_at
            || null === $event->processing_error
            || in_array($event->processing_status, ['terminal_failure', 'dead_letter'], true)
        ) {
            throw ValidationException::withMessages(['event' => 'Only failed, retryable events may be retried.']);
        }

        return $this->process($event);
    }

    private function recordFailure(BillingEvent $event, Throwable $exception): void
    {
        $attempts = $event->processing_attempts + 1;
        $terminal = $exception instanceof ValidationException;
        $maximum = max(1, (int) config('billing_events.max_attempts', 5));
        $deadLetter = ! $terminal && $attempts >= $maximum;
        $delays = config('billing_events.retry_delays_seconds', [30, 120, 600, 1800, 3600]);
        $delayIndex = min($attempts - 1, max(0, count($delays) - 1));
        $delay = (int) ($delays[$delayIndex] ?? 3600);

        $event->update([
            'processing_error' => mb_substr($exception->getMessage(), 0, 65535),
            'processing_status' => $terminal ? 'terminal_failure' : ($deadLetter ? 'dead_letter' : 'retry_scheduled'),
            'processing_attempts' => $attempts,
            'last_attempted_at' => now(),
            'next_retry_at' => $terminal || $deadLetter ? null : now()->addSeconds($delay),
        ]);
    }
}
