<?php

declare(strict_types=1);

return [
    'max_attempts' => (int) env('BILLING_EVENT_MAX_ATTEMPTS', 5),
    'retry_delays_seconds' => [30, 120, 600, 1800, 3600],
];
