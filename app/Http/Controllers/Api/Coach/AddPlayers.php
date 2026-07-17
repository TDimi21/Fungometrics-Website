<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Events\UserChanged;
use App\Events\UserCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AddUserRequest;
use App\Models\Player;
use App\Models\PlayerTeam;
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

            // ── Player limit enforcement ──
            $teamId = $data['team'] ?? null;
            $playerLimit = $teamId ? CoachUtils::playerLimit($teamId) : null;
            if ($teamId && null !== $playerLimit) {
                $currentCount = PlayerTeam::where('team_id', $teamId)
                    ->where('actual', true)
                    ->count();
                if ($currentCount >= $playerLimit) {
                    DB::rollBack();
                    return response()->json([
                        'code'    => '016-LIMIT',
                        'message' => "You have reached the {$playerLimit}-player limit on your current plan.",
                        'status'  => 'error',
                        'data'    => [],
                    ], HttpCodes::HTTP_FORBIDDEN);
                }
            }
            $player = (new ListServiceData(new User()))->byParamFirst('phone', $data['phone']);
            $message = "";
            $changedEventData = null;
            if ( ! isset($player)) {
                $savePlayer = CoachUtils::saveNewUser($data);
                event(new UserCreated($savePlayer['user']));
                $message = 'the player is added to team';
            } else {
                $data_team = CoachUtils::addPlayerToRoaster($player, $data['team']);
                if($data_team['exist']) {
                    $message = 'this player already belongs to the team';
                } else {
                    $message = 'the player is added to team';
                    $changedEventData = ['user' => $player, 'team' => $data_team];
                }
            }

            $userId = $player?->id ?? $savePlayer['user']->id;
            $playerInput = $data['player'] ?? [];
            if (isset($playerInput['grad_year']) && $playerInput['grad_year']) {
                Player::query()->updateOrCreate(
                    ['user_id' => (string) $userId],
                    ['grad_year' => (int) $playerInput['grad_year']]
                );
            }
            $response = [
                'code' => '016',
                'message' => $message,
                'status' => 'success',
                'data' =>  User::with('profile', 'player', 'fitness', 'positions')->find($userId),
            ];
            DB::commit();
            if (null !== $changedEventData) {
                event(new UserChanged($changedEventData));
            }

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
