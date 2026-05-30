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
use App\Services\Statistics\WeightBallStatisticsService;
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

            $data = Cache::remember("performance_overview_{$teamId}", 600, function () use ($teamId) {
                // All player IDs on this team — filter out unclaimed (null user_id) entries
                $playerIds = PlayerTeam::where('team_id', $teamId)
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->all();

                // Log for debugging
                Log::info('GetPerformanceOverview playerIds', ['team' => $teamId, 'count' => count($playerIds), 'ids' => $playerIds]);

                if (empty($playerIds)) {
                    Log::warning('GetPerformanceOverview: no claimed players found for team', ['team' => $teamId]);
                    return [];
                }

                $result = [];

                // ── Batting FPS — last 10 sessions ───────────────────────────
                $battingData = ResultTrainingService::getBattingResultsLastSessions($teamId, $playerIds, 10);
                $result['batting'] = (new BattingStatisticsService())->fps($battingData);

                // ── Bullpen — last 10 sessions ────────────────────────────────
                $bullpenData = ResultTrainingService::getBullpenResultsLastSessions($teamId, $playerIds, 10);
                $bullpenSvc  = new BullpenStatisticsService();
                $result['bullpen'] = [
                    'bps'      => $bullpenSvc->bps($bullpenData),
                    'totals'   => $bullpenSvc->totals($bullpenData),
                    'percents' => $bullpenSvc->percents($bullpenData),
                    'avg_velo' => $bullpenSvc->averageVelocityBreakDown($bullpenData),
                    'top_velo' => $bullpenSvc->topVelocityBreakDown($bullpenData),
                ];

                // ── Cage — last 10 sessions ───────────────────────────────────
                $cageData = ResultTrainingService::getCageResultsLastSessions($teamId, $playerIds, 10);
                $cageSvc  = new CageStatisticsService();
                $result['cage'] = [
                    'fcs'                  => $cageSvc->fcs($cageData),
                    'launch_angle_totals'   => $cageSvc->launchAngleTotals($cageData),
                    'launch_angle_percents' => $cageSvc->launchAnglePercents($cageData),
                    'launch_angle_avg_ev'   => $cageSvc->launchAngleExitVelocityAverage($cageData),
                    'spray_angle_totals'    => $cageSvc->sprayAngleTotals($cageData),
                    'spray_angle_percents'  => $cageSvc->sprayAnglePercents($cageData),
                    'spray_angle_avg_ev'    => $cageSvc->sprayAngleExitVelocityAverage($cageData),
                ];

                // ── Exit Velocity — last 10 sessions ──────────────────────────
                $evData = ResultTrainingService::getExitVelocityResultsLastSessions($teamId, $playerIds, 10);
                $evSvc  = new ExitVelocityStatisticsService();
                $result['exit_velocity'] = [
                    'evs'      => $evSvc->evs($evData),
                    'totals'   => $evSvc->totals($evData),
                    'percents' => $evSvc->percents($evData),
                ];

                // ── Long Toss — last 10 sessions ──────────────────────────────
                $longTossData = ResultTrainingService::getLongTossResultsLastSessions($teamId, $playerIds, 10);
                $ltSvc        = new LongTossStatisticsService();
                $result['long_toss'] = [
                    'lts'               => $ltSvc->lts($longTossData),
                    'distance_totals'   => $ltSvc->distanceTotals($longTossData),
                    'distance_percents' => $ltSvc->distancePercentage($longTossData),
                    'distance_avg'      => $ltSvc->distanceAverage($longTossData),
                    'total_hops'        => $ltSvc->totalHops($longTossData),
                    'avg_hops'          => $ltSvc->averageHops($longTossData),
                    'max_hops'          => $ltSvc->maxHops($longTossData),
                ];

                // ── Weight Ball — last 10 sessions ────────────────────────────
                $wbData = ResultTrainingService::getWeightBallResultsLastSessions($teamId, $playerIds, 10);
                $wbSvc  = new WeightBallStatisticsService();
                $result['weight_ball'] = [
                    'wbs' => $wbSvc->wbs($wbData),
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
