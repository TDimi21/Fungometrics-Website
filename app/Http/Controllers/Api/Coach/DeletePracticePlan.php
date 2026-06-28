<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PracticePlan;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class DeletePracticePlan extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plan = PracticePlan::where('id', $id)
                ->whereIn('team_id', $teamIds)
                ->first();

            if ($plan) {
                $plan->delete();
            }

            return response()->json([
                'code'    => '082',
                'message' => 'practice plan deleted',
                'status'  => 'success',
                'data'    => [],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('DeletePracticePlan: ' . $e->getMessage());

            return response()->json([
                'code'    => '082-E',
                'message' => 'failed to delete practice plan',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
