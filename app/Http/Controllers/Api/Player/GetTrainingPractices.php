<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Concerns\PracticeTypes;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\WeightBallPractice;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            $lineupPracticeIds = PracticeLineUp::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();

            $data = Practice::with([
                'team', 'longToss' => function ($query): void {
                    $query->where('user_id', '=', auth()->id());
                }, 'exitVelocity' => function ($query): void {
                    $query->where('user_id', '=', auth()->id());
                }, 'weightBall' => function ($query): void {
                    $query->where('user_id', '=', auth()->id());
                }
            ])
                ->where('type', '=', PracticeTypes::TRAINING->value)
                ->where(function ($query) use ($practicesId, $lineupPracticeIds): void {
                    $query->where('user_id', '=', auth()->id())
                        ->orWhereIn('id', $practicesId)
                        ->orWhereIn('id', $lineupPracticeIds);
                })
                ->paginate();
            $response = [
                'code' => '058',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (AuthenticationException|AuthorizationException|ValidationException|HttpException $exception) {
            // Auth/validation failures belong to the framework's exception handler.
            throw $exception;
        } catch (Exception $exception) {
            Log::error('Failed to load training practice sessions', ['exception' => $exception]);
            return response()->json([
                'code' => '058-E',
                'message' => 'Unable to load sessions',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
