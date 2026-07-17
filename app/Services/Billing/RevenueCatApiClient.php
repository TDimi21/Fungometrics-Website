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
        $response = Http::baseUrl((string) config('billing.revenuecat.base_url'))
            ->withToken($key)->acceptJson()->timeout(8)
            ->get('/projects/'.rawurlencode($project).'/customers/'.rawurlencode($appUserId).'/subscriptions', [
                'environment' => 'test' === config('billing.revenuecat.environment') ? 'sandbox' : 'production',
            ])->throw()->json();

        return is_array($response['items'] ?? null) ? $response['items'] : [];
    }
}
