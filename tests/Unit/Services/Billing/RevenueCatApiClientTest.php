<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\RevenueCatApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RevenueCatApiClientTest extends TestCase
{
    public function test_it_resolves_the_store_identifier_from_the_revenuecat_product(): void
    {
        config([
            'billing.revenuecat.base_url' => 'https://api.revenuecat.com/v2',
            'billing.revenuecat.secret_api_key' => 'sk_test',
            'billing.revenuecat.project_id' => 'project-1',
            'billing.revenuecat.environment' => 'test',
        ]);

        Http::fake([
            'api.revenuecat.com/v2/projects/project-1/customers/user-1/subscriptions*' => Http::response([
                'items' => [[
                    'id' => 'subscription-1',
                    'product_id' => 'revenuecat-product-1',
                    'status' => 'active',
                ]],
            ]),
            'api.revenuecat.com/v2/projects/project-1/products/revenuecat-product-1' => Http::response([
                'id' => 'revenuecat-product-1',
                'store_identifier' => 'fmtrx_player_basic_monthly',
            ]),
        ]);

        $items = (new RevenueCatApiClient())->subscriptionsFor('user-1');

        $this->assertSame('fmtrx_player_basic_monthly', $items[0]['product_id']);
        $this->assertSame('revenuecat-product-1', $items[0]['revenuecat_product_id']);
        Http::assertSentCount(2);
    }
}
