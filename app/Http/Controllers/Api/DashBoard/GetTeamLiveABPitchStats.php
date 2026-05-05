<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Models\BullpenPracticeResult;
use App\Models\LiveABPracticeResult;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTeamLiveABPitchStats extends Controller
{
    private const PITCH_TYPES = ['FB', 'CB', 'SL', 'CH'];

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $liveAbResults = LiveABPracticeResult::whereNotNull('pitching_result_id')
                ->get(['pitching_result_id', 'is_strike'])
                ->keyBy('pitching_result_id');

            $liveAbPitchingIds = $liveAbResults->keys();

            $results = BullpenPracticeResult::whereIn('id', $liveAbPitchingIds)
                ->whereNotNull('type_throw')
                ->get();

            $total = $results->count();

            $pitchStats = [];

            foreach (self::PITCH_TYPES as $type) {
                $rows     = $results->where('type_throw', $type);
                $count    = $rows->count();
                $strikes  = $rows->filter(fn($r) => $liveAbResults->get($r->id)?->is_strike === true || $r->trajectory === 'SM')->count();
                $veloRows = $rows->filter(fn($r) => $r->miles_per_hour > 0)->pluck('miles_per_hour');
                $avgVelo  = $veloRows->count() ? round($veloRows->avg(), 1) : null;

                $pitchStats[$type] = [
                    'count'          => $count,
                    'strikes'        => $strikes,
                    'strike_percent' => $count > 0 ? round(($strikes / $count) * 100) : 0,
                    'avg_velo'       => $avgVelo,
                ];
            }

            $otherRows     = $results->whereNotIn('type_throw', self::PITCH_TYPES);
            $otherCount    = $otherRows->count();
            $otherStrikes  = $otherRows->filter(fn($r) => $liveAbResults->get($r->id)?->is_strike === true || $r->trajectory === 'SM')->count();
            $otherVeloRows = $otherRows->filter(fn($r) => $r->miles_per_hour > 0)->pluck('miles_per_hour');
            $pitchStats['OTHER'] = [
                'count'          => $otherCount,
                'strikes'        => $otherStrikes,
                'strike_percent' => $otherCount > 0 ? round(($otherStrikes / $otherCount) * 100) : 0,
                'avg_velo'       => $otherVeloRows->count() ? round($otherVeloRows->avg(), 1) : null,
            ];

            return response()->json([
                'code'   => '200',
                'status' => 'success',
                'data'   => [
                    'id'     => 'team',
                    'name'   => 'Team',
                    'avatar' => null,
                    'total'  => $total,
                    'pitches' => $pitchStats,
                ],
            ], HttpCodes::HTTP_OK);

        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'code'   => '500',
                'status' => 'error',
                'data'   => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
