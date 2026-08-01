<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\SmsLog;
use App\Services\SendSmsService;
use RuntimeException;
use Tests\TestCase;

class SendSmsServiceTest extends TestCase
{
    public function test_pre_registration_sms_can_be_logged_without_a_user(): void
    {
        $service = new class () extends SendSmsService {
            protected function deliver($phone, string $message): array
            {
                return ['sid' => 'SM_TEST', 'status' => 'queued'];
            }
        };

        $this->assertTrue($service->sendSms(
            '5556666600',
            'Sensitive verification code',
            type: 'team_join_verification',
            user: null,
            sensitive: true
        ));

        $log = SmsLog::query()->sole();
        $this->assertNull($log->user_id);
        $this->assertSame('[redacted security message]', $log->message);
        $this->assertNull($log->response);
        $this->assertTrue($log->status);
    }

    public function test_sms_audit_failure_does_not_change_provider_delivery_result(): void
    {
        $service = new class () extends SendSmsService {
            protected function deliver($phone, string $message): array
            {
                return ['sid' => 'SM_TEST', 'status' => 'queued'];
            }

            protected function writeLog(array $data): void
            {
                throw new RuntimeException('database unavailable');
            }
        };

        $this->assertTrue($service->sendSms(
            '5556666600',
            'Sensitive verification code',
            type: 'team_join_verification',
            sensitive: true
        ));
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_failed_delivery_is_logged_without_exposing_security_message(): void
    {
        $service = new class () extends SendSmsService {
            protected function deliver($phone, string $message): array
            {
                throw new RuntimeException('provider unavailable');
            }
        };

        $this->assertFalse($service->sendSms(
            '5556666600',
            'Sensitive verification code',
            type: 'team_join_verification',
            sensitive: true
        ));
        $this->assertDatabaseHas('sms_logs', [
            'user_id' => null,
            'message' => '[redacted security message]',
            'status' => false,
        ]);
    }
}
