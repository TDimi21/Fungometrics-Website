<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\BattingPracticeResult;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetBattingPracticeResults extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = BattingPracticeResult::with('profile')
                ->where('practice_id', $request->practice)
                ->orderByDesc('sort')
                ->get();
            if($request->player) {
                $data =  $data->filter(fn ($item) => $item->batter_id === auth()->user()->id)->flatten();
            }
            if (0 === $data->count()) {
                throw  new NotFound();
            }
            $count = $data->count();
            $byQuality = $data->groupBy('quality_of_contact')->all();
            $byFieldLocation = $data->groupBy('field_direction')->all();
            $byTrajectory = $data->groupBy('type_of_hit')->all();
            $groupByPlayer = $data->groupBy('batter_id')->all();
            $response = [
                'code' => '019',
                'message' => 'statistics for training id '.$request->practice,
                'status' => 'success',
                'data' => [
                    'count' => $count,
                    'ball_x_ball' => $data,
                    'by_contact' => $byQuality,
                    'by_field_location' => $byFieldLocation,
                    'by_trajectory' => $byTrajectory,
                    'by_player' => $groupByPlayer
                ]
                ,];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = ['code' => '019-E', 'message' => 'not get data from practice '.$request->practice, 'status' => 'error', 'data' => [],];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
