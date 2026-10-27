<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\PlayerTeam;
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
        $teamId = $request->validate(['team_id' => 'nullable|uuid'])['team_id'] ?? null;
        if ($teamId) {
            abort_unless(PlayerTeam::query()->where('user_id', $userId)
                ->where('team_id', $teamId)->where('actual', true)->whereHas('team')->exists(), 403,
                'This team is no longer connected to your profile.');
        }

        $data = Cache::remember(
            PlayerDashboardSummaryService::cacheKey($userId, $teamId),
            PlayerDashboardSummaryService::CACHE_TTL_SECONDS,
            fn (): array => $service->build($userId, $teamId)
        );

        return response()->json([
            'code' => '075',
            'message' => '',
            'status' => 'success',
            'data' => $data,
        ], HttpCodes::HTTP_OK);
    }
}
