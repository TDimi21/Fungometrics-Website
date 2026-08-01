<?php

declare(strict_types=1);

return [
    'web_token_ttl_minutes' => (int) env('WEB_TOKEN_TTL_MINUTES', 480),
    'account_claim_ttl_minutes' => (int) env('ACCOUNT_CLAIM_TTL_MINUTES', 60),
    'team_join_ttl_minutes' => (int) env('TEAM_JOIN_TTL_MINUTES', 10),
    'team_join_max_attempts' => (int) env('TEAM_JOIN_MAX_ATTEMPTS', 5),
    'test_phone_verification' => [
        // Test verification remains fail-closed unless every setting is valid.
        // Keep the allowlist limited to synthetic numbers that cannot receive SMS.
        'enabled' => (bool) env('FMTRX_TEST_PHONE_VERIFICATION_ENABLED', false),
        'code' => (string) env('FMTRX_TEST_PHONE_VERIFICATION_CODE', ''),
        'phones' => array_values(array_filter(array_map(
            static fn (string $phone): string => preg_replace('/\D+/', '', trim($phone)) ?? '',
            explode(',', (string) env('FMTRX_TEST_PHONE_VERIFICATION_PHONES', ''))
        ))),
        'ends_at' => env('FMTRX_TEST_PHONE_VERIFICATION_ENDS_AT'),
    ],
];
