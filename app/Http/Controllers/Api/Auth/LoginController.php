<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\RoasterUtils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\Login\CoachResource;
use App\Http\Resources\Api\Login\PlayerResource;
use App\Http\Resources\Api\PlayerTeamResource;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class LoginController extends Controller
{
    private RoasterUtils $getPlayerData;

    public function __construct()
    {
        $this->getPlayerData = new RoasterUtils();
    }

    /**
     * @param LoginRequest $request
     * @return JsonResponse
     */
      public function __invoke(LoginRequest $request): JsonResponse
      {
          $response_data = collect();
          $response = null;
          try {
              AuthUtils::authCredentials($request);
              $user = User::where('email', $request->email)->firstOrFail();
              $data = AuthUtils::createTokenFromUser($user);
              $response_data->put('token', $data['token']);
              if ($user->type === UserTypes::PLAYER->value) {
                  $response_data->put('player', $user->toArray());
                  $response_data->put('profile', $user->profile
                      ->toArray());
                  $response_data->put('positions', $user->positions
                      ->makeHidden(['created_at', 'updated_at', 'player_id'])
                      ->toArray());
                  $response_data->put('other', $user->player
                      ->makeHidden(['created_at', 'updated_at', 'player_id'])
                      ->toArray());
                  $response_data->put('fitness', $user->fitness
                      ->makeHidden(['created_at', 'updated_at', 'user_id'])->toArray());
                  $response = new PlayerResource($response_data->all());
              }

              if ($user->type === UserTypes::COACH->value) {
                  $temp_data = $this->getDataLoginCoach($user);
                  $response_data->put('user', $temp_data['user']->toArray());
                  $response_data->put('teams', $temp_data['teams']->toArray());
                  $response_data->put('profile', $user->profile->toArray());
                  if(null=== $temp_data['team']) {
                      $team_users =[];
                      $players_data=collect();
                  } else {
                      $team_users = $temp_data['team']->team_players->where('actual', true)->pluck('user_id')->toArray();
                      $players_data = (new RoasterUtils())->getDataPlayers($team_users);
                  }


                  $players = [];
                  $count_player = 0;
                  if (0 !== $players_data->count()) {
                      $players = PlayerTeamResource::collection($players_data);
                      $count_player = $players_data->count();
                  }
                  $response_data->put('players', $players);
                  $response_data->put('count_player', $count_player);
                  $response = new CoachResource($response_data->all());
              }

              $response = [
                  'code' => '001',
                  'message' => 'login ok',
                  'status' => 'success',
                  'data' => $response,
              ];

              return response()->json($response, HttpCodes::HTTP_OK);
          } catch (Exception $exception) {
              $response = [
                  'code' => '001-E',
                  'message' => 'credentials not found',
                  'status' => 'error',
                  'data' => [],
              ];
              Log::error($exception->getMessage());
              return response()->json($response, HttpCodes::HTTP_UNAUTHORIZED);
          }
      }

      private function getDataLoginCoach(User $user): array
      {
          $teams = CoachTeam::with(['team'])->where('coach_id', $user->id)->get();
          return [
              'user' => $user,
              'team'=> $teams->where('is_main', true)->first()?->team,
              'teams' => $teams->sortByDesc('is_main')

          ];
      }
}
