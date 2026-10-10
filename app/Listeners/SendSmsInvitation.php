<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use App\Models\Concerns\UserTypes;
use App\Services\Security\AccountClaimService;
use App\Services\SendSmsService;

class SendSmsInvitation
{
    public function __construct(
        private AccountClaimService $claims,
        private SendSmsService $sms
    ) {
    }

    /**
     * @param  \App\Events\UserCreated  $event
     * @return void
     */
    public function handle(UserCreated $event): void
    {
        // Player claim codes are returned to the coach for private sharing.
        // Do not issue another code or invalidate it with an SMS invitation.
        if (UserTypes::PLAYER->value === (string) $event->data->type) {
            return;
        }

        $token = $this->claims->issue($event->data);
        $message = 'You are invited to FungoMetrics. Complete registration: '
            .rtrim((string) config('app.url'), '/').'/complete/'.$token;
        $this->sms->sendSms($event->data->phone, $message, user: $event->data->id, sensitive: true);
    }
}
