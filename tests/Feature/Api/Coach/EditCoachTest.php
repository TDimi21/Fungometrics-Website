<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\Concerns\UserTypes;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditCoachTest extends TestCase
{
    public function test_update_coach_profile_ok(): void
    {
        $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        Profile::factory()->create(['user_id'=>$user->id]);
        $data = [
            'first_name'=>fake()->firstName,
            'last_name'=>fake()->lastName,
            'phone'=>fake()->phoneNumber,
            'picture'=>fake()->imageUrl
        ];

        $response = $this->json('POST', 'api/coach/edit', $data);
        $response->assertOk();
        $dataResponse = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($dataResponse->data->phone, $data['phone']);
        $this->assertEquals($dataResponse->data->profile->first_name, $data['first_name']);
    }

  public function test_update_coach_profile_with_logo_ok(): void
  {
      $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      Storage::fake('s3');

      Profile::factory()->create(['user_id'=>$user->id]);
      $data = [
          'first_name'=>fake()->firstName,
          'last_name'=>fake()->lastName,
          'phone'=>fake()->phoneNumber,
          'picture'=>UploadedFile::fake()->image('avatar.jpg'),
      ];

      $response = $this->json('POST', 'api/coach/edit', $data);
      $response->assertOk();
      $dataResponse = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
      $this->assertEquals($dataResponse->data->phone, $data['phone']);
      $this->assertEquals($dataResponse->data->profile->first_name, $data['first_name']);
  }

  public function test_update_coach_profile_unauthorized(): void
  {
      $response = $this->json('POST', 'api/coach/edit', []);
      $response->assertUnauthorized();
  }

  public function test_update_coach_profile_forbidden(): void
  {
      $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
      $response = $this->json('POST', 'api/coach/edit', []);
      $response->assertForbidden();
  }

  public function test_update_coach_profile_error(): void
  {
      $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $data = [
          'first_name'=>null,
          'last_name'=>fake()->lastName,
          'phone'=>fake()->phoneNumber,
      ];

      $response = $this->json('POST', 'api/coach/edit', $data);
      $response->assertServerError();
  }
}
