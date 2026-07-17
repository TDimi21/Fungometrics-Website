<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

interface RevenueCatClient
{
    /** @return array<int, array<string, mixed>> */
    public function subscriptionsFor(string $appUserId): array;

    public function subscriptionIdForStoreIdentifier(string $storeSubscriptionIdentifier): string;
}
