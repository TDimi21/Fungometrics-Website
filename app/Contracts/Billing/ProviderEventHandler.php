<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Models\BillingEvent;

interface ProviderEventHandler
{
    public function supports(string $provider, string $eventType): bool;
    public function handle(BillingEvent $event): void;
}
