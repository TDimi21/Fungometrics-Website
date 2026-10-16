<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class FmtrxActivityNotification extends Notification
{
    public function __construct(public array $content, public bool $optional = true)
    {
    }
    public function via($notifiable): array
    {
        return ['mail'];
    }
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())->subject($this->content['heading'])->markdown('mail.fmtrx-notification', [
            'heading' => $this->content['heading'],'lines' => array_merge($this->content['lines'], $this->optional ? ['Manage email preferences in FMTRX Settings.'] : []),
            'actionText' => $this->content['action_text'],'actionUrl' => $this->content['action_url'],
            'preferencesUrl' => $this->optional ? $this->content['preferences_url'] : null,
        ]);
    }
}
