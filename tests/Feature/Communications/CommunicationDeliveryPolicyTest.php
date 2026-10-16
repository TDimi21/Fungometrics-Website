<?php

declare(strict_types=1);
namespace Tests\Feature\Communications;
use App\Services\Communications\CommunicationDeliveryPolicy;
use Tests\TestCase;

class CommunicationDeliveryPolicyTest extends TestCase
{
    public function test_pilot_requires_all_gates_and_an_exact_recipient(): void
    {
        $policy = new CommunicationDeliveryPolicy();
        config(['communications.enabled'=>true,'communications.send_enabled'=>true,'communications.invitations_enabled'=>true,'communications.categories.invitations'=>true,'communications.pilot_mode'=>true,'communications.pilot_allowlist'=>['pilot@example.com']]);
        $this->assertTrue($policy->allows('invitations',' PILOT@example.com '));
        $this->assertFalse($policy->allows('invitations','someone@example.com'));
        $this->assertFalse($policy->allows('workouts','pilot@example.com'));
        foreach(['enabled','send_enabled','invitations_enabled','categories.invitations'] as $gate) {
            config(['communications.'.$gate=>false]);
            $this->assertFalse($policy->allows('invitations','pilot@example.com'));
            config(['communications.'.$gate=>true]);
        }
        config(['communications.pilot_allowlist'=>[]]);
        $this->assertFalse($policy->allows('invitations','pilot@example.com'));
        config(['communications.pilot_mode'=>false]);
        $this->assertTrue($policy->allows('invitations','pilot@example.com'));
        $this->assertFalse($policy->allows('unknown','pilot@example.com'));
    }
}
