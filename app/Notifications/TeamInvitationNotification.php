<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

// Sent synchronously inside the invitation job so raw tokens never enter queue storage.
class TeamInvitationNotification extends Notification
{
    public function __construct(public string $acceptUrl, public string $coach, public string $team, public string $expiry, public string $role, public ?string $claimCode = null, public ?string $teamCode = null)
    {
    }
    public function via($notifiable): array
    {
        return ['mail'];
    }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())->subject('Your FMTRX team invitation')->markdown('mail.fmtrx-notification', [
            'heading' => 'You are invited to FMTRX',
            'lines' => $this->role === 'player' ? [
                "{$this->coach} invited you to claim your player profile on {$this->team}.",
                'Click Claim Profile to open the player claim screen. Your invited email and team code will be filled in for you.',
                ...($this->teamCode ? ["Team code: {$this->teamCode}"] : []),
                'Confirm your profile, then choose a password if you are new to FMTRX or use your existing player password. Your training history stays with your original profile.',
                'If a coach or another account is signed in, sign out on the claim screen before continuing.',
                "This invitation expires {$this->expiry}. Keep the invitation link private.",
            ] : [
                "{$this->coach} invited you to join {$this->team} as a coach.",
                'Use the invited email and your existing password, or choose a password to finish your coach account.',
                "This invitation expires {$this->expiry}.",
                ...($this->claimCode ? ["Backup manual claim code: {$this->claimCode}", 'Enter this code and your invited email at https://app.fmtrx.com/invitations/accept. Never share this code.'] : []),
            ],
            'actionText' => $this->role === 'player' ? 'Claim Profile' : 'Accept invitation', 'actionUrl' => $this->acceptUrl,
        ]);
    }
}
