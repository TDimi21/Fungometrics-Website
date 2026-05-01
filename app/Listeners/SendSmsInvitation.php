<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use App\Services\SendSmsService;
use Illuminate\Support\Facades\Log;

class SendSmsInvitation
{
    /**
     * @param  \App\Events\UserCreated  $event
     * @return void
     */
    public function handle(UserCreated $event): void
    {
        $code = str_replace('-', '', $event->data->id);
        $message = 'Hi you are invited to fungometrics to complete the register follow the link '.config('app.url').'/complete/'.$code;
        Log::info($message);
        (new SendSmsService())->sendSms($event->data->phone, $message);
    }
}
