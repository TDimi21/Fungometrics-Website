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
    // Empty allowlist intentionally sends nothing while pilot mode is on.
    'pilot_mode' => env('FMTRX_EMAIL_PILOT_MODE', true),
    'pilot_allowlist' => array_values(array_filter(array_map(static fn ($email) => strtolower(trim($email)), explode(',', (string) env('FMTRX_EMAIL_PILOT_ALLOWLIST', ''))))),
    'categories' => [
        'invitations' => env('FMTRX_INVITATION_EMAILS_ENABLED', false),
        'workouts' => env('FMTRX_WORKOUT_EMAILS_ENABLED', false),
        'assessments' => env('FMTRX_ASSESSMENT_EMAILS_ENABLED', false),
        'development_plans' => env('FMTRX_DEVELOPMENT_EMAILS_ENABLED', false),
        'weekly_reports' => env('FMTRX_WEEKLY_EMAILS_ENABLED', false),
        'team_announcements' => env('FMTRX_ANNOUNCEMENT_EMAILS_ENABLED', false),
        'billing' => env('FMTRX_BILLING_EMAILS_ENABLED', false),
        'welcome' => env('FMTRX_WELCOME_EMAILS_ENABLED', false),
    ],
];
