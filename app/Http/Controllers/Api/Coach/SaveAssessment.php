<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AssessmentRequest;
use App\Models\PlayerAssessment;
use App\Models\PlayerTeam;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveAssessment extends Controller
{
    public function __invoke(AssessmentRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // ── Compute scores server-side from percentiles ───────────────────
            $safe = fn ($k) => min(100, max(0, (int) ($data[$k] ?? 0)));

            $sq  = $safe('squat_percentile');
            $dl  = $safe('deadlift_percentile');
            $lng = $safe('lunge_percentile');
            $bp  = $safe('bench_press_percentile');
            $pu  = $safe('pull_up_percentile');
            $psh = $safe('push_up_percentile');
            $bj  = $safe('broad_jump_percentile');
            $vj  = $safe('vertical_jump_percentile');
            $sp  = $safe('sprint_10yd_percentile');
            $mb  = $safe('med_ball_rotational_percentile');
            $ev  = $safe('exit_velocity_percentile');
            $bs  = $safe('bat_speed_percentile');

            $lowerBody       = (int) round($sq * 0.60 + $dl * 0.25 + $lng * 0.15);
            $upperBody       = (int) round($bp * 0.50 + $pu * 0.25 + $psh * 0.25);
            $explosivePower  = (int) round($bj * 0.40 + $vj * 0.40 + $sp * 0.20);
            $rotationalPower = (int) round($mb * 0.60 + $ev * 0.25 + $bs * 0.15);
            $strengthOverall = (int) round(
                $lowerBody      * 0.30 +
                $upperBody      * 0.20 +
                $explosivePower * 0.25 +
                $rotationalPower * 0.25
            );

            // Mobility: average of provided 0-10 fields scaled to 0-100
            $mobilityFields = ['hip_mobility', 'shoulder_mobility', 'ankle_mobility', 'hip_flexor_mobility', 'rotational_mobility'];
            $mobilityVals   = array_filter(array_map(fn ($f) => isset($data[$f]) ? (int) $data[$f] : null, $mobilityFields), fn ($v) => $v !== null);
            $mobilityOverall = count($mobilityVals) > 0
                ? (int) round((array_sum($mobilityVals) / count($mobilityVals)) * 10)
                : 0;

            // Combined overall
            $overall = $strengthOverall > 0 && $mobilityOverall > 0
                ? (int) round($strengthOverall * 0.70 + $mobilityOverall * 0.30)
                : max($strengthOverall, $mobilityOverall);

            $data = array_merge($data, [
                'strength_lower_body_score'    => $lowerBody,
                'strength_upper_body_score'    => $upperBody,
                'strength_explosive_score'     => $explosivePower,
                'strength_rotational_score'    => $rotationalPower,
                'strength_overall_score'       => $strengthOverall,
                'mobility_overall_score'       => $mobilityOverall,
                'overall_score'                => $overall,
            ]);

            $assessment = (new CreateServiceData(new PlayerAssessment()))->handle($data);

            // Bust relevant caches
            $teamIds = PlayerTeam::where('user_id', (string) $request->user_id)
                ->whereNotNull('team_id')
                ->pluck('team_id')
                ->unique()
                ->values();

            foreach ($teamIds as $teamId) {
                Cache::forget("player_cards_v3_{$teamId}");
                Cache::forget("player_dev_board_{$teamId}");
            }

            return response()->json([
                'code'    => '060',
                'message' => 'assessment saved for player ' . $request->user_id,
                'status'  => 'success',
                'data'    => $assessment,
            ], HttpCodes::HTTP_CREATED);

        } catch (Exception $e) {
            Log::error('SaveAssessment: ' . $e->getMessage());
            return response()->json([
                'code'    => '060-E',
                'message' => 'failed to save assessment',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
