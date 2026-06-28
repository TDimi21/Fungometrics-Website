<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PracticePlan;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPracticePlans extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $plans = PracticePlan::whereIn('team_id', $teamIds)
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'code'    => '080',
                'message' => 'list of practice plans',
                'status'  => 'success',
                'data'    => $plans,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetPracticePlans: ' . $e->getMessage());

            return response()->json([
                'code'    => '080-E',
                'message' => 'failed to fetch practice plans',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
