<?php

declare(strict_types=1);

namespace App\Providers;

use Resend\Laravel\ResendServiceProvider;

// Reuse the official transport and client without introducing webhook routes.
class ResendMailServiceProvider extends ResendServiceProvider
{
    protected function registerRoutes(): void
    {
        // Sending only. Delivery webhooks can be explicitly integrated later.
    }
}
