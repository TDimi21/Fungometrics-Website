<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FitnessPlayerResource;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\LongTossPractice;
use App\Models\PlayerFitness;
use App\Models\WeightBallPractice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class ScoresStatisticPlayers extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $player = $request->player;
            $batting = BattingPracticeResult::where('batter_id', $player);
            $cage = CagePracticeResult::where('user_id', $player);
            $bullpen = BullpenPracticeResult::where('pitcher_id', $player);
            $exitVelocityBatting = $batting->pluck('velocity')->sortDesc();
            $exitVelocityCage = $cage->pluck('launch_angle_velocity')->sortDesc();
            $resultMaxExitVelocity = $exitVelocityBatting->merge($exitVelocityCage)->take(10)->toArray();
            $maxDistance = collect()->merge($cage->pluck('distance_travel')
                ->sortDesc()->take(10)->toArray());
            $maxFastBall = collect()->merge(
                $bullpen->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                    ->pluck('miles_per_hour')
                    ->sortDesc()->take(10)
            )->toArray();
            $maxLongToss = collect()->merge(LongTossPractice::where('user_id', $player)
                ->pluck('distance')->sortDesc()->take(10))->toArray();
            $maxStrike = collect()->merge($bullpen->where('is_strike', true)->pluck('miles_per_hour')
                ->sortDesc()->take(10))->toArray();
            $maxWeightBall = collect()->merge(WeightBallPractice::where('user_id', $player)
                ->pluck('velocity')->sortDesc()->take(10))->toArray();
            $playerFitness = PlayerFitness::where('user_id', $player);
            $metricLog = $playerFitness->get()->sortByDesc('fitness_date')
                ->take(10);
            $max = [
                "bench_press" => $playerFitness->max('bench_press')??0,
                "front_squat" => $playerFitness->max('front_squat')??0,
                "back_squat" => $playerFitness->max('back_squat')??0,
                "power_clean" => $playerFitness->max('power_clean')??0,
                "dead_lift" => $playerFitness->max('dead_lift')??0,
                "yd_40_dash" => $playerFitness->max('yd_40_dash')??0,
                "yd_60_dash" => $playerFitness->max('yd_60_dash')??0,
            ];

            $avg = [
                "bench_press"=> number_format($playerFitness->pluck('bench_press')->avg()??0, 2),
                "front_squat"=> number_format($playerFitness->pluck('front_squat')->avg()??0, 2),
                "back_squat"=> number_format($playerFitness->pluck('back_squat')->avg()??0, 2),
                "power_clean"=> number_format($playerFitness->pluck('power_clean')->avg()??0, 2),
                "dead_lift"=>number_format($playerFitness->pluck('dead_lift')->avg()??0, 2),
                "yd_40_dash"=> number_format($playerFitness->pluck('yd_40_dash')->avg()??0, 2),
                "yd_60_dash"=> number_format($playerFitness->pluck('yd_60_dash')->avg()??0, 2),
            ];
            $response = [
                'code' => '044',
                'message' => '',
                'status' => 'success',
                'data' => [
                    'top' => [
                        'max_exit_velocity' => $resultMaxExitVelocity,
                        'max_distance' => $maxDistance,
                        'max_fast_ball' => $maxFastBall,
                        'max_long_toss' => $maxLongToss,
                        'max_strike' => $maxStrike,
                        'max_weight_ball' => $maxWeightBall
                    ],
                    'metrics'=>FitnessPlayerResource::collection($metricLog),
                    'max'=> $max,
                    'avg'=> $avg
                ],
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '044-E',
                'message' => 'not get data',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
