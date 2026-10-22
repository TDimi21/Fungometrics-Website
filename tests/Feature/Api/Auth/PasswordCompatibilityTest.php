<?php
namespace Tests\Feature\Api\Auth;
use App\Http\Requests\Api\Auth\{RegisterCoachRequest, RegisterPlayerRequest, LoginRequest};
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Models\{User, Profile, Team};
use Illuminate\Support\Facades\{Validator, Hash};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class PasswordCompatibilityTest extends TestCase
{
    public function test_password_boundaries_for_new_credentials_and_existing_proof(): void
    {
        foreach ([6,7,8,20,21] as $length) {
            $password = str_repeat('x', $length);
            foreach ([new RegisterCoachRequest, new RegisterPlayerRequest, new ChangePasswordRequest] as $request) {
                $this->assertSame($length >= 8 && $length <= 20, Validator::make(['password'=>$password,'password_confirmation'=>$password], ['password'=>$request->rules()['password']])->passes());
            }
            $this->assertTrue(Validator::make(['password'=>$password], ['password'=>(new LoginRequest)->rules()['password']])->passes());
        }
    }
    public function test_legacy_passwords_can_login_authorize_deletion_and_change_password(): void
    {
        foreach (['short6', 'seven77', str_repeat('x',21)] as $password) {
            \Illuminate\Support\Facades\Auth::shouldUse('web');
            $user = User::factory()->create(['type'=>'coach','password'=>Hash::make($password)]);
            Profile::factory()->create(['user_id'=>$user->id]);
            $this->grantTeamAccess($user, Team::factory()->create());
            $this->postJson('/api/login', ['email'=>$user->email,'password'=>$password])->assertOk();
            Sanctum::actingAs($user, ['coach']);
            $this->postJson('/api/me/account-deletion/authorize', ['password'=>$password])->assertOk();
            $this->postJson('/api/password', ['old'=>$password,'password'=>'NewPassword123','password_confirmation'=>'NewPassword123'])->assertOk();
            $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
            $this->app['auth']->forgetGuards();
        }
    }
}
