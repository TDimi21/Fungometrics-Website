<?php

declare(strict_types=1);

return [
    'revenuecat' => [
        // No silent Test Store fallback. Every environment must declare its
        // billing mode and allowed stores explicitly.
        'environment' => env('REVENUECAT_ENVIRONMENT'),
        'webhook_auth' => env('REVENUECAT_WEBHOOK_AUTH'),
        'secret_api_key' => env('REVENUECAT_SECRET_API_KEY'),
        'project_id' => env('REVENUECAT_PROJECT_ID'),
        'base_url' => env('REVENUECAT_API_URL', 'https://api.revenuecat.com/v2'),
        'sandbox_stores' => array_values(array_filter(array_map('trim', explode(',', (string) env('REVENUECAT_SANDBOX_STORES'))))),
        'products' => [
            'fmtrx_coach_pro_monthly' => ['plan' => 'coach_pro', 'entitlement' => 'coach_pro', 'interval' => 'month', 'audience' => 'coach'],
            'fmtrx_coach_pro_annual' => ['plan' => 'coach_pro', 'entitlement' => 'coach_pro', 'interval' => 'year', 'audience' => 'coach'],
            'fmtrx_player_basic_monthly' => ['plan' => 'player_basic', 'entitlement' => 'player_basic', 'interval' => 'month', 'audience' => 'player'],
            'fmtrx_player_pro_monthly' => ['plan' => 'player_pro', 'entitlement' => 'player_pro', 'interval' => 'month', 'audience' => 'player'],
        ],
    ],
];
