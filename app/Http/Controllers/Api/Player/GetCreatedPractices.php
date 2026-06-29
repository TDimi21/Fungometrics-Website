<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

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
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'code' => '059-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
