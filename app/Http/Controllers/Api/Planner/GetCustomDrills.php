<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlannerCustomDrill;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: my custom drills/lifts — the ones I authored, plus any my teammates have
 * shared to a team we share. These merge into the in-app library.
 */
class GetCustomDrills extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $userId  = Auth::id();
            $teamIds = CoachTeam::where('coach_id', $userId)->pluck('team_id')->all();

            $drills = PlannerCustomDrill::where(function ($q) use ($userId, $teamIds): void {
                $q->where('created_by', $userId)
                    ->orWhere(function ($q2) use ($teamIds): void {
                        $q2->whereIn('team_id', $teamIds)
                            ->whereIn('visibility', ['team', 'public']);
                    });
            })
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'code'    => '097',
                'message' => 'custom drills',
                'status'  => 'success',
                'data'    => $drills->map->toDrillArray()->values(),
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetCustomDrills: ' . $e->getMessage());

            return response()->json([
                'code'    => '097-E',
                'message' => 'failed to fetch custom drills',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
