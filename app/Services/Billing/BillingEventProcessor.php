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
            ['event_type' => $eventType, 'payload' => $payload]
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
                $handler = collect($this->handlers)->first(fn (ProviderEventHandler $candidate): bool => $candidate->supports($event->provider, $event->event_type));
                if ( ! $handler) {
                    throw ValidationException::withMessages(['event' => 'No provider event handler is registered.']);
                }
                $handler->handle($event);
                $event->update(['processed_at' => now(), 'processing_error' => null]);
                return $event->fresh();
            });
        } catch (Throwable $exception) {
            BillingEvent::whereKey($event->id)->whereNull('processed_at')->update(['processing_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function retryFailed(BillingEvent $event): BillingEvent
    {
        if (null !== $event->processed_at || null === $event->processing_error) {
            throw ValidationException::withMessages(['event' => 'Only failed, unprocessed events may be retried.']);
        }
        return $this->process($event);
    }
}
