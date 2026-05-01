<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\BullpenPracticeResult;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetBullpenPracticeResults extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = BullpenPracticeResult::with('profile')
                ->where('practice_id', $request->practice)
                ->orderByDesc('sort')
                ->get();
            if($request->player) {
                $data =  $data->filter(fn ($item) => $item->pitcher_id === auth()->user()->id)->flatten();
            }
            if (0 === $data->count()) {
                throw new NotFound();
            }
            $byPitchSide = $data->groupBy('pitch_side')->all();
            $byThrowType = $data->groupBy('type_throw')->all();
            $byTrajectory = $data->groupBy('trajectory')->all();
            $byPlayer = $data->groupBy('pitcher_id')->all();
            $response = [
                'code' => '020',
                'message' => 'data for practice: '.$request->practice,
                'status' => 'success',
                'data' => [
                    'count' => $data->count(),
                    'ball_x_ball' => $data,
                    'by_player' => $byPlayer,
                    'by_throw_type' => $byThrowType,
                    'by_pitch_side' => $byPitchSide,
                    'by_trajectory' => $byTrajectory
                ],
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '020-E',
                'message' => 'Not Data Found for practice '.$request->practice,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
