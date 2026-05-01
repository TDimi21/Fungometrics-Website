<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\LongTossPractice;
use App\Utils\Helper;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetLongTossPracticeResult extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $result = LongTossPractice::with('profile')
                ->where('practice_id', $request->practice)
                ->orderBy('sort')
                ->get();
            $sets = Helper::getSets($result);

            if (0 === $result->count()) {
                throw new NotFound();
            }

            $count = $result->count();
            $response = [
                'code' => '026',
                'message' => '',
                'status' => 'success',
                'data' => [
                    'count' => $count,
                    'ball_x_ball' => $result->sortByDesc('set')->sortByDesc('sort'),
                    'sets'=>$sets
                ]
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '026-E',
                'message' => 'Not Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
