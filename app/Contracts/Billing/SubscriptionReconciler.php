<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Models\BillingEvent;

interface SubscriptionReconciler
{
    public function reconcile(BillingEvent $event): void;
}
