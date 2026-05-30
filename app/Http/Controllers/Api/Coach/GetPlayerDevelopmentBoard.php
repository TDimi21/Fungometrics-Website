<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\User;
use App\Models\WeightBallPractice;
use App\Models\Concerns\PracticeTypes;
use App\Services\ResultTrainingService;
use App\Services\Statistics\BattingStatisticsService;
use App\Services\Statistics\BullpenStatisticsService;
use App\Services\Statistics\CageStatisticsService;
use App\Services\Statistics\ExitVelocityStatisticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerDevelopmentBoard extends Controller
{
    /**
     * GET /coach/teams/{id}/player-development-board
     *
     * Returns a development card for every player on the team:
     *  - FMTRX scores (batting FPS, bullpen BPS, cage FCS, EV EVS)
     *  - Session coverage counts (last 30 days)
     *  - Simple trend (comparing last 5 vs previous 5 sessions per type)
     *  - Status label (Hot / Improving / Steady / Needs Work / No Data)
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teamId = (string) $request->route('id');

            $board = Cache::remember("player_dev_board_{$teamId}", 300, function () use ($teamId) {

                $playerIds = PlayerTeam::where('team_id', $teamId)
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->all();

                if (empty($playerIds)) {
                    return [];
                }

                $users = User::whereIn('id', $playerIds)
                    ->with(['profile', 'player'])
                    ->get()
                    ->keyBy('id');

                // ── Latest fitness snapshot per player + team standings ──────
                $latestFitness = PlayerFitness::whereIn('user_id', $playerIds)
                    ->orderByDesc('fitness_date')
                    ->orderByDesc('created_at')
                    ->get()
                    ->unique('user_id')
                    ->keyBy('user_id');

                $buildFitnessRank = function (string $metric, bool $lowerIsBetter = false) use ($latestFitness): array {
                    $rows = $latestFitness
                        ->filter(function ($fit) use ($metric) {
                            $v = $fit->{$metric} ?? null;
                            return $v !== null && (float) $v > 0;
                        })
                        ->map(function ($fit) use ($metric) {
                            return ['user_id' => $fit->user_id, 'value' => (float) $fit->{$metric}];
                        })
                        ->values()
                        ->all();

                    usort($rows, function ($a, $b) use ($lowerIsBetter) {
                        return $lowerIsBetter
                            ? ($a['value'] <=> $b['value'])
                            : ($b['value'] <=> $a['value']);
                    });

                    $ranks = [];
                    $rank = 0;
                    $lastValue = null;
                    foreach ($rows as $idx => $row) {
                        if ($lastValue === null || $row['value'] !== $lastValue) {
                            $rank = $idx + 1;
                            $lastValue = $row['value'];
                        }
                        $ranks[$row['user_id']] = [
                            'rank'  => $rank,
                            'total' => count($rows),
                            'value' => $row['value'],
                        ];
                    }

                    return $ranks;
                };

                $fitnessRanks = [
                    'body_weight' => $buildFitnessRank('body_weight'),
                    'bench_press' => $buildFitnessRank('bench_press'),
                    'front_squat' => $buildFitnessRank('front_squat'),
                    'back_squat'  => $buildFitnessRank('back_squat'),
                    'power_clean' => $buildFitnessRank('power_clean'),
                    'dead_lift'   => $buildFitnessRank('dead_lift'),
                    'yd_40_dash'  => $buildFitnessRank('yd_40_dash', true),
                    'yd_60_dash'  => $buildFitnessRank('yd_60_dash', true),
                ];

                // ── Session coverage: count distinct practices per type in last 30 days ──
                $since = now()->subDays(30);

                $battingCounts = BattingPracticeResult::where('team_id', $teamId)
                    ->whereIn('batter_id', $playerIds)
                    ->where('created_at', '>=', $since)
                    ->selectRaw('batter_id as player_id, COUNT(DISTINCT practice_id) as cnt')
                    ->groupBy('batter_id')
                    ->pluck('cnt', 'player_id');

                $bullpenCounts = BullpenPracticeResult::where('team_id', $teamId)
                    ->whereIn('pitcher_id', $playerIds)
                    ->where('created_at', '>=', $since)
                    ->selectRaw('pitcher_id as player_id, COUNT(DISTINCT practice_id) as cnt')
                    ->groupBy('pitcher_id')
                    ->pluck('cnt', 'player_id');

                $cageCounts = CagePracticeResult::where('team_id', $teamId)
                    ->whereIn('user_id', $playerIds)
                    ->where('created_at', '>=', $since)
                    ->selectRaw('user_id as player_id, COUNT(DISTINCT practice_id) as cnt')
                    ->groupBy('user_id')
                    ->pluck('cnt', 'player_id');

                $evCounts = ExitVelocityPractice::where('team_id', $teamId)
                    ->whereIn('user_id', $playerIds)
                    ->where('created_at', '>=', $since)
                    ->selectRaw('user_id as player_id, COUNT(DISTINCT practice_id) as cnt')
                    ->groupBy('user_id')
                    ->pluck('cnt', 'player_id');

                $ltCounts = LongTossPractice::where('team_id', $teamId)
                    ->whereIn('user_id', $playerIds)
                    ->where('created_at', '>=', $since)
                    ->selectRaw('user_id as player_id, COUNT(DISTINCT practice_id) as cnt')
                    ->groupBy('user_id')
                    ->pluck('cnt', 'player_id');

                $wbCounts = WeightBallPractice::where('team_id', $teamId)
                    ->whereIn('user_id', $playerIds)
                    ->where('created_at', '>=', $since)
                    ->selectRaw('user_id as player_id, COUNT(DISTINCT practice_id) as cnt')
                    ->groupBy('user_id')
                    ->pluck('cnt', 'player_id');

                // ── Batch-fetch all result rows for all players at once ────────
                // (avoids N×8 queries — fetch once, split by player in PHP)
                $battingSvc = new BattingStatisticsService();
                $bullpenSvc = new BullpenStatisticsService();
                $cageSvc    = new CageStatisticsService();
                $evSvc      = new ExitVelocityStatisticsService();

                $allBatting = ResultTrainingService::getBattingResultsLastSessions($teamId, $playerIds, 10);
                $allBullpen = ResultTrainingService::getBullpenResultsLastSessions($teamId, $playerIds, 10);
                $allCage    = ResultTrainingService::getCageResultsLastSessions($teamId, $playerIds, 10);
                $allEV      = ResultTrainingService::getExitVelocityResultsLastSessions($teamId, $playerIds, 10);

                // Group by player so we can slice per-player without extra queries
                $batByPlayer  = $allBatting->groupBy('batter_id');
                $bulByPlayer  = $allBullpen->groupBy(fn($r) => $r->pitcher_id ?? $r->user_id ?? null);
                $cageByPlayer = $allCage->groupBy('user_id');
                $evByPlayer   = $allEV->groupBy('user_id');

                $board = [];

                foreach ($playerIds as $playerId) {
                    $user = $users->get($playerId);
                    if (!$user) continue;

                    $profile = $user->profile;
                    $fitness = $latestFitness->get($playerId);
                    $name = $profile
                        ? trim("{$profile->first_name} {$profile->last_name}")
                        : "Player #{$playerId}";

                    // ── Batting FPS (last 5 / prev 5) ──────────────────────────
                    $fpsRecent = null; $fpsPrev = null;
                    try {
                        $playerBat = $batByPlayer->get($playerId, collect());
                        $batRecentIds = $playerBat->pluck('practice_id')->unique()->take(5)->values()->all();
                        $batPrevIds   = $playerBat->pluck('practice_id')->unique()->slice(5)->values()->all();
                        $batRecent = $playerBat->whereIn('practice_id', $batRecentIds);
                        $batPrev   = $playerBat->whereIn('practice_id', $batPrevIds);
                        $fpsRecent = $batRecent->count() > 0 ? ($battingSvc->fps($batRecent)['fps'] ?? null) : null;
                        $fpsPrev   = $batPrev->count()   > 0 ? ($battingSvc->fps($batPrev)['fps']   ?? null) : null;
                    } catch (\Throwable $e) {
                        Log::warning("DevBoard batting player {$playerId}: " . $e->getMessage());
                    }

                    // ── Bullpen BPS (last 5 / prev 5) ──────────────────────────
                    $bpsRecent = null; $bpsPrev = null;
                    try {
                        $playerBul = $bulByPlayer->get($playerId, collect());
                        $bulRecentIds = $playerBul->pluck('practice_id')->unique()->take(5)->values()->all();
                        $bulPrevIds   = $playerBul->pluck('practice_id')->unique()->slice(5)->values()->all();
                        $bulRecent = $playerBul->whereIn('practice_id', $bulRecentIds);
                        $bulPrev   = $playerBul->whereIn('practice_id', $bulPrevIds);
                        $bpsRecent = $bulRecent->count() > 0 ? ($bullpenSvc->bps($bulRecent)['bps'] ?? null) : null;
                        $bpsPrev   = $bulPrev->count()   > 0 ? ($bullpenSvc->bps($bulPrev)['bps']   ?? null) : null;
                    } catch (\Throwable $e) {
                        Log::warning("DevBoard bullpen player {$playerId}: " . $e->getMessage());
                    }

                    // ── Cage FCS (last 5 / prev 5) ─────────────────────────────
                    $fcsRecent = null; $fcsPrev = null;
                    try {
                        $playerCage = $cageByPlayer->get($playerId, collect());
                        $cageRecentIds = $playerCage->pluck('practice_id')->unique()->take(5)->values()->all();
                        $cagePrevIds   = $playerCage->pluck('practice_id')->unique()->slice(5)->values()->all();
                        $cageRecent = $playerCage->whereIn('practice_id', $cageRecentIds);
                        $cagePrev   = $playerCage->whereIn('practice_id', $cagePrevIds);
                        $fcsRecent  = $cageRecent->count() > 0 ? ($cageSvc->fcs($cageRecent)['fcs'] ?? null) : null;
                        $fcsPrev    = $cagePrev->count()   > 0 ? ($cageSvc->fcs($cagePrev)['fcs']   ?? null) : null;
                    } catch (\Throwable $e) {
                        Log::warning("DevBoard cage player {$playerId}: " . $e->getMessage());
                    }

                    // ── EV EVS (last 5 / prev 5) ────────────────────────────────
                    $evsRecent = null; $evsPrev = null;
                    try {
                        $playerEV = $evByPlayer->get($playerId, collect());
                        $evRecentIds = $playerEV->pluck('practice_id')->unique()->take(5)->values()->all();
                        $evPrevIds   = $playerEV->pluck('practice_id')->unique()->slice(5)->values()->all();
                        $evRecent = $playerEV->whereIn('practice_id', $evRecentIds);
                        $evPrev   = $playerEV->whereIn('practice_id', $evPrevIds);
                        $evsRecent = $evRecent->count() > 0 ? ($evSvc->evs($evRecent)['evs'] ?? null) : null;
                        $evsPrev   = $evPrev->count()   > 0 ? ($evSvc->evs($evPrev)['evs']   ?? null) : null;
                    } catch (\Throwable $e) {
                        Log::warning("DevBoard ev player {$playerId}: " . $e->getMessage());
                    }

                    // ── Overall score (average of available scores) ─────────────
                    $scores = array_filter([$fpsRecent, $bpsRecent, $fcsRecent, $evsRecent], fn($s) => $s !== null);
                    $overall = count($scores) > 0 ? round(array_sum($scores) / count($scores)) : null;

                    // ── Trend: compare overall recent vs overall prev ─────────────
                    $prevScores = array_filter([$fpsPrev, $bpsPrev, $fcsPrev, $evsPrev], fn($s) => $s !== null);
                    $overallPrev = count($prevScores) > 0 ? round(array_sum($prevScores) / count($prevScores)) : null;

                    $trend = 'steady';
                    if ($overall !== null && $overallPrev !== null) {
                        $delta = $overall - $overallPrev;
                        if ($delta >= 5)       $trend = 'up';
                        elseif ($delta <= -5)  $trend = 'down';
                    }

                    // ── Status ───────────────────────────────────────────────────
                    $status = 'no_data';
                    if ($overall !== null) {
                        if ($overall >= 85 && $trend === 'up')        $status = 'hot';
                        elseif ($trend === 'up')                       $status = 'improving';
                        elseif ($overall >= 75 && $trend !== 'down')   $status = 'steady';
                        elseif ($trend === 'down' || $overall < 65)    $status = 'needs_work';
                        else                                            $status = 'steady';
                    }

                    // ── Session coverage ─────────────────────────────────────────
                    $totalSessions = (int)($battingCounts->get($playerId, 0))
                        + (int)($bullpenCounts->get($playerId, 0))
                        + (int)($cageCounts->get($playerId, 0))
                        + (int)($evCounts->get($playerId, 0))
                        + (int)($ltCounts->get($playerId, 0))
                        + (int)($wbCounts->get($playerId, 0));

                    if ($status === 'no_data' && $totalSessions > 0) {
                        $status = 'steady';
                    }

                    $board[] = [
                        'id'          => $playerId,
                        'name'        => $name,
                        'jersey'      => $user->player?->number_in_shirt ?? null,
                        'picture'     => $profile?->picture ?? null,
                        'scores' => [
                            'overall' => $overall,
                            'batting' => $fpsRecent !== null ? (int) round($fpsRecent) : null,
                            'bullpen' => $bpsRecent !== null ? (int) round($bpsRecent) : null,
                            'cage'    => $fcsRecent !== null ? (int) round($fcsRecent) : null,
                            'ev'      => $evsRecent !== null ? (int) round($evsRecent) : null,
                        ],
                        'prev_scores' => [
                            'overall' => $overallPrev,
                            'batting' => $fpsPrev   !== null ? (int) round($fpsPrev)   : null,
                            'bullpen' => $bpsPrev   !== null ? (int) round($bpsPrev)   : null,
                            'cage'    => $fcsPrev   !== null ? (int) round($fcsPrev)   : null,
                            'ev'      => $evsPrev   !== null ? (int) round($evsPrev)   : null,
                        ],
                        'trend'  => $trend,
                        'status' => $status,
                        'coverage' => [
                            'batting'      => (int) $battingCounts->get($playerId, 0),
                            'bullpen'      => (int) $bullpenCounts->get($playerId, 0),
                            'cage'         => (int) $cageCounts->get($playerId, 0),
                            'exit_velocity'=> (int) $evCounts->get($playerId, 0),
                            'long_toss'    => (int) $ltCounts->get($playerId, 0),
                            'weight_ball'  => (int) $wbCounts->get($playerId, 0),
                            'total'        => $totalSessions,
                        ],
                        'fitness' => [
                            'body_weight' => $fitness?->body_weight,
                            'bench_press' => $fitness?->bench_press,
                            'front_squat' => $fitness?->front_squat,
                            'back_squat'  => $fitness?->back_squat,
                            'power_clean' => $fitness?->power_clean,
                            'dead_lift'   => $fitness?->dead_lift,
                            'yd_40_dash'  => $fitness?->yd_40_dash,
                            'yd_60_dash'  => $fitness?->yd_60_dash,
                            'date'        => $fitness?->fitness_date,
                        ],
                        'fitness_rank' => [
                            'body_weight' => $fitnessRanks['body_weight'][$playerId] ?? null,
                            'bench_press' => $fitnessRanks['bench_press'][$playerId] ?? null,
                            'front_squat' => $fitnessRanks['front_squat'][$playerId] ?? null,
                            'back_squat'  => $fitnessRanks['back_squat'][$playerId] ?? null,
                            'power_clean' => $fitnessRanks['power_clean'][$playerId] ?? null,
                            'dead_lift'   => $fitnessRanks['dead_lift'][$playerId] ?? null,
                            'yd_40_dash'  => $fitnessRanks['yd_40_dash'][$playerId] ?? null,
                            'yd_60_dash'  => $fitnessRanks['yd_60_dash'][$playerId] ?? null,
                        ],
                    ];
                }

                // Sort: hot first, then improving, steady, needs_work, no_data
                $statusOrder = ['hot' => 0, 'improving' => 1, 'steady' => 2, 'needs_work' => 3, 'no_data' => 4];
                usort($board, function ($a, $b) use ($statusOrder) {
                    $oa = $statusOrder[$a['status']] ?? 5;
                    $ob = $statusOrder[$b['status']] ?? 5;
                    if ($oa !== $ob) return $oa <=> $ob;
                    return ($b['scores']['overall'] ?? 0) <=> ($a['scores']['overall'] ?? 0);
                });

                return $board;
            });

            return response()->json([
                'code'    => '065',
                'message' => '',
                'status'  => 'success',
                'data'    => $board,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $exception) {
            Log::error('GetPlayerDevelopmentBoard: ' . $exception->getMessage());
            return response()->json([
                'code'    => '065-E',
                'message' => 'Error retrieving player development board',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
