<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\Concerns\UserTypes;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EditTeamsTest extends TestCase
{
    public function test_edit_team_ok_change_logo(): void
    {
        $team = Team::factory()->create();
        Storage::fake('s3');
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $dataEdit = [
            'name' => fake()->company,
            'logo' => UploadedFile::fake()->image('team.jpg'),
        ];
        $response = $this->json('POST', 'api/coach/edit/teams/'.$team->id, $dataEdit);

        $response->assertOk();
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($data_response->data->name, $dataEdit['name']);
        $this->assertNotEquals($team->logo, $data_response->data->logo);
    }

    public function test_edit_team_ok_no_change_logo(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $dataEdit = [
            'logo' => fake()->imageUrl,
            'name' => fake()->company,
        ];
        $response = $this->json('POST', 'api/coach/edit/teams/'.$team->id, $dataEdit);
        $response->assertOk();
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($data_response->data->name, $dataEdit['name']);
        $this->assertEquals($dataEdit['logo'], $data_response->data->logo);
    }

  public function test_edit_team_ok_without_logo(): void
  {
      $team = Team::factory()->create();
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $dataEdit = [
          'name' => fake()->company,
      ];
      $response = $this->json('POST', 'api/coach/edit/teams/'.$team->id, $dataEdit);
      $response->assertOk();
      $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
      $this->assertEquals($data_response->data->name, $dataEdit['name']);
  }

    public function test_edit_team_unauthorized(): void
    {
        $team = Team::factory()->create();
        $dataEdit = [
            'logo' => fake()->imageUrl,
            'name' => fake()->company,
        ];
        $response = $this->json('POST', 'api/coach/edit/teams/'.$team->id, $dataEdit);
        $response->assertUnauthorized();
    }

    public function test_edit_team_forbidden(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $dataEdit = [
            'logo' => fake()->imageUrl,
            'name' => fake()->company,
        ];
        $response = $this->json('POST', 'api/coach/edit/teams/'.$team->id, $dataEdit);
        $response->assertForbidden();
    }

    public function test_edit_team_validations(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $dataEdit = [
            'logo' => null,
            'name' => null,
        ];
        $response = $this->json('POST', 'api/coach/edit/teams/'.$team->id, $dataEdit);
        $response->assertUnprocessable();
    }



  public function test_edit_team_ok_error2(): void
  {
      $team = Team::factory()->create();
      Storage::fake('s3');
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $dataEdit = [
          'name' => fake()->company,
          'logo' => UploadedFile::fake()->image('team.jpg'),
      ];
      $response = $this->json('POST', 'api/coach/edit/teams/'.fake()->uuid, $dataEdit);
      $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
  }
}
