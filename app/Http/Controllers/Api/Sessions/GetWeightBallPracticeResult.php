<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\WeightBallPractice;
use App\Utils\Helper;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetWeightBallPracticeResult extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $result = WeightBallPractice::with('profile')
                ->where('practice_id', $request->practice)
                ->orderBy('sort')
                ->get();
            $sets = Helper::getSets($result);
            if (0 === $result->count()) {
                throw new NotFound();
            }

            $count = $result->count();
            // Per-weight velocity aggregates computed server-side so the app and
            // web render identical numbers (single source of truth).
            $velocity = Helper::weightBallVelocityByWeight($result);
            $response = [
                'code' => '027',
                'message' => 'result practices',
                'status' => 'success',
                'data' => [
                    'count'=>$count,
                    'ball_x_ball'=>$result->sortBy('sort')->sortByDesc('set')->values(),
                    'sets'=>$sets,
                    'velocity_by_weight'=>$velocity['velocity_by_weight'],
                    'velocity_by_weight_by_player'=>$velocity['velocity_by_weight_by_player'],
                    'team_max_velo'=>$velocity['team_max_velo'],
                ],
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '027-E',
                'message' => 'Not Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
