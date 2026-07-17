<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RevenueCatWebhookController extends Controller
{
    public function __invoke(Request $request, BillingEventProcessor $processor): JsonResponse
    {
        $expected = (string) config('billing.revenuecat.webhook_auth');
        $provided = (string) $request->header('Authorization');
        if ('' === $expected || '' === $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        if ( ! $request->isJson()) {
            return response()->json(['message' => 'Malformed JSON.'], 422);
        }
        $request->validate(['api_version' => ['required', 'string'], 'event' => ['required', 'array'],
            'event.id' => ['required', 'string', 'max:128'], 'event.type' => ['required', 'string', 'max:128'], 'event.event_timestamp_ms' => ['required', 'integer']]);
        $body = (array) $request->input('event');
        $event = $processor->record('revenuecat', (string) $body['id'], (string) $body['type'], $body);
        if (null === $event->processed_at && null === $event->processing_error) {
            try {
                $processor->process($event);
            } catch (ValidationException) {
                return response()->json(['message' => 'RevenueCat event rejected.'], 422);
            }
        }
        return response()->json(['received' => true]);
    }
}
