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
        try {
            $teamData = $event->data['team'];
            // $teamData may be a Model or an array with a 'data' key
            $teamId = is_array($teamData)
                ? ($teamData['data']['team_id'] ?? $teamData['team_id'] ?? null)
                : ($teamData->team_id ?? null);
            if (!$teamId) return;
            $teamModel = Team::find($teamId);
            if (!$teamModel) return;
            $user = $event->data['user'];
            $firstName = $user->profile->first_name ?? '';
            $message = 'Hi '.$firstName.' you are added to team: '.$teamModel->name.' in fungometrics';
            Log::info($message);
            (new SendSmsService())->sendSms($user->phone, $message);
        } catch (\Exception $e) {
            Log::error('SendSmsChange error: '.$e->getMessage());
        }
    }
}
