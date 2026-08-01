<?php

declare(strict_types=1);

return [
    'web_token_ttl_minutes' => (int) env('WEB_TOKEN_TTL_MINUTES', 480),
    'account_claim_ttl_minutes' => (int) env('ACCOUNT_CLAIM_TTL_MINUTES', 60),
    'profile_claim_ttl_minutes' => (int) env('PROFILE_CLAIM_TTL_MINUTES', 10),
];
