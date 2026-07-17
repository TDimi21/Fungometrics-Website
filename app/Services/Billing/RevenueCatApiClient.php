<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\RevenueCatClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RevenueCatApiClient implements RevenueCatClient
{
    public function subscriptionIdForStoreIdentifier(string $storeSubscriptionIdentifier): string
    {
        if ('' === $storeSubscriptionIdentifier) {
            throw new RuntimeException('RevenueCat store subscription identity is required.');
        }

        $response = $this->client()
            ->get('/projects/'.rawurlencode($this->project()).'/subscriptions', [
                'store_subscription_identifier' => $storeSubscriptionIdentifier,
            ])->throw()->json();

        $items = is_array($response['items'] ?? null) ? $response['items'] : [];
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => is_array($item) ? (string) ($item['id'] ?? '') : '',
            $items
        ))));

        if (1 !== count($ids)) {
            throw new RuntimeException('RevenueCat store subscription identity did not resolve uniquely.');
        }

        return $ids[0];
    }

    public function subscriptionsFor(string $appUserId): array
    {
        $project = $this->project();
        $client = $this->client();
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

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $key = (string) config('billing.revenuecat.secret_api_key');
        if ('' === $key) {
            throw new RuntimeException('RevenueCat server synchronization is not configured.');
        }

        return Http::baseUrl((string) config('billing.revenuecat.base_url'))
            ->withToken($key)->acceptJson()->timeout(8);
    }

    private function project(): string
    {
        $project = (string) config('billing.revenuecat.project_id');
        if ('' === $project) {
            throw new RuntimeException('RevenueCat server synchronization is not configured.');
        }

        return $project;
    }
}
