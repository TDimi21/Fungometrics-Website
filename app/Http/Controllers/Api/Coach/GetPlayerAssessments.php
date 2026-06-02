<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\PlayerAssessment;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerAssessments extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $playerId = (string) $request->route('player');

            $assessments = PlayerAssessment::with('profile')
                ->where('user_id', $playerId)
                ->orderByDesc('assessment_date')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            return response()->json([
                'code'    => '061',
                'message' => 'assessments for player ' . $playerId,
                'status'  => 'success',
                'data'    => $assessments,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $e) {
            Log::error('GetPlayerAssessments: ' . $e->getMessage());
            return response()->json([
                'code'    => '061-E',
                'message' => 'failed to fetch assessments',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
