<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\LiveABRequest;
use App\Http\Resources\Api\LiveABResource;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\LiveABPracticeResult;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveLiveABResultPractice extends Controller
{
    /**
     * @param  LiveABRequest  $request
     * @return JsonResponse
     */
    public function __invoke(LiveABRequest $request): JsonResponse
    {
        try {

            $hasBattingZoneColumn = Schema::hasColumn('batting_practice_results', 'zone');
            $hasBullpenZoneColumn = Schema::hasColumn('bullpen_practice_results', 'zone');
            $hasTurnPitchesColumn = Schema::hasColumn('live_a_b_practice_results', 'turn_pitches');
            $hasCountBsColumn = Schema::hasColumn('live_a_b_practice_results', 'count_b_s');

            $count = LiveABPracticeResult::where('practice_id', '=', $request->practice_id)->count();

            DB::beginTransaction();
            $sortValue = $count++;
            $requestData = $request->validated();
            $typeOfHit = $requestData['type_of_hit'] ?? BattingTrajectory::TAKE->value;
            $fieldMark = isset($requestData['batting']['field_mark']) && $requestData['batting']['field_mark'] !== null
                ? (int) $requestData['batting']['field_mark']
                : 0;
            $pitchType = $requestData['pitching']['type_throw'] ?? 'FB';
            $zone = $typeOfHit === BattingTrajectory::HIT_BY_PITCH->value
                ? SidesPitchPosition::ZONE_BALL->value
                : ($requestData['zone'] ?? SidesPitchPosition::ZONE_BALL->value);
            $battingPayload = [
                'practice_id' => $requestData['practice_id'],
                'team_id' => $requestData['batting']['team_id'],
                'batter_id' => $requestData['batting']['batter_id'],
                'is_contact' => $requestData['is_contact'],
                'pitch_location' => $requestData['pitch_location'],
                'quality_of_contact' => $requestData['batting']['quality_of_contact'],
                'type_of_hit' => $typeOfHit,
                'field_mark' => $fieldMark,
                'pitch_mark' => $requestData['pitch_mark'],
                'field_direction' => $requestData['batting']['field_direction'],
                'velocity' => $requestData['batting']['velocity'],
                'sort' => $sortValue,
                'is_in_match' => true,
            ];

            if ($hasBattingZoneColumn) {
                $battingPayload['zone'] = $zone;
            }

            $batting = (new CreateServiceData(new BattingPracticeResult()))->handle($battingPayload);

            $pitchingPayload = [
                'practice_id' => $requestData['practice_id'],
                'team_id' => $requestData['pitching']['team_id'],
                'pitcher_id' => $requestData['pitching']['pitcher_id'],
                'pitch_side' => $requestData['pitch_location'],
                'pitch_mark' => $requestData['pitch_mark'],
                'is_strike' => false,
                'miles_per_hour' => $requestData['pitching']['miles_per_hour'],
                'type_throw' => $pitchType,
                'trajectory' => $typeOfHit,
                'is_in_match' => true,
                'sort' => $sortValue,
            ];

            if ($hasBullpenZoneColumn) {
                $pitchingPayload['zone'] = $zone;
            }

            $pitching = (new CreateServiceData(new BullpenPracticeResult()))->handle($pitchingPayload);

            $isHit = false;
            $isStrike = false;
            $isBall = false;

            $count_b_s = null;
            $numBall = $requestData['turn']['ball'] ?? 0;
            $numStrike = $requestData['turn']['strike'] ?? 0;

            $scenario = "{$numBall}-{$numStrike}";


            $isHit = $this->validateHit($typeOfHit);

            if ($typeOfHit === BattingTrajectory::HIT_BY_PITCH->value) {
                $isStrike = false;
                $isBall = false;
                $requestData['bases'] = 6;
                $numBall = $requestData['turn']['ball'];
                $numStrike = $requestData['turn']['strike'];
            } else {
                if ($zone === SidesPitchPosition::ZONE_BALL->value) {
                    $isBall = true;
                    if (BattingTrajectory::FOUL->value === $typeOfHit) {
                        $isBall = false;
                        if ($numStrike < 2) {
                            $numStrike++;
                            $numBall = $numBall;
                        } else {
                            $numStrike = $numStrike;
                            $numBall = $numBall;
                        }
                    } elseif (BattingTrajectory::SWING_MISS->value === $typeOfHit) {
                        $isBall = false;
                        $numBall = $numBall;
                        $numStrike++;
                    } else {
                        $numBall++;
                    }

                } else {
                    $isStrike = true;
                    if (BattingTrajectory::FOUL->value === $typeOfHit) {
                        if ($numStrike < 2) {
                            $numStrike++;
                            $numBall = $numBall;
                        } else {
                            $numStrike = $numStrike;
                            $numBall = $numBall;
                        }
                    } else {
                        $numStrike++;
                    }

                }

            }

            if ('0-0' === $scenario) {
                $count_b_s = '0-0';
            } elseif (
                '3-2' === $scenario &&
                BattingTrajectory::FOUL->value === $typeOfHit) {
                $count_b_s = '3-2';

            } elseif (
                '0-2' === $scenario &&
                BattingTrajectory::FOUL->value === $typeOfHit) {
                $count_b_s = '0-2';
            } else {
                $count_b_s = $scenario;
            }


            if (3 === $numStrike
                || 4 === $numBall
                || BattingTrajectory::HIT_BY_PITCH->value === $typeOfHit
                || BattingTrajectory::LINE_DRIVE->value === $typeOfHit
                || BattingTrajectory::GROUND_BALL->value === $typeOfHit
                || BattingTrajectory::FLY_BALL->value === $typeOfHit
            ) {
                $requestData['turn']['is_over'] = true;
            } else {
                $requestData['turn']['is_over'] = false;
            }

            $liveAbPayload = [
                'practice_id' => $requestData['practice_id'],
                'turn' => $requestData['turn']['turn'],
                'turn_strike' => $numStrike,
                'turn_ball' => $numBall,
                'turn_is_over' => $requestData['turn']['is_over'],
                'sort' => $sortValue,
                'is_hit' => $isHit,
                'is_strike' => $isStrike,
                'is_ball' => $isBall,
                'bases' => $requestData['bases'],
                'batting_result_id' => $batting->id,
                'pitching_result_id' => $pitching->id,
            ];

            if ($hasTurnPitchesColumn) {
                $liveAbPayload['turn_pitches'] = $requestData['turn']['pitches'];
            }

            if ($hasCountBsColumn) {
                $liveAbPayload['count_b_s'] = $count_b_s;
            }

            // Backward compatibility: some environments may not have the
            // 2026 play-result columns migrated yet.
            $hasPlayResultColumns = Schema::hasColumns('live_a_b_practice_results', [
                'play_result',
                'outs_recorded',
                'runs_scored',
                'rbi',
                'is_safe',
                'sac_fly',
                'sac_bunt',
                'runners_before',
                'runners_after',
            ]);

            if ($hasPlayResultColumns) {
                $liveAbPayload = array_merge($liveAbPayload, [
                    // Game-engine play result fields (sent from mobile Ball-In-Play screen)
                    'play_result'    => $requestData['play_result'] ?? null,
                    'outs_recorded'  => $requestData['outs_recorded'] ?? 0,
                    'runs_scored'    => $requestData['runs_scored'] ?? 0,
                    'rbi'            => $requestData['rbi'] ?? 0,
                    'is_safe'        => $requestData['is_safe'] ?? false,
                    'sac_fly'        => $requestData['sac_fly'] ?? false,
                    'sac_bunt'       => $requestData['sac_bunt'] ?? false,
                    'runners_before' => isset($requestData['runners_before']) ? json_encode($requestData['runners_before']) : null,
                    'runners_after'  => isset($requestData['runners_after'])  ? json_encode($requestData['runners_after'])  : null,
                ]);
            }

            $result = (new CreateServiceData(new LiveABPracticeResult()))->handle($liveAbPayload);

            DB::commit();
            $response = [
                'code' => '010',
                'message' => 'save liveab result',
                'status' => 'success',
                'data' => new LiveABResource($result),
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollback();
            $response = [
                'code' => '010',
                'message' => 'error to save liveab result ',
                'status' => 'error',
                'data' => []
            ];
            Log::error('SaveLiveABResultPractice error', [
                'message' => $exception->getMessage(),
                'practice_id' => $request->practice_id,
                'payload' => $request->validated(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ]);

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function validateHit($type): bool
    {
        return match ($type) {
            BattingTrajectory::FLY_BALL->value,
            BattingTrajectory::POP_FLY->value,
            BattingTrajectory::GROUND_BALL->value,
            BattingTrajectory::LINE_DRIVE->value => true,

            BattingTrajectory::SWING_MISS->value,
            BattingTrajectory::TAKE->value,
            BattingTrajectory::FOUL->value,
            BattingTrajectory::HIT_BY_PITCH->value => false,
            default => false,

        };
    }
}
