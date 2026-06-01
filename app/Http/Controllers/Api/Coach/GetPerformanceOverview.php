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
use Throwable;

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

                if (empty($playerIds)) {
                    Log::warning('GetPerformanceOverview: no claimed players found for team, using team-level fallbacks', ['team' => $teamId]);
                }

                $result = [];
                $safeStat = static function (callable $resolver, string $key, mixed $default = []) use ($teamId) {
                    try {
                        return $resolver();
                    } catch (Throwable $e) {
                        Log::warning('GetPerformanceOverview stat failed', [
                            'team' => $teamId,
                            'stat' => $key,
                            'error' => $e->getMessage(),
                        ]);

                        return $default;
                    }
                };

                // ── Batting FPS — last 10 sessions ───────────────────────────
                try {
                    $battingData = ResultTrainingService::getBattingResultsLastSessions($teamId, $playerIds, 10);
                    $result['batting'] = (new BattingStatisticsService())->fps($battingData);
                } catch (Throwable $e) {
                    Log::error('GetPerformanceOverview batting failed', [
                        'team' => $teamId,
                        'error' => $e->getMessage(),
                    ]);
                    $result['batting'] = [];
                }

                // ── Bullpen — last 10 sessions ────────────────────────────────
                try {
                    $bullpenData = ResultTrainingService::getBullpenResultsLastSessions($teamId, $playerIds, 10);
                    $bullpenSvc  = new BullpenStatisticsService();
                    $result['bullpen'] = [
                        'bps'      => $safeStat(fn () => $bullpenSvc->bps($bullpenData), 'bullpen.bps'),
                        'totals'   => $safeStat(fn () => $bullpenSvc->totals($bullpenData), 'bullpen.totals'),
                        'percents' => $safeStat(fn () => $bullpenSvc->percents($bullpenData), 'bullpen.percents'),
                        'avg_velo' => $safeStat(fn () => $bullpenSvc->averageVelocityBreakDown($bullpenData), 'bullpen.avg_velo'),
                        'top_velo' => $safeStat(fn () => $bullpenSvc->topVelocityBreakDown($bullpenData), 'bullpen.top_velo'),
                    ];
                } catch (Throwable $e) {
                    Log::error('GetPerformanceOverview bullpen failed', [
                        'team' => $teamId,
                        'error' => $e->getMessage(),
                    ]);
                    $result['bullpen'] = [];
                }

                // ── Cage — last 10 sessions ───────────────────────────────────
                try {
                    $cageData = ResultTrainingService::getCageResultsLastSessions($teamId, $playerIds, 10);
                    $cageSvc  = new CageStatisticsService();
                    $result['cage'] = [
                        'fcs'                  => $safeStat(fn () => $cageSvc->fcs($cageData), 'cage.fcs'),
                        'launch_angle_totals'   => $safeStat(fn () => $cageSvc->launchAngleTotals($cageData), 'cage.launch_angle_totals'),
                        'launch_angle_percents' => $safeStat(fn () => $cageSvc->launchAnglePercents($cageData), 'cage.launch_angle_percents'),
                        'launch_angle_avg_ev'   => $safeStat(fn () => $cageSvc->launchAngleExitVelocityAverage($cageData), 'cage.launch_angle_avg_ev'),
                        'spray_angle_totals'    => $safeStat(fn () => $cageSvc->sprayAngleTotals($cageData), 'cage.spray_angle_totals'),
                        'spray_angle_percents'  => $safeStat(fn () => $cageSvc->sprayAnglePercents($cageData), 'cage.spray_angle_percents'),
                        'spray_angle_avg_ev'    => $safeStat(fn () => $cageSvc->sprayAngleExitVelocityAverage($cageData), 'cage.spray_angle_avg_ev'),
                    ];
                } catch (Throwable $e) {
                    Log::error('GetPerformanceOverview cage failed', [
                        'team' => $teamId,
                        'error' => $e->getMessage(),
                    ]);
                    $result['cage'] = [];
                }

                // ── Exit Velocity — last 10 sessions ──────────────────────────
                try {
                    $evData = ResultTrainingService::getExitVelocityResultsLastSessions($teamId, $playerIds, 10);
                    $evSvc  = new ExitVelocityStatisticsService();
                    $result['exit_velocity'] = [
                        'evs'      => $safeStat(fn () => $evSvc->evs($evData), 'exit_velocity.evs'),
                        'totals'   => $safeStat(fn () => $evSvc->totals($evData), 'exit_velocity.totals'),
                        'percents' => $safeStat(fn () => $evSvc->percents($evData), 'exit_velocity.percents'),
                    ];
                } catch (Throwable $e) {
                    Log::error('GetPerformanceOverview exit_velocity failed', [
                        'team' => $teamId,
                        'error' => $e->getMessage(),
                    ]);
                    $result['exit_velocity'] = [];
                }

                // ── Long Toss — last 10 sessions ──────────────────────────────
                try {
                    $longTossData = ResultTrainingService::getLongTossResultsLastSessions($teamId, $playerIds, 10);
                    $ltSvc        = new LongTossStatisticsService();
                    $result['long_toss'] = [
                        'lts'               => $safeStat(fn () => $ltSvc->lts($longTossData), 'long_toss.lts'),
                        'distance_totals'   => $safeStat(fn () => $ltSvc->distanceTotals($longTossData), 'long_toss.distance_totals'),
                        'distance_percents' => $safeStat(fn () => $ltSvc->distancePercentage($longTossData), 'long_toss.distance_percents'),
                        'distance_avg'      => $safeStat(fn () => $ltSvc->distanceAverage($longTossData), 'long_toss.distance_avg'),
                        'total_hops'        => $safeStat(fn () => $ltSvc->totalHops($longTossData), 'long_toss.total_hops'),
                        'avg_hops'          => $safeStat(fn () => $ltSvc->averageHops($longTossData), 'long_toss.avg_hops'),
                        'max_hops'          => $safeStat(fn () => $ltSvc->maxHops($longTossData), 'long_toss.max_hops'),
                    ];
                } catch (Throwable $e) {
                    Log::error('GetPerformanceOverview long_toss failed', [
                        'team' => $teamId,
                        'error' => $e->getMessage(),
                    ]);
                    $result['long_toss'] = [];
                }

                // ── Weight Ball — last 10 sessions ────────────────────────────
                try {
                    $wbData = ResultTrainingService::getWeightBallResultsLastSessions($teamId, $playerIds, 10);
                    $wbSvc  = new WeightBallStatisticsService();
                    $result['weight_ball'] = [
                        'wbs' => $safeStat(fn () => $wbSvc->wbs($wbData), 'weight_ball.wbs'),
                    ];
                } catch (Throwable $e) {
                    Log::error('GetPerformanceOverview weight_ball failed', [
                        'team' => $teamId,
                        'error' => $e->getMessage(),
                    ]);
                    $result['weight_ball'] = [];
                }

                return $result;
            });

            return response()->json([
                'code'    => '060',
                'message' => '',
                'status'  => 'success',
                'data'    => $data,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $exception) {
            Log::error('GetPerformanceOverview failed', [
                'team' => (string) $request->team,
                'error' => $exception->getMessage(),
            ]);
            return response()->json([
                'code'    => '060-E',
                'message' => ' ',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
