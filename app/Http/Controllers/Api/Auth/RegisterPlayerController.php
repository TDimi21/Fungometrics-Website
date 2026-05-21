<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\PlayerUtils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterPlayerRequest;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Throwable;

class RegisterPlayerController extends Controller
{
    /**
     * @param  RegisterPlayerRequest  $request
     * @return JsonResponse
     *
     * @throws Throwable
     */
    public function __invoke(RegisterPlayerRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $responseUser = AuthUtils::saveUser($request);

            $url = UploadS3File::getUrl($request->picture, '/players');

            $response_profile = AuthUtils::saveProfile($request, $responseUser, $url);
            $response_player = PlayerUtils::savePlayer($request, $responseUser);
            PlayerUtils::createPlayerFitness($responseUser);
            $position_response = PlayerUtils::savePositionsPlayer($request, $responseUser->id);

            // If a valid team join_code was provided, auto-join the player to that team
            $joinedTeam = null;
            $teamCode = strtoupper(trim($request->input('team_code', '')));
            if ($teamCode !== '') {
                $team = Team::where('join_code', $teamCode)->first();
                if ($team) {
                    PlayerTeam::create([
                        'user_id' => $responseUser->id,
                        'team_id' => $team->id,
                        'actual'  => true,
                    ]);
                    $joinedTeam = $team;
                }
            }

            DB::commit();

            $data_to_response = [
                'user'      => $responseUser,
                'profile'   => $response_profile,
                'player'    => $response_player,
                'positions' => $position_response,
                'token'     => null !== $request->get('email') ? AuthUtils::createTokenFromUser($responseUser)['token'] : '',
                'team'      => $joinedTeam,
            ];

            $response = [
                'code' => '002',
                'message' => 'register player ok',
                'status' => 'success',
                'data' => $data_to_response,
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            UploadS3File::deleteObject($url, 'players');
            DB::rollBack();
            $response = [
                'code' => '002-E',
                'message' => 'error to register player',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
