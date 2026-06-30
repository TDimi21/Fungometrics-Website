<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class RemoveCoachFromTeam extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $target = CoachTeam::findOrFail($request->id);
            $actor = $request->user();

            // Only a head coach of THIS team may remove coaches.
            // Scoping to $target->team_id also closes the cross-team IDOR.
            if (! CoachUtils::isHeadCoach($actor->id, $target->team_id)) {
                return response()->json([
                    'code' => '056-ROLE',
                    'message' => 'Only the head coach can remove coaches from this team.',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            // Never strand a team without a head coach.
            if ($target->is_main) {
                $headCount = CoachTeam::where('team_id', $target->team_id)
                    ->where('is_main', true)
                    ->count();
                if ($headCount <= 1) {
                    return response()->json([
                        'code' => '056-LASTHEAD',
                        'message' => 'You cannot remove the only head coach. Assign another head coach first.',
                        'status' => 'error',
                        'data' => [],
                    ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $target->forceDelete();
            $response = [
                'code' => '056',
                'message' => 'coach remove from team',
                'status' => 'success',
                'data' => true,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {

            $response = [
                'code' => '056-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
