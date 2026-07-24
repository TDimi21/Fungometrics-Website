<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\CageRequest;
use App\Models\CagePracticeResult;
use App\Models\Practice;
use App\Services\Cage\CageDistanceService;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Throwable;

class SaveCageResultPractice extends Controller
{
    /**
     * @param CageRequest $request
     * @return JsonResponse
     */
    public function __invoke(CageRequest $request, CageDistanceService $distanceService): JsonResponse
    {
        try {
            DB::beginTransaction();
            $count = CagePracticeResult::where('practice_id', '=', $request->practice_id)
                ->count();
            $data = $request->validated();

            // Every cage swing must carry its team + user linkage regardless of
            // which cage mode (regular / game / practice) scored it. The app only
            // sends team_id when a team happens to be loaded, so backfill it from
            // the session's practice — otherwise team-level cage stats silently
            // drop swings saved with a null team_id (different totals per screen).
            if (empty($data['team_id']) || empty($data['user_id'])) {
                $practice = Practice::query()->find($data['practice_id']);
                if ($practice) {
                    if (empty($data['team_id']) && ! empty($practice->team_id)) {
                        $data['team_id'] = (string) $practice->team_id;
                    }
                    if (empty($data['user_id']) && ! empty($practice->user_id)) {
                        $data['user_id'] = (string) $practice->user_id;
                    }
                }
            }

            $data['sort']= $count++;
            $result = (new CreateServiceData(new CagePracticeResult()))->handle($data);

            if (config('fmtrx.cage_distance_v2_enabled')) {
                $this->attachDistanceV2($result, $data, $distanceService);
            }

            DB::commit();

            $teamId = $result->team_id ?? null;
            if ($teamId) {
                Cache::forget("last_sessions_{$teamId}");
                Cache::forget("performance_overview_{$teamId}");
                Cache::forget("dashboard_graphics_{$teamId}");
            }

            $response = [
                'code' => '014',
                'message' => 'save cage practice',
                'status' => 'success',
                'data' => $result,
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '014-E',
                'message' => 'error to save cage practice',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Computes the FMTRX Cage Distance Model v2 estimate alongside the
     * existing (unchanged) distance_travel and stores it in the nullable v2
     * columns. Any failure here (missing inputs, a service exception, etc.)
     * is logged and swallowed — it must never block or roll back the
     * original cage result save.
     *
     * @param  array<string,mixed>  $data
     */
    private function attachDistanceV2(CagePracticeResult $result, array $data, CageDistanceService $distanceService): void
    {
        try {
            $ev = isset($data['launch_angle_velocity']) ? (float) $data['launch_angle_velocity'] : null;
            $la = isset($data['launch_angle']) ? (float) $data['launch_angle'] : null;
            $sa = isset($data['spray_angle']) ? (float) $data['spray_angle'] : null;

            $v2 = $distanceService->estimate([
                'exit_velocity_mph' => $ev,
                'launch_angle_deg' => $la,
                'spray_angle_deg' => $sa,
                'ground_ball' => $data['ground_ball'] ?? null,
                'mode' => 'standardized',
            ]);

            $result->update([
                'distance_model_version' => $v2['model_version'],
                'distance_model_meta' => [
                    'exit_velocity_mph' => $ev,
                    'launch_angle_deg' => $la,
                    'spray_angle_deg' => $sa,
                    'mode' => 'standardized',
                    'spin_source' => $v2['inputs_used']['spin_source'] ?? null,
                    'assumptions' => $v2['assumptions'] ?? [],
                    'hang_time_seconds' => $v2['hang_time_seconds'] ?? null,
                    'maximum_height_ft' => $v2['maximum_height_ft'] ?? null,
                    'landing_x_ft' => $v2['landing_x_ft'] ?? null,
                    'landing_y_ft' => $v2['landing_y_ft'] ?? null,
                ],
                'estimated_carry_v2' => $v2['estimated_carry_ft'] ?? null,
                'estimated_carry_low_v2' => $v2['carry_low_ft'] ?? null,
                'estimated_carry_high_v2' => $v2['carry_high_ft'] ?? null,
                'distance_confidence_v2' => $v2['confidence'] ?? null,
            ]);
        } catch (Throwable $exception) {
            Log::error('CageDistanceService v2 estimate failed: ' . $exception->getMessage());
        }
    }
}
