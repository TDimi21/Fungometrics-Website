<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Support\TestPhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestPhoneNumberTest extends TestCase
{
    public function test_placeholder_profiles_remain_distinct_and_can_log_in_by_email(): void
    {
        Storage::fake('s3');
        foreach (['player', 'coach'] as $role) {
            foreach (['000-000-0000', '(000) 000-0000', '+10000000000'] as $index => $phone) {
                $email = "$role$index@example.test";
                $body = [
                    'email'=>$email, 'phone'=>$phone, 'password'=>'Password123',
                    'profile'=>['name'=>['first'=>'Test','last'=>'Account'], 'level'=>'player'],
                    'picture'=>UploadedFile::fake()->image('player.jpg'),
                    'logo'=>UploadedFile::fake()->image('team.jpg'),
                    'team'=>'Test team', 'city'=>'City', 'state'=>'Georgia', 'zip'=>'30000',
                    'player'=>['born'=>'2000-05-12','ft'=>6,'inch'=>0,'shirt'=>1],
                    'positions'=>[['position'=>'P']],
                ];
                $this->postJson("/api/$role/register", $body)->assertCreated();
                $user = User::where('email',$email)->firstOrFail();
                $this->assertNull($user->phone);
                $this->postJson('/api/login', ['email'=>$email,'password'=>'Password123'])->assertOk();
            }
        }
        $this->assertSame(6, User::whereNull('phone')->count());
    }

    public function test_only_the_zero_placeholder_is_special(): void
    {
        foreach (['000-000-0000','0000000000','(000) 000-0000','+1 (000) 000-0000'] as $value) $this->assertTrue(TestPhoneNumber::matches($value));
        foreach ([null,'','123-456-7890','000000000','00000000000','test0000000000'] as $value) $this->assertFalse(TestPhoneNumber::matches($value));
    }

    public function test_placeholder_cannot_request_a_claim_verification_code(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\Security\PlayerContactVerification::class)->normalize('000-000-0000');
    }
}
