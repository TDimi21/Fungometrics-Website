<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\PlayerAssessment;
use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Services\Access\AssessmentResponsePolicy;
use BackedEnum;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerAssessments extends Controller
{
    public function __invoke(Request $request, AssessmentResponsePolicy $policy): JsonResponse
    {
        try {
            $playerId = (string) $request->route('player');
            $type = $request->user()->type instanceof BackedEnum ? $request->user()->type->value : $request->user()->type;
            $mayRead = ('player' === $type && $request->user()->id === $playerId)
                || ('coach' === $type && CoachTeam::query()->where('coach_id', $request->user()->id)
                    ->whereIn('team_id', PlayerTeam::query()->where('user_id', $playerId)->select('team_id'))->exists());
            if ( ! $mayRead) {
                return response()->json(['message' => 'These assessments are not available to this user.'], HttpCodes::HTTP_FORBIDDEN);
            }

            $assessments = PlayerAssessment::with('profile')
                ->where('user_id', $playerId)
                ->orderByDesc('assessment_date')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            return response()->json([
                'code'    => '061',
                'message' => 'assessments for player '.$playerId,
                'status'  => 'success',
                'data'    => $policy->shape($assessments, $request->user()),
            ], HttpCodes::HTTP_OK);

        } catch (Exception $e) {
            Log::error('GetPlayerAssessments: '.$e->getMessage());
            return response()->json([
                'code'    => '061-E',
                'message' => 'failed to fetch assessments',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
