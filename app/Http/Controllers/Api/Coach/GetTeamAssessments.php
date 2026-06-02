<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\PlayerAssessment;
use App\Models\Profile;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTeamAssessments extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teamId = (string) $request->route('team');
            $returnAll = $request->boolean('all');

            $baseQuery = PlayerAssessment::with('profile')
                ->where('team_id', $teamId)
                ->orderByDesc('assessment_date')
                ->orderByDesc('created_at')
                ->limit($returnAll ? 200 : 80);

            // default: latest assessment per player on this team
            $assessments = $returnAll
                ? $baseQuery->get()->values()
                : $baseQuery->get()
                    ->groupBy('user_id')
                    ->map(fn ($group) => $group->first())
                    ->values();

            return response()->json([
                'code'    => '062',
                'message' => 'assessments for team ' . $teamId,
                'status'  => 'success',
                'data'    => $assessments,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $e) {
            Log::error('GetTeamAssessments: ' . $e->getMessage());
            return response()->json([
                'code'    => '062-E',
                'message' => 'failed to fetch team assessments',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
