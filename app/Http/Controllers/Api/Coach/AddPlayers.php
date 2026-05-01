<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Events\UserChanged;
use App\Events\UserCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AddUserRequest;
use App\Models\User;
use App\Services\ListServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddPlayers extends Controller
{
    /**
     * @param  AddUserRequest  $request
     * @return JsonResponse
     */
    public function __invoke(AddUserRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $player = (new ListServiceData(new User()))->byParamFirst('phone', $data['phone']);
            $message = "";
            if ( ! isset($player)) {
                $savePlayer = CoachUtils::saveNewUser($data);
                event(new UserCreated($savePlayer['user']));
                $message = 'the player is added to team';
            } else {
                $data_team = CoachUtils::addPlayerToRoaster($player, $data['team']);
                $data_to_event = [
                    'user' => $player,
                    'team' => $data_team,
                ];

                if( ! $data_team['exist']) {
                    event(new UserChanged($data_to_event));
                }
                if($data_team['exist']) {
                    $message = 'this player already belongs to the team';
                }
            }

            $userId = $player?->id ?? $savePlayer['user']->id;
            $response = [
                'code' => '016',
                'message' => $message,
                'status' => 'success',
                'data' =>  User::with('profile', 'player', 'fitness', 'positions')->find($userId),
            ];
            DB::commit();

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '016-E',
                'message' => 'error to add player ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
