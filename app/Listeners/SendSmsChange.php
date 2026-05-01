<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserChanged;
use App\Models\Team;
use App\Services\SendSmsService;
use Illuminate\Support\Facades\Log;

class SendSmsChange
{
    /**
     * @param  UserChanged  $event
     * @return void
     */
    public function handle(UserChanged $event): void
    {
        $team = Team::find($event->data['team']['data']['team_id'])->name;
        $message = 'Hi '.$event->data['user']->profile->first_name.' you are added to team: '
            .$team.' in fungometrics';
        Log::info($message);
        (new SendSmsService())->sendSms($event->data['user']->phone, $message);
    }
}
