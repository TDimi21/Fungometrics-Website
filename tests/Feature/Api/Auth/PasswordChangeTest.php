<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    public function test_change_password_ok(): void
    {
        $user = User::factory()->create([
            'password'=>bcrypt('test5676')
        ]);
        Sanctum::actingAs($user);

        $data = [
            'old'=>'test5676',
            'password'=>'test567',
            'password_confirmation'=>'test567',
        ];
        $response = $this->json('POST', 'api/password', $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

     public function test_change_password_different_register_exception(): void
     {
         $user = User::factory()->create([
             'password'=>bcrypt('test5676')
         ]);
         Sanctum::actingAs($user);

         $data = [
             'old'=>'test5677776',
             'password'=>'test567',
             'password_confirmation'=>'test567',
         ];
         $response = $this->json('POST', 'api/password', $data);
         $response->assertUnprocessable()->assertJsonStructure([
             'code',
             'message',
             'status',
             'data'
         ]);
     }

     public function test_change_password_same_old_and_new_error(): void
     {
         $user = User::factory()->create([
             'password'=>bcrypt('test')
         ]);
         Sanctum::actingAs($user);

         $data = [
             'old'=>'test',
             'password'=>'test',
             'password_confirmation'=>'test',
         ];
         $response = $this->json('POST', 'api/password', $data);
         $response->assertUnprocessable()->assertJsonStructure([
             'code',
             'message',
             'status',
             'data'
         ]);
     }

}
