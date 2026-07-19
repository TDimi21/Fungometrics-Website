<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Tests\TestCase;

class ValidateRevenueCatConfigurationTest extends TestCase
{
    public function test_validation_fails_when_configuration_is_implicit_or_missing(): void
    {
        config([
            'billing.revenuecat.environment' => null,
            'billing.revenuecat.sandbox_stores' => [],
            'billing.revenuecat.project_id' => null,
            'billing.revenuecat.webhook_auth' => null,
            'billing.revenuecat.secret_api_key' => null,
        ]);

        $this->artisan('billing:validate-revenuecat')->assertFailed();
    }

    public function test_test_configuration_passes_general_validation_but_not_production_validation(): void
    {
        config([
            'billing.revenuecat.environment' => 'test',
            'billing.revenuecat.sandbox_stores' => ['TEST_STORE', 'APP_STORE'],
            'billing.revenuecat.project_id' => 'project',
            'billing.revenuecat.webhook_auth' => 'configured',
            'billing.revenuecat.secret_api_key' => 'configured',
        ]);

        $this->artisan('billing:validate-revenuecat')->assertSuccessful();
        $this->artisan('billing:validate-revenuecat --production')->assertFailed();
    }

    public function test_production_validation_allows_only_explicit_app_store_configuration(): void
    {
        config([
            'billing.revenuecat.environment' => 'production',
            'billing.revenuecat.sandbox_stores' => ['APP_STORE'],
            'billing.revenuecat.project_id' => 'project',
            'billing.revenuecat.webhook_auth' => 'configured',
            'billing.revenuecat.secret_api_key' => 'configured',
        ]);

        $this->artisan('billing:validate-revenuecat --production')->assertSuccessful();
    }
}
