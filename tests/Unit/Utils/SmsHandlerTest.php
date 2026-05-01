<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Services\SendSmsService;
use Mockery;
use Tests\TestCase;

class SmsHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_send_sms_ok(): void
    {
        $service = $this->createMock(SendSmsService::class);
        $service->method('sendSms')->willReturn(true);
        $result = $service->sendSms(fake()->phoneNumber, fake()->word);
        $this->assertTrue($result);
    }

    public function test_send_sms_fail(): void
    {
        $service = $this->createMock(SendSmsService::class);
        $service->method('sendSms')->willReturn(false);
        $result = $service->sendSms('');
        $this->assertFalse($result);
    }
}
