<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GetCreatedPractices extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $lineupPracticeIds = PracticeLineUp::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();

            $data = Practice::with([
                'batting',
                'bullpen',
                'cage',
                'cageMeta',
                'longToss',
                'exitVelocity',
                'weightBall',
                'team',
            ])
                ->where(function ($query) use ($lineupPracticeIds): void {
                    $query->where('user_id', '=', auth()->id())
                        ->orWhereIn('id', $lineupPracticeIds);
                })
                ->orderByDesc('updated_at')
                ->paginate();

            return response()->json([
                'code' => '059',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ], HttpCodes::HTTP_OK);
        } catch (AuthenticationException|AuthorizationException|ValidationException|HttpException $exception) {
            // Auth/validation failures belong to the framework's exception handler.
            throw $exception;
        } catch (Exception $exception) {
            Log::error('Failed to load created practice sessions', ['exception' => $exception]);

            return response()->json([
                'code' => '059-E',
                'message' => 'Unable to load sessions',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
