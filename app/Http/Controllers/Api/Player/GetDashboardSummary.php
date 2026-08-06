<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Services\Statistics\PlayerDashboardSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetDashboardSummary extends Controller
{
    public function __invoke(Request $request, PlayerDashboardSummaryService $service): JsonResponse
    {
        $userId = (string) auth()->id();

        $data = Cache::remember(
            PlayerDashboardSummaryService::cacheKey($userId),
            PlayerDashboardSummaryService::CACHE_TTL_SECONDS,
            fn (): array => $service->build($userId)
        );

        return response()->json([
            'code' => '075',
            'message' => '',
            'status' => 'success',
            'data' => $data,
        ], HttpCodes::HTTP_OK);
    }
}
