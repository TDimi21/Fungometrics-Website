<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Billing\RevenueCatClient;
use App\Services\Billing\BillingEventProcessor;
use App\Services\Billing\RevenueCatApiClient;
use App\Services\Billing\RevenueCatEventHandler;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(RevenueCatClient::class, RevenueCatApiClient::class);
        $this->app->bind(BillingEventProcessor::class, fn ($app) => new BillingEventProcessor([$app->make(RevenueCatEventHandler::class)]));

        if ($this->app->environment('local')) {
            if (class_exists(TelescopeServiceProvider::class)) {
                $this->app->register(TelescopeServiceProvider::class);
            }
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        if ( ! app()->environment('local')) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}
