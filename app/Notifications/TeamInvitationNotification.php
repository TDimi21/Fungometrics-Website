<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

// Sent synchronously inside the invitation job so raw tokens never enter queue storage.
class TeamInvitationNotification extends Notification
{
    public function __construct(public string $acceptUrl, public string $coach, public string $team, public string $expiry, public string $role)
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
            'lines' => ["{$this->coach} invited you to join {$this->team} as a {$this->role}.", 'Your existing training history stays with your profile.', "This invitation expires {$this->expiry}.", 'If you did not expect this invitation, you can ignore it.'],
            'actionText' => 'Accept invitation', 'actionUrl' => $this->acceptUrl,
        ]);
    }
}
