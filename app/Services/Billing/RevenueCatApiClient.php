<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\RevenueCatClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RevenueCatApiClient implements RevenueCatClient
{
    public function subscriptionsFor(string $appUserId): array
    {
        $key = (string) config('billing.revenuecat.secret_api_key');
        $project = (string) config('billing.revenuecat.project_id');
        if ('' === $key || '' === $project) {
            throw new RuntimeException('RevenueCat server synchronization is not configured.');
        }
        $client = Http::baseUrl((string) config('billing.revenuecat.base_url'))
            ->withToken($key)->acceptJson()->timeout(8);
        $response = $client
            ->get('/projects/'.rawurlencode($project).'/customers/'.rawurlencode($appUserId).'/subscriptions', [
                'environment' => 'test' === config('billing.revenuecat.environment') ? 'sandbox' : 'production',
            ])->throw()->json();

        $items = is_array($response['items'] ?? null) ? $response['items'] : [];

        return array_map(function (array $subscription) use ($client, $project): array {
            $subscriptionId = (string) ($subscription['id'] ?? '');
            if ('' === $subscriptionId) {
                return $subscription;
            }

            $revenueCatProductId = (string) ($subscription['product_id'] ?? '');
            if ('' === $revenueCatProductId) {
                return $subscription;
            }

            $product = $client
                ->get('/projects/'.rawurlencode($project).'/products/'.rawurlencode($revenueCatProductId))
                ->throw()->json();
            $storeProductId = (string) ($product['store_identifier'] ?? '');

            if ('' !== $storeProductId) {
                $subscription['revenuecat_product_id'] = $revenueCatProductId;
                $subscription['product_id'] = $storeProductId;
            }

            return $subscription;
        }, $items);
    }
}
