<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateRevenueCatConfiguration extends Command
{
    protected $signature = 'billing:validate-revenuecat {--production : Require production mode and APP_STORE only}';

    protected $description = 'Fail closed when required RevenueCat server configuration is missing or inconsistent';

    public function handle(): int
    {
        $environment = config('billing.revenuecat.environment');
        $stores = config('billing.revenuecat.sandbox_stores', []);
        $required = [
            'REVENUECAT_ENVIRONMENT' => $environment,
            'REVENUECAT_SANDBOX_STORES' => $stores,
            'REVENUECAT_PROJECT_ID' => config('billing.revenuecat.project_id'),
            'REVENUECAT_WEBHOOK_AUTH' => config('billing.revenuecat.webhook_auth'),
            'REVENUECAT_SECRET_API_KEY' => config('billing.revenuecat.secret_api_key'),
        ];

        $missing = collect($required)
            ->filter(fn (mixed $value): bool => is_array($value) ? [] === $value : ! filled($value))
            ->keys()
            ->all();

        if ([] !== $missing) {
            $this->error('RevenueCat configuration is incomplete: '.implode(', ', $missing));

            return self::FAILURE;
        }

        if ( ! in_array($environment, ['test', 'production'], true)) {
            $this->error('REVENUECAT_ENVIRONMENT must be explicitly set to test or production.');

            return self::FAILURE;
        }

        if ($this->option('production')) {
            if ('production' !== $environment) {
                $this->error('Production validation requires REVENUECAT_ENVIRONMENT=production.');

                return self::FAILURE;
            }

            if (array_values($stores) !== ['APP_STORE']) {
                $this->error('Production validation requires APP_STORE as the only allowed store.');

                return self::FAILURE;
            }
        }

        $this->info('RevenueCat server configuration is explicit and internally consistent.');

        return self::SUCCESS;
    }
}
