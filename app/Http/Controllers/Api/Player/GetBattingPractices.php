<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\BattingPracticeResult;
use App\Models\Practice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetBattingPractices extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $practicesId = BattingPracticeResult::where('is_in_match', '=', false)
                ->where('batter_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();

            $data = Practice::with('batting', 'team')->where('user_id', '=', auth()->id())
                ->orWhereIn('id', $practicesId)
                ->paginate();
            if(0 === $data->count()) {
                throw new NotFound();
            }
            $response = [
                'code' => '049',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '049-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
