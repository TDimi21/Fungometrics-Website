<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\ProviderEventHandler;
use App\Models\BillingEvent;

class RevenueCatEventHandler implements ProviderEventHandler
{
    public function __construct(private RevenueCatReconciler $reconciler)
    {
    }
    public function supports(string $provider, string $eventType): bool
    {
        return 'revenuecat' === $provider;
    }
    public function handle(BillingEvent $event): void
    {
        $this->reconciler->reconcile($event);
    }
}
