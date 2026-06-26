<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin email allowlist
    |--------------------------------------------------------------------------
    |
    | There is no admin role in the users schema (User::type is only player or
    | coach), so privileged routes (e.g. changing another user's subscription
    | plan) are restricted to this explicit allowlist instead of being gated by
    | the generic `coach` ability. Set ADMIN_EMAILS in the environment as a
    | comma-separated list, e.g. ADMIN_EMAILS="ops@fmtrx.com,tom@fmtrx.com".
    |
    */
    'emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),
];
