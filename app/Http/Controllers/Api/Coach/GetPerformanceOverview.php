<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\PlayerTeam;
use App\Services\ResultTrainingService;
use App\Services\Statistics\BattingStatisticsService;
use App\Services\Statistics\BullpenStatisticsService;
use App\Services\Statistics\CageStatisticsService;
use App\Services\Statistics\ExitVelocityStatisticsService;
use App\Services\Statistics\LongTossStatisticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPerformanceOverview extends Controller
{
    /**
     * GET /coach/performance-overview/{team}
     *
     * Returns pre-computed FMTRX performance scores for the team.
     * Results are cached for 60 seconds to keep response times fast.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teamId = (string) $request->team;

            $data = Cache::remember("performance_overview_{$teamId}", 60, function () use ($teamId) {
                // Date window: last 90 days
                $dates = [
                    now()->subDays(90)->toDateString(),
                    now()->toDateString(),
                ];

                // All active player IDs on this team
                $playerIds = PlayerTeam::where('team_id', $teamId)
                    ->pluck('user_id')
                    ->all();

                if (empty($playerIds)) {
                    return [];
                }

                $result = [];

                // ── Batting FPS ──────────────────────────────────────────────
                $battingData = ResultTrainingService::getBattingResults($teamId, $playerIds, $dates);
                $result['batting'] = (new BattingStatisticsService())->fps($battingData);

                // ── Bullpen ───────────────────────────────────────────────────
                $bullpenData = ResultTrainingService::getBullpenResults($teamId, $playerIds, $dates);
                $bullpenSvc  = new BullpenStatisticsService();
                $result['bullpen'] = [
                    'totals'   => $bullpenSvc->totals($bullpenData),
                    'percents' => $bullpenSvc->percents($bullpenData),
                    'avg_velo' => $bullpenSvc->averageVelocityBreakDown($bullpenData),
                    'top_velo' => $bullpenSvc->topVelocityBreakDown($bullpenData),
                ];

                // ── Cage ──────────────────────────────────────────────────────
                $cageData = ResultTrainingService::getCageResults($teamId, $playerIds, $dates);
                $cageSvc  = new CageStatisticsService();
                $result['cage'] = [
                    'launch_angle_totals'   => $cageSvc->launchAngleTotals($cageData),
                    'launch_angle_percents' => $cageSvc->launchAnglePercents($cageData),
                    'launch_angle_avg_ev'   => $cageSvc->launchAngleExitVelocityAverage($cageData),
                    'spray_angle_totals'    => $cageSvc->sprayAngleTotals($cageData),
                    'spray_angle_percents'  => $cageSvc->sprayAnglePercents($cageData),
                    'spray_angle_avg_ev'    => $cageSvc->sprayAngleExitVelocityAverage($cageData),
                ];

                // ── Exit Velocity ─────────────────────────────────────────────
                $evData = ResultTrainingService::getExitVelocityResults($teamId, $playerIds, $dates);
                $evSvc  = new ExitVelocityStatisticsService();
                $result['exit_velocity'] = [
                    'totals'   => $evSvc->totals($evData),
                    'percents' => $evSvc->percents($evData),
                ];

                // ── Long Toss ─────────────────────────────────────────────────
                $longTossData = ResultTrainingService::getLongTossResults($teamId, $playerIds, $dates);
                $ltSvc        = new LongTossStatisticsService();
                $result['long_toss'] = [
                    'distance_totals'   => $ltSvc->distanceTotals($longTossData),
                    'distance_percents' => $ltSvc->distancePercentage($longTossData),
                    'distance_avg'      => $ltSvc->distanceAverage($longTossData),
                    'total_hops'        => $ltSvc->totalHops($longTossData),
                    'avg_hops'          => $ltSvc->averageHops($longTossData),
                    'max_hops'          => $ltSvc->maxHops($longTossData),
                ];

                return $result;
            });

            return response()->json([
                'code'    => '060',
                'message' => '',
                'status'  => 'success',
                'data'    => $data,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code'    => '060-E',
                'message' => ' ',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
