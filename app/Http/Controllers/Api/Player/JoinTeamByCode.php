<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Api\Auth\AuthUtils;
use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\ListServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class JoinTeamByCode extends Controller
{
    /**
     * POST /player/join
     *
     * Body: { phone: "5558675309", team_code: "HAWK7X" }
     *
     * Two outcomes:
     *   1. Phone matches an existing user  → link to team, return token (player is logged in)
     *   2. Phone not found                 → return team info only (player needs to register first)
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'phone'     => ['required', 'string'],
            'team_code' => ['required', 'string', 'size:6'],
        ]);

        $phone    = preg_replace('/\D/', '', $request->input('phone'));
        $teamCode = strtoupper(trim($request->input('team_code')));

        try {
            // 1. Find the team by join code
            $team = Team::where('join_code', $teamCode)->first();

            if (! $team) {
                return response()->json([
                    'code'    => '018-NF',
                    'message' => 'invalid team code',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            // 2. Find user by phone
            $user = (new ListServiceData(new User()))->byParamFirst('phone', $phone);

            if (! $user) {
                // Player not registered yet — return team info so the app can pre-fill the team code
                return response()->json([
                    'code'    => '018-NR',
                    'message' => 'player not registered',
                    'status'  => 'not_registered',
                    'data'    => [
                        'team_id'   => $team->id,
                        'team_name' => $team->name,
                        'join_code' => $team->join_code,
                    ],
                ], HttpCodes::HTTP_OK);
            }

            DB::beginTransaction();

            // 3. Link player to team (addPlayerToRoaster handles duplicates gracefully)
            $result = CoachUtils::addPlayerToRoaster($user, $team->id);

            // 4. Issue a Sanctum token so the player is immediately logged in
            $tokenData = AuthUtils::createTokenFromUser($user);

            DB::commit();

            $alreadyOnTeam = $result['exist'] ?? false;

            return response()->json([
                'code'    => '018',
                'message' => $alreadyOnTeam ? 'already on team' : 'joined team successfully',
                'status'  => 'success',
                'data'    => [
                    'token'    => $tokenData['token'],
                    'user'     => $user->load('profile', 'player', 'positions', 'fitness'),
                    'team'     => $team,
                ],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('[JoinTeamByCode] ' . $exception->getMessage());
            return response()->json([
                'code'    => '018-E',
                'message' => 'error joining team',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
