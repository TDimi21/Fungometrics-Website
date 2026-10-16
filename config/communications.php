<?php

declare(strict_types=1);

return [
    'enabled' => env('FMTRX_COMMUNICATIONS_ENABLED', false),
    'send_enabled' => env('FMTRX_COMMUNICATIONS_SEND_ENABLED', false),
    'weekly_enabled' => env('FMTRX_WEEKLY_REPORTS_ENABLED', false),
    // Enable only after review and a supervised asynchronous queue is running.
    'invitations_enabled' => env('FMTRX_INVITATIONS_ENABLED', false),
    'frontend_url' => env('FMTRX_FRONTEND_URL', 'https://app.fmtrx.com'),
    'invitation_days' => 7,
];
