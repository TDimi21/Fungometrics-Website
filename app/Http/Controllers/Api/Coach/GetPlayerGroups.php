<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlayerGroup;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: list player sub-groups for the coach's teams.
 */
class GetPlayerGroups extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $groups = PlayerGroup::whereIn('team_id', $teamIds)
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'code'    => '0B0',
                'message' => 'player groups',
                'status'  => 'success',
                'data'    => $groups,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetPlayerGroups: ' . $e->getMessage());

            return response()->json([
                'code'    => '0B0-E',
                'message' => 'failed to fetch player groups',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
