<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\CagePracticeResult;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetCagePracticeResults extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = CagePracticeResult::with('profile')
                ->where('practice_id', $request->practice)
                ->orderByDesc('sort')
                ->get();
            if($request->player) {
                $data =  $data->filter(fn ($item) => $item->user_id === auth()->user()->id)->flatten();
            }
            if (0 === $data->count()) {
                throw new NotFound();
            }

            $byPlayer = $data->groupBy('user_id')->all();
            $byFlyBall = $data->whereBetween('launch_angle', config('constants.launchAngle.fb'))->all();
            $byLineDrive = $data->whereBetween('launch_angle', config('constants.launchAngle.ld'))->all();
            $byPopFly = $data->where('launch_angle', '>', config('constants.launchAngle.pf'))->all();
            $byGroundBall = $data->where('launch_angle', '<', config('constants.launchAngle.gb'))->all();

            $vMax = $data->where('launch_angle_velocity', '>=', config('constants.velocityAngle.v-max'))->toArray();
            $vHigh = $data->whereBetween('launch_angle_velocity', config('constants.velocityAngle.v-high'))->toArray();
            $vMid = $data->whereBetween('launch_angle_velocity', config('constants.velocityAngle.v-mid'))->toArray();
            $vLow= $data->whereBetween('launch_angle_velocity', config('constants.velocityAngle.v-low'))->toArray();
            $vMin = $data->where('launch_angle_velocity', '<', config('constants.velocityAngle.v-min'))->toArray();

            $response = [
                'code' => '038',
                'message' => '',
                'status' => 'success',
                'data' => [
                    'count'=>$data->count(),
                    'ball_x_ball'=>$data,
                    'by_player'=>$byPlayer,
                    'by_line_drive'=>$byLineDrive,
                    'by_fly_ball'=>$byFlyBall,
                    'by_pop_fly'=>$byPopFly,
                    'by_ground_ball'=>$byGroundBall,
                    'by_velocities'=>[
                        'max'=>$vMax,
                        'high'=>$vHigh,
                        'mid'=>$vMid,
                        'low'=>$vLow,
                        'min'=>$vMin
                    ],
                    "cage_meta" => $data[0]?->practice->cageMeta
                ],
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '038-E',
                'message' => 'Not Data Found ',
                'status' => 'error',
                'data' => [],
            ];

            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
