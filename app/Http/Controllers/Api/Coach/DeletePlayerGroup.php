<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlayerGroup;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: delete a player sub-group (soft delete) within the coach's teams.
 */
class DeletePlayerGroup extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $group = PlayerGroup::where('id', $id)
                ->whereIn('team_id', $teamIds)
                ->first();

            if ($group) {
                $group->delete();
            }

            return response()->json([
                'code'    => '0B2',
                'message' => 'player group deleted',
                'status'  => 'success',
                'data'    => [],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('DeletePlayerGroup: ' . $e->getMessage());

            return response()->json([
                'code'    => '0B2-E',
                'message' => 'failed to delete player group',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
