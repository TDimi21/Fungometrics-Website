<?php

declare(strict_types=1);

return [
    'privacy_url' => env('FMTRX_PRIVACY_URL', rtrim((string) env('APP_URL'), '/').'/privacy'),
    'terms_url' => env('FMTRX_TERMS_URL', rtrim((string) env('APP_URL'), '/').'/terms'),
    'support_url' => env('FMTRX_SUPPORT_URL', rtrim((string) env('APP_URL'), '/').'/support'),
    'account_deletion_url' => env('FMTRX_ACCOUNT_DELETION_URL', rtrim((string) env('APP_URL'), '/').'/account-deletion'),
    'apple_subscriptions_url' => 'https://apps.apple.com/account/subscriptions',
];
