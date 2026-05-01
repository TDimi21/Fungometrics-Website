<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SentResults;
use App\Models\Concerns\TypeSMS;
use App\Services\SendSmsService;
use Illuminate\Support\Facades\Log;

class SentResultsNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\SentResults  $event
     * @return void
     */
    public function handle(SentResults $event): void
    {
        $message = "Hey ".$event->data['data']['name']." Your Stats are available on ".config('app.url')." ,
                please login to check your stats.";
        Log::info($message);
        (new SendSmsService())->sendSms(
            phone:$event->data['data']['phone'],
            message: $message,
            practice: $event->data['practice'],
            type: TypeSMS::TRAINING->value,
            user: $event->data['data']['id']
        );
    }
}
