<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Contracts\Billing\RevenueCatClient;
use App\Http\Controllers\Controller;
use App\Services\Access\EntitlementResolver;
use App\Services\Billing\RevenueCatReconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RevenueCatSyncController extends Controller
{
    public function __invoke(Request $request, RevenueCatClient $client, RevenueCatReconciler $reconciler, EntitlementResolver $resolver): JsonResponse
    {
        $user = $request->user();
        try {
            $items = $client->subscriptionsFor((string) $user->id);
        } catch (RuntimeException) {
            return response()->json(['message' => 'RevenueCat synchronization is not configured.'], 503);
        }
        $reconciler->reconcileCustomer($user, $items);
        return response()->json(['status' => 'success', 'data' => $resolver->getAccessSummary($user->fresh(), $request->input('team_id'))]);
    }
}
