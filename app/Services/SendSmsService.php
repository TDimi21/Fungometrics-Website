<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmsLog;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class SendSmsService
{
    /**
     * @param $phone
     * @return bool
     */
    public function sendSms(
        $phone,
        $message = 'Welcome to http://www.fungometrics.com',
        $practice=null,
        $type = 'create_profile',
        $user=null,
        bool $sensitive = false
    ): bool {
        try {
            $client = self::smsClient();
            $client?->messages->create($phone, [
                'from' => config('services.twilio.number'),
                'body' => $message,
            ]);
            (new CreateServiceData(new SmsLog()))->handle([
                'user_id' => $user??Auth::id(),
                'practice_id' => $practice,
                'type' => $type,
                'phone' => $phone,
                'message' => $sensitive ? '[redacted security message]' : $message,
                'response' => $sensitive ? null : $client,
                'status' => true,
            ]);

            return true;
        } catch (Exception $exception) {
            Log::warning('SMS delivery failed.');
            (new CreateServiceData(new SmsLog()))->handle([
                'user_id' => $user??Auth::id(),
                'practice_id' => $practice,
                'type' => $type,
                'phone' => $phone,
                'message' => $sensitive ? '[redacted security message]' : $message,
                'response' => null,
                'status' => false,
            ]);

            return false;
        }
    }

    private static function smsClient()
    {
        try {
            return new Client(config('services.twilio.sid'), config('services.twilio.token'));
        } catch (Exception $exception) {
            Log::error('SMS provider initialization failed.');
            return null;
        }
    }
}
