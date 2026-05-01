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

            $count = LiveABPracticeResult::where('practice_id', '=', $request->practice_id)->count();

            DB::beginTransaction();
            $sortValue = $count++;
            $requestData = $request->validated();
            $zone = $requestData['type_of_hit'] === BattingTrajectory::HIT_BY_PITCH->value ? 'B' : $requestData['zone'];
            $batting = (new CreateServiceData(new BattingPracticeResult()))->handle([
                'practice_id' => $requestData['practice_id'],
                'team_id' => $requestData['batting']['team_id'],
                'batter_id' => $requestData['batting']['batter_id'],
                'is_contact' => $requestData['is_contact'],
                'pitch_location' => $requestData['pitch_location'],
                'quality_of_contact' => $requestData['batting']['quality_of_contact'],
                'type_of_hit' => $requestData['type_of_hit'],
                'field_mark' => $requestData['batting']['field_mark'],
                'pitch_mark' => $requestData['pitch_mark'],
                'field_direction' => $requestData['batting']['field_direction'],
                'velocity' => $requestData['batting']['velocity'],
                'sort' => $sortValue,
                'is_in_match' => true,
                'zone' => $zone
            ]);
            $pitching = (new CreateServiceData(new BullpenPracticeResult()))->handle([
                'practice_id' => $requestData['practice_id'],
                'team_id' => $requestData['pitching']['team_id'],
                'pitcher_id' => $requestData['pitching']['pitcher_id'],
                'pitch_side' => $requestData['pitch_location'],
                'pitch_mark' => $requestData['pitch_mark'],
                'isStrike' => false,
                'miles_per_hour' => $requestData['pitching']['miles_per_hour'],
                'type_throw' => $requestData['pitching']['type_throw'],
                'trajectory' => $requestData['type_of_hit'],
                'is_in_match' => true,
                'sort' => $sortValue,
                'zone' => $zone

            ]);

            $isHit = false;
            $isStrike = false;
            $isBall = false;

            $count_b_s = null;
            $numBall = $requestData['turn']['ball'] ?? 0;
            $numStrike = $requestData['turn']['strike'] ?? 0;

            $scenario = "{$numBall}-{$numStrike}";


            $isHit = $this->validateHit($requestData['type_of_hit']);

            if ($requestData['type_of_hit'] === BattingTrajectory::HIT_BY_PITCH->value) {
                $isStrike = false;
                $isBall = false;
                $requestData['bases'] = 6;
                $numBall = $requestData['turn']['ball'];
                $numStrike = $requestData['turn']['strike'];
            } else {
                if ($requestData['zone'] === SidesPitchPosition::ZONE_BALL->value) {
                    $isBall = true;
                    if (BattingTrajectory::FOUL->value === $requestData['type_of_hit']) {
                        $isBall = false;
                        if ($numStrike < 2) {
                            $numStrike++;
                            $numBall = $numBall;
                        } else {
                            $numStrike = $numStrike;
                            $numBall = $numBall;
                        }
                    } elseif (BattingTrajectory::SWING_MISS->value === $requestData['type_of_hit']) {
                        $isBall = false;
                        $numBall = $numBall;
                        $numStrike++;
                    } else {
                        $numBall++;
                    }

                } else {
                    $isStrike = true;
                    if (BattingTrajectory::FOUL->value === $requestData['type_of_hit']) {
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
                BattingTrajectory::FOUL->value === $requestData['type_of_hit']) {
                $count_b_s = '3-2';

            } elseif (
                '0-2' === $scenario &&
                BattingTrajectory::FOUL->value === $requestData['type_of_hit']) {
                $count_b_s = '0-2';
            } else {
                $count_b_s = $scenario;
            }


            if (3 === $numStrike
                || 4 === $numBall
                || BattingTrajectory::HIT_BY_PITCH->value === $requestData['type_of_hit']
                || BattingTrajectory::LINE_DRIVE->value === $requestData['type_of_hit']
                || BattingTrajectory::GROUND_BALL->value === $requestData['type_of_hit']
                || BattingTrajectory::FLY_BALL->value === $requestData['type_of_hit']
            ) {
                $requestData['turn']['is_over'] = true;
            } else {
                $requestData['turn']['is_over'] = false;
            }

            $result = (new CreateServiceData(new LiveABPracticeResult()))->handle([
                'practice_id' => $requestData['practice_id'],
                'turn' => $requestData['turn']['turn'],
                'turn_pitches' => $requestData['turn']['pitches'],
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
                'count_b_s' => $count_b_s
            ]);

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
            Log::error($exception->getMessage());

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

        };
    }
}
