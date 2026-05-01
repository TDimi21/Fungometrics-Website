<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\BullpenPracticeResult;
use App\Models\Practice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetBullpenPractices extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $practicesId = BullpenPracticeResult::where('pitcher_id', '=', auth()->id())
                ->pluck('practice_id')->unique()
                ->all();

            $data = Practice::with('bullpen', 'team')->where('user_id', '=', auth()->id())
                ->orWhereIn('id', $practicesId)
                ->paginate();
            if(0 === $data->count()) {
                throw new NotFound();
            }
            $response = [
                'code' => '050',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '050-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
