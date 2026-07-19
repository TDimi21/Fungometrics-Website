<?php

declare(strict_types=1);

return [
    'web_token_ttl_minutes' => (int) env('WEB_TOKEN_TTL_MINUTES', 480),
    'account_claim_ttl_minutes' => (int) env('ACCOUNT_CLAIM_TTL_MINUTES', 60),
    'team_join_ttl_minutes' => (int) env('TEAM_JOIN_TTL_MINUTES', 10),
    'team_join_max_attempts' => (int) env('TEAM_JOIN_MAX_ATTEMPTS', 5),
];
