<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\WeightBallPractice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTrainingPractices extends Controller
{
    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $practicesIdLong = LongTossPractice::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();
            $practicesIdWeight = WeightBallPractice::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();
            $practicesIdExit = ExitVelocityPractice::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();
            $practicesId = array_merge($practicesIdLong, $practicesIdExit, $practicesIdWeight);

            $data = Practice::with([
                'team', 'longToss' => function ($query): void {
                    $query->where('user_id', '=', auth()->id());
                }, 'exitVelocity' => function ($query): void {
                    $query->where('user_id', '=', auth()->id());
                }, 'weightBall' => function ($query): void {
                    $query->where('user_id', '=', auth()->id());
                }
            ])
                ->where('user_id', '=', auth()->id())
                ->orWhereIn('id', $practicesId)
                ->paginate();
            if (0 === $data->count()) {
                throw new NotFound();
            }
            $response = [
                'code' => '058',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '058-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
