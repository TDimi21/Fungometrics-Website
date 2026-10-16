<?php

declare(strict_types=1);

namespace App\Services\Communications;

class CommunicationDeliveryPolicy
{
    public function allows(string $category, ?string $email): bool
    {
        if (!config('communications.enabled') || !config('communications.send_enabled')
            || !config('communications.categories.'.$category, false)) {
            return false;
        }
        $email = strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        if ($category === 'invitations' && !config('communications.invitations_enabled')) return false;
        if ($category === 'weekly_reports' && !config('communications.weekly_enabled')) return false;
        return !config('communications.pilot_mode', true)
            || in_array($email, config('communications.pilot_allowlist', []), true);
    }

    public function category(string $event): ?string
    {
        return CommunicationContent::CATEGORIES[$event] ?? match ($event) {
            'CoachRegistered' => 'welcome',
            'SubscriptionStatusChanged' => 'billing',
            default => null,
        };
    }
}
