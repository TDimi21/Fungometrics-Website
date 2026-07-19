<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingEvent;
use App\Services\Billing\BillingEventProcessor;
use Illuminate\Http\JsonResponse;

class BillingEventAdminController extends Controller
{
    public function failed(): JsonResponse
    {
        return response()->json(['data' => BillingEvent::query()
            ->whereNull('processed_at')
            ->whereIn('processing_status', ['retry_scheduled', 'terminal_failure', 'dead_letter'])
            ->latest('updated_at')
            ->limit(100)
            ->get([
                'id', 'provider', 'provider_event_id', 'event_type', 'processing_status',
                'processing_attempts', 'last_attempted_at', 'next_retry_at',
            ])]);
    }

    public function retry(BillingEvent $event, BillingEventProcessor $processor): JsonResponse
    {
        if (null !== $event->processed_at) {
            return response()->json(['message' => 'The event is already processed.'], 422);
        }
        if ('terminal_failure' === $event->processing_status) {
            return response()->json(['message' => 'Terminal provider validation failures cannot be retried.'], 422);
        }
        if ('dead_letter' === $event->processing_status) {
            $event->update(['processing_status' => 'retry_scheduled', 'next_retry_at' => now()]);
        }

        $processed = $processor->retryFailed($event->fresh());

        return response()->json(['data' => $processed->only([
            'id', 'provider_event_id', 'processing_status', 'processed_at',
        ])]);
    }
}
