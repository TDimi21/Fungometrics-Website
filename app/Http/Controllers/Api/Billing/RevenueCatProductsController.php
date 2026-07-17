<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueCatProductsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $audience = (string) $request->user()->type;
        $products = collect(config('billing.revenuecat.products', []))->filter(fn ($mapping) => ($mapping['audience'] ?? null) === $audience)
            ->map(fn ($mapping, $id) => ['product_id' => $id, 'plan' => $mapping['plan'], 'interval' => $mapping['interval'], 'audience' => $mapping['audience']])->values();
        return response()->json(['status' => 'success', 'data' => $products]);
    }
}
