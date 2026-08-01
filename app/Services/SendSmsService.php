<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmsLog;
use RuntimeException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
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
        $practice = null,
        $type = 'create_profile',
        $user = null,
        bool $sensitive = false
    ): bool {
        $ownerId = $user ?? Auth::id();
        $delivered = false;
        $providerResponse = null;

        try {
            $providerResponse = $this->deliver($phone, $message);
            $delivered = true;
        } catch (Throwable) {
            Log::warning('SMS delivery failed.', ['type' => $type]);
        }

        try {
            $this->writeLog([
                'user_id' => $ownerId,
                'practice_id' => $practice,
                'type' => $type,
                'phone' => $phone,
                'message' => $sensitive ? '[redacted security message]' : $message,
                'response' => $sensitive || null === $providerResponse
                    ? null
                    : json_encode($providerResponse, JSON_THROW_ON_ERROR),
                'status' => $delivered,
            ]);
        } catch (Throwable) {
            // SMS audit persistence must never prevent an OTP challenge from
            // being created. SecurityAuditLogger records the challenge itself.
            Log::warning('SMS audit logging failed.', [
                'type' => $type,
                'delivered' => $delivered,
            ]);
        }

        return $delivered;
    }

    /** @return array<string, string|null> */
    protected function deliver($phone, string $message): array
    {
        $client = self::smsClient();
        if (null === $client) {
            throw new RuntimeException('SMS provider is unavailable.');
        }
        $result = $client->messages->create($phone, [
            'from' => config('services.twilio.number'),
            'body' => $message,
        ]);

        return [
            'sid' => isset($result->sid) ? (string) $result->sid : null,
            'status' => isset($result->status) ? (string) $result->status : null,
        ];
    }

    /** @param array<string, mixed> $data */
    protected function writeLog(array $data): void
    {
        SmsLog::query()->create($data);
    }

    private static function smsClient()
    {
        try {
            return new Client(config('services.twilio.sid'), config('services.twilio.token'));
        } catch (Throwable) {
            Log::error('SMS provider initialization failed.');
            return null;
        }
    }
}
