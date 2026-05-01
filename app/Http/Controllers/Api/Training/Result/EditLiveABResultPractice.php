<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\LiveABEditRequest;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\LiveABPracticeResult;
use App\Services\ListServiceData;
use App\Services\UpdateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class EditLiveABResultPractice extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(LiveABEditRequest  $request, $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();

            $requestData = $request->validated();

            $liveab = (new ListServiceData(new LiveABPracticeResult()))->findByUuid($request->uuid)->load(['practice', 'batting.profile', 'pitching.profile']);

            $batting = (new UpdateServiceData(new BattingPracticeResult()))->handle($liveab->batting->id, [
                'is_contact' => $requestData['is_contact'],
                'batter_id'=>$requestData['batting']['batter_id'],
                'pitch_location' => $requestData['pitch_location'],
                'quality_of_contact' => $requestData['batting']['quality_of_contact'],
                'type_of_hit' => $requestData['type_of_hit'],
                'field_mark' => $requestData['batting']['field_mark'],
                'pitch_mark' => $requestData['pitch_mark'],
                'field_direction' => $requestData['batting']['field_direction'],
                'velocity' => $requestData['batting']['velocity'],
                //'sort' => $requestData['sort'],
                'is_in_match' => true,
                'zone'=>$request['zone']
            ]);
            $pitching = (new UpdateServiceData(new BullpenPracticeResult()))->handle($liveab->pitching->id, [
                'pitch_side' => $requestData['pitch_location'],
                'pitcher_id'=>$requestData['pitching']['pitcher_id'],
                'pitch_mark' => $requestData['pitch_mark'],
                'isStrike' => false,
                'miles_per_hour' => $requestData['pitching']['miles_per_hour'],
                'type_throw' => $requestData['pitching']['type_throw'],
                'trajectory' => $requestData['type_of_hit'],
                'is_in_match' => true,
                // 'sort' => $requestData['sort'],
                'zone'=>$request['zone']

            ]);

            $isHit = false;
            $isStrike = false;
            $isBall = false;

            $numBall = $requestData['turn']['ball'] ?? 0;
            $numStrike = $requestData['turn']['strike'] ?? 0;

            if ($requestData['batting']['quality_of_contact'] !== ContactQuality::NONE->value) {
                $isHit = true;
            }
            if ($requestData['batting']['quality_of_contact'] === ContactQuality::MISS_FOUL->value) {
                $isHit = false;
            }

            if ($requestData['type_of_hit'] === BattingTrajectory::TAKE->value) {
                $isBall = true;
                $numBall++;
            }

            if ($numStrike <= 2 && $requestData['batting']['quality_of_contact'] === ContactQuality::MISS_FOUL->value) {
                if (2 !== $numStrike) {
                    $numStrike++;
                }

                $isStrike = true;
            }
            if ($numStrike <= 2 && $requestData['batting']['quality_of_contact'] ===
              BattingTrajectory::SWING_MISS->value) {
                $numStrike++;
                $isStrike = true;
            }

            $result = (new UpdateServiceData(new LiveABPracticeResult()))->handle($uuid, [
                'turn' => $requestData['turn']['turn'],
                'turn_pitches' => $requestData['turn']['pitches'],
                'turn_strike' => $numStrike,
                'turn_ball' => $numBall,
                'turn_is_over' => $requestData['turn']['is_over'],
                //'sort' => $requestData['sort'],
                'isHit' => $isHit,
                'isStrike' => $isStrike,
                'isBall' => $isBall,
                'bases' => $requestData['bases'],
                'count_b_s' => "{$numStrike}-{$numBall}"
            ]);
            DB::commit();
            $response = [
                'code' => 'code',
                'message' => 'Edit liveab success',
                'status' => 'success',
                'data' => $result->load(['practice', 'batting.profile', 'pitching.profile']),
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
