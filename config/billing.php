<?php

declare(strict_types=1);

return [
    'revenuecat' => [
        'environment' => env('REVENUECAT_ENVIRONMENT', 'test'),
        'webhook_auth' => env('REVENUECAT_WEBHOOK_AUTH'),
        'secret_api_key' => env('REVENUECAT_SECRET_API_KEY'),
        'project_id' => env('REVENUECAT_PROJECT_ID'),
        'base_url' => env('REVENUECAT_API_URL', 'https://api.revenuecat.com/v2'),
        'products' => [
            'fmtrx_coach_pro_monthly' => ['plan' => 'coach_pro', 'entitlement' => 'coach_pro', 'interval' => 'month', 'audience' => 'coach'],
            'fmtrx_coach_pro_annual' => ['plan' => 'coach_pro', 'entitlement' => 'coach_pro', 'interval' => 'year', 'audience' => 'coach'],
            'fmtrx_player_basic_monthly' => ['plan' => 'player_basic', 'entitlement' => 'player_basic', 'interval' => 'month', 'audience' => 'player'],
            'fmtrx_player_pro_monthly' => ['plan' => 'player_pro', 'entitlement' => 'player_pro', 'interval' => 'month', 'audience' => 'player'],
        ],
    ],
];
