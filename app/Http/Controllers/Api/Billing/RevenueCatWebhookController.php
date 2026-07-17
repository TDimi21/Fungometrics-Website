<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

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

        $rawBody = $request->getContent();
        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Malformed JSON.'], 422);
        }

        if ( ! is_array($payload) || ! str_starts_with(ltrim($rawBody), '{')) {
            return response()->json(['message' => 'Malformed JSON.'], 422);
        }

        $validator = Validator::make($payload, ['api_version' => ['required', 'string'], 'event' => ['required', 'array'],
            'event.id' => ['required', 'string', 'max:128'], 'event.type' => ['required', 'string', 'max:128'], 'event.event_timestamp_ms' => ['required', 'integer']]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var array<string, mixed> $body */
        $body = $payload['event'];
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
