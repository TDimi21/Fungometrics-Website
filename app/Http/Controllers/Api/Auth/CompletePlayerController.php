<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\PlayerUtils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterPlayerRequest;
use App\Models\Concerns\LevelTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\User;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class CompletePlayerController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(RegisterPlayerRequest $request, User $user): JsonResponse
    {
        try {
            DB::beginTransaction();
            $url = UploadS3File::getUrl($request->picture, '/players');

            $user->update([
                'email' => $request->get('email'),
                'password' => bcrypt($request->get('password')),
                'type' => UserTypes::PLAYER->value,
            ]);

            $user->profile->update([
                'first_name' => $request->get('profile')['name']['first'],
                'last_name' => $request->get('profile')['name']['last'],
                'picture' => $url,
                'level' => $request->get('profile')['level'] ?? LevelTypes::PLAYER->value,
                'city' => $request->get('city'),
                'state' => $request->get('state'),
                'zip' => $request->get('zip'),
            ]);

            $request_player_data = [
                'height_in_ft' => $request->get('player')['ft'] ?? 0,
                'height_in_inch' => $request->get('player')['inch'] ?? 0,
                // 'weight' => $request->get('player')['weight'] ?? 0,
                'born_date' => $request->get('player')['born'],
            ];

            if (isset($request->get('player')['shirt'])) {
                $request_player_data['number_in_shirt'] = $request->get('player')['shirt'];
            }

            Player::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                $request_player_data
            );

            $position_response = PlayerUtils::savePositionsPlayer($request, $user->id);
            PlayerUtils::createPlayerFitness($user);


            $response = [
                'code' => 'code',
                'message' => 'register player ok',
                'status' => 'success',
                'data' => [],
            ];
            DB::commit();
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
