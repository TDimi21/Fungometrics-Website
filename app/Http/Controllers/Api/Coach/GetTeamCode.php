<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\Team;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTeamCode extends Controller
{
    /**
     * GET /coach/teams/{id}/code
     * Returns (and generates if missing) the join code for a team the authenticated coach owns.
     */
    public function __invoke(string $id): JsonResponse
    {
        try {
            // Verify the authenticated coach actually owns this team
            $owns = CoachTeam::where('coach_id', Auth::id())
                ->where('team_id', $id)
                ->exists();

            if (! $owns) {
                return response()->json([
                    'code'    => '017-F',
                    'message' => 'team not found or access denied',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $team = Team::findOrFail($id);

            // Generate a code if somehow it's missing (legacy teams)
            if (empty($team->join_code)) {
                $team->join_code = Team::generateJoinCode();
                $team->save();
            }

            return response()->json([
                'code'    => '017',
                'message' => 'team code ok',
                'status'  => 'success',
                'data'    => [
                    'team_id'   => $team->id,
                    'team_name' => $team->name,
                    'join_code' => $team->join_code,
                ],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code'    => '017-E',
                'message' => 'error retrieving team code',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
