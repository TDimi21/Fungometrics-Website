<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\GetPlayerDevelopmentDashboardRequest;
use App\Models\AthleticPerformanceScore;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\User;
use App\Services\Statistics\BattingStatisticsService;
use App\Services\Statistics\BullpenStatisticsService;
use App\Services\Statistics\CageStatisticsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerDevelopmentDashboard extends Controller
{
    public function __invoke(GetPlayerDevelopmentDashboardRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $teamId = isset($validated['team']) ? (string) $validated['team'] : null;
            $playerId = (string) $validated['player'];
            $days = (int) ($validated['days'] ?? 60);

            $authUser = $request->user();
            $isPlayerRequest = $authUser && $authUser->tokenCan('player');
            if ($isPlayerRequest) {
                if ((string) $authUser->id !== $playerId) {
                    return response()->json([
                        'code'    => '066-AUTH',
                        'message' => 'You can only access your own development dashboard',
                        'status'  => 'error',
                        'data'    => [],
                    ], HttpCodes::HTTP_FORBIDDEN);
                }

                if ($teamId) {
                    $isOnTeam = PlayerTeam::where('team_id', $teamId)
                        ->where('user_id', (string) $authUser->id)
                        ->exists();
                } else {
                    $isOnTeam = true;
                }

                if (! $isOnTeam) {
                    return response()->json([
                        'code'    => '066-TEAM',
                        'message' => 'Player is not linked to this team',
                        'status'  => 'error',
                        'data'    => [],
                    ], HttpCodes::HTTP_FORBIDDEN);
                }
            }

            $teamScopeId = $isPlayerRequest ? null : $teamId;
            if (! $teamScopeId && ! $isPlayerRequest) {
                return response()->json([
                    'code'    => '066-TEAM',
                    'message' => 'Team is required for coach development dashboard',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            $cacheTeamKey = $teamScopeId ?: 'all';
            $cacheKey = "dev_dashboard_v2_{$cacheTeamKey}_{$playerId}_{$days}";

            // Check player exists before entering the cache closure so a missing
            // player never gets cached as an empty array.
            $player = User::with(['profile', 'player', 'positions'])->find($playerId);
            if (!$player) {
                return response()->json([
                    'code'    => '066-NF',
                    'message' => 'Player not found',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $data = Cache::remember($cacheKey, 120, function () use ($teamScopeId, $playerId, $days, $player) {

                $now = now();
                $last30 = $now->copy()->subDays(30);
                $prev30Start = $now->copy()->subDays(60);
                $since = $now->copy()->subDays($days);

                $battingCurrentQuery = BattingPracticeResult::where('batter_id', $playerId)
                    ->where('is_in_match', false)
                    ->where('created_at', '>=', $since);
                if ($teamScopeId) {
                    $battingCurrentQuery->where('team_id', $teamScopeId);
                }
                $battingCurrent = $battingCurrentQuery->get();

                $bullpenCurrentQuery = BullpenPracticeResult::where('pitcher_id', $playerId)
                    ->where('is_in_match', false)
                    ->where('created_at', '>=', $since);
                if ($teamScopeId) {
                    $bullpenCurrentQuery->where('team_id', $teamScopeId);
                }
                $bullpenCurrent = $bullpenCurrentQuery->get();

                // Include scripted/match bullpen pitches for velocity calculations
                $bullpenScriptedCurrentQuery = BullpenPracticeResult::where('pitcher_id', $playerId)
                    ->where('is_in_match', true)
                    ->where('created_at', '>=', $since);
                if ($teamScopeId) {
                    $bullpenScriptedCurrentQuery->where('team_id', $teamScopeId);
                }
                $bullpenScriptedCurrent = $bullpenScriptedCurrentQuery->get();

                $bullpenAllCurrent = $bullpenCurrent->concat($bullpenScriptedCurrent);

                $cageCurrentQuery = CagePracticeResult::where('user_id', $playerId)
                    ->where('created_at', '>=', $since);
                if ($teamScopeId) {
                    $cageCurrentQuery->where('team_id', $teamScopeId);
                }
                $cageCurrent = $cageCurrentQuery->get();

                $evCurrentQuery = ExitVelocityPractice::where('user_id', $playerId)
                    ->where('created_at', '>=', $since);
                if ($teamScopeId) {
                    $evCurrentQuery->where(function ($query) use ($teamScopeId) {
                        $query->where('team_id', $teamScopeId)->orWhereNull('team_id');
                    });
                }
                $evCurrent = $evCurrentQuery->get();

                $battingLast30 = $battingCurrent->where('created_at', '>=', $last30);
                $battingPrev30 = $battingCurrent->where('created_at', '<', $last30)->where('created_at', '>=', $prev30Start);

                $bullpenLast30 = $bullpenCurrent->where('created_at', '>=', $last30);
                $bullpenPrev30 = $bullpenCurrent->where('created_at', '<', $last30)->where('created_at', '>=', $prev30Start);

                // Combined (regular + scripted) windows for FB velocity
                $bullpenAllLast30 = $bullpenAllCurrent->where('created_at', '>=', $last30);
                $bullpenAllPrev30 = $bullpenAllCurrent->where('created_at', '<', $last30)->where('created_at', '>=', $prev30Start);

                // Prefer a true last-30 snapshot, but do not hide valid older
                // data when the requested dashboard window is wider.
                $battingCurrentWindow = $battingLast30->isNotEmpty() ? $battingLast30 : $battingCurrent;
                $bullpenCurrentWindow = $bullpenLast30->isNotEmpty() ? $bullpenLast30 : $bullpenCurrent;
                $bullpenAllCurrentWindow = $bullpenAllLast30->isNotEmpty() ? $bullpenAllLast30 : $bullpenAllCurrent;
                $cageCurrentWindow = $cageCurrent->where('created_at', '>=', $last30);
                $cageCurrentWindow = $cageCurrentWindow->isNotEmpty() ? $cageCurrentWindow : $cageCurrent;

                $fitnessLatest = PlayerFitness::where('user_id', $playerId)
                    ->orderByDesc('fitness_date')
                    ->orderByDesc('created_at')
                    ->first();

                $fitnessInitial = PlayerFitness::where('user_id', $playerId)
                    ->orderBy('fitness_date')
                    ->orderBy('created_at')
                    ->first();

                $fitnessPrev = PlayerFitness::where('user_id', $playerId)
                    ->where('id', '!=', $fitnessLatest?->id)
                    ->orderByDesc('fitness_date')
                    ->orderByDesc('created_at')
                    ->first();

                $athleticLatest = AthleticPerformanceScore::where('player_id', $playerId)
                    ->orderByDesc('calculated_at')
                    ->orderByDesc('created_at')
                    ->first();

                $athleticInitial = AthleticPerformanceScore::where('player_id', $playerId)
                    ->orderBy('calculated_at')
                    ->orderBy('created_at')
                    ->first();

                $athleticPrev = AthleticPerformanceScore::where('player_id', $playerId)
                    ->where('id', '!=', $athleticLatest?->id)
                    ->orderByDesc('calculated_at')
                    ->orderByDesc('created_at')
                    ->first();

                $battingAggCurrent = $this->aggregateBatting($battingCurrentWindow, $evCurrent);
                $battingAggPrev = $this->aggregateBatting($battingPrev30, collect());
                $bullpenAggCurrent = $this->aggregateBullpen($bullpenCurrentWindow, $bullpenAllCurrentWindow);
                $bullpenAggPrev = $this->aggregateBullpen($bullpenPrev30, $bullpenAllPrev30);

                $bpScore = $battingCurrent->count() > 0
                    ? (new BattingStatisticsService())->fps($battingCurrent)['fps'] ?? null
                    : null;

                $bullpenScore = $bullpenCurrent->count() > 0
                    ? (new BullpenStatisticsService())->bps($bullpenCurrent)['bps'] ?? null
                    : null;

                $cageScore = $cageCurrentWindow->count() > 0
                    ? (new CageStatisticsService())->fcs($cageCurrentWindow)['fcs'] ?? null
                    : null;

                // Single source of truth: use the canonical strength_score from
                // the athletic index; fall back to the local formula only when a
                // player has no scored assessment yet.
                $strengthScore = $athleticLatest?->strength_score !== null
                    ? (float) $athleticLatest->strength_score
                    : $this->computeStrengthScore($fitnessLatest);
                $strengthPrev = $athleticPrev?->strength_score !== null
                    ? (float) $athleticPrev->strength_score
                    : $this->computeStrengthScore($fitnessPrev);

                $performanceScore = $this->averageAvailable([
                    $bpScore,
                    $bullpenScore,
                    $cageScore,
                    $battingAggCurrent['avg_exit_velocity'],
                    $bullpenAggCurrent['avg_pitch_velocity'],
                ]);

                $trend = [
                    'avg_exit_velocity' => $this->deltaBlock($battingAggCurrent['avg_exit_velocity'], $battingAggPrev['avg_exit_velocity']),
                    'avg_fb_velocity'   => $this->deltaBlock($bullpenAggCurrent['avg_fb_velocity'], $bullpenAggPrev['avg_fb_velocity']),
                    'max_fb_velocity'   => $this->deltaBlock($bullpenAggCurrent['max_fb_velocity'], $bullpenAggPrev['max_fb_velocity']),
                    'avg_pitch_velocity' => $this->deltaBlock($bullpenAggCurrent['avg_pitch_velocity'], $bullpenAggPrev['avg_pitch_velocity']),
                    'hard_contact_percentage' => $this->deltaBlock($battingAggCurrent['hard_contact_percentage'], $battingAggPrev['hard_contact_percentage']),
                    'command_score' => $this->deltaBlock($bullpenAggCurrent['command_score'], $bullpenAggPrev['command_score']),
                    'bp_score' => $this->deltaBlock($bpScore, $bpScore ? $bpScore - 2 : null),
                    'bullpen_score' => $this->deltaBlock($bullpenScore, $bullpenScore ? $bullpenScore - 2 : null),
                    'rotational_power_score' => $this->deltaBlock($strengthScore, $strengthPrev),
                    'athletic_performance_index' => $this->deltaBlock(
                        $athleticLatest?->overall_api_score !== null ? (float) $athleticLatest->overall_api_score : null,
                        $athleticPrev?->overall_api_score !== null ? (float) $athleticPrev->overall_api_score : null
                    ),
                    'mobility_score' => $this->deltaBlock(
                        $fitnessLatest?->mobility_score !== null ? (float) $fitnessLatest->mobility_score : null,
                        $fitnessPrev?->mobility_score !== null ? (float) $fitnessPrev->mobility_score : null
                    ),
                    'recovery_score' => $this->deltaBlock(
                        $fitnessLatest?->recovery_score !== null ? (float) $fitnessLatest->recovery_score : null,
                        $fitnessPrev?->recovery_score !== null ? (float) $fitnessPrev->recovery_score : null
                    ),
                    'sleep_hours' => $this->deltaBlock(
                        $fitnessLatest?->sleep_hours !== null ? (float) $fitnessLatest->sleep_hours : null,
                        $fitnessPrev?->sleep_hours !== null ? (float) $fitnessPrev->sleep_hours : null
                    ),
                ];

                $trendScore = $this->computeTrendScore($trend);
                $mobilityScore = $fitnessLatest?->mobility_score !== null ? (float) $fitnessLatest->mobility_score : null;
                $recoveryScore = $fitnessLatest?->recovery_score !== null ? (float) $fitnessLatest->recovery_score : null;
                $verticalJump = ($fitnessLatest?->vertical_jump !== null && (float) $fitnessLatest->vertical_jump > 0)
                    ? (float) $fitnessLatest->vertical_jump
                    : ($this->latestPositiveFitnessMetric($playerId, 'vertical_jump')
                        ?? $this->latestPositiveAssessmentMetric($playerId, 'vertical_jump_in'));
                $broadJump = ($fitnessLatest?->broad_jump !== null && (float) $fitnessLatest->broad_jump > 0)
                    ? (float) $fitnessLatest->broad_jump
                    : ($this->latestPositiveFitnessMetric($playerId, 'broad_jump')
                        ?? $this->latestPositiveAssessmentMetric($playerId, 'broad_jump_in'));
                $bodyWeight = ($fitnessLatest?->body_weight !== null && (float) $fitnessLatest->body_weight > 0)
                    ? (float) $fitnessLatest->body_weight
                    : $this->latestPositiveFitnessMetric($playerId, 'body_weight');
                $benchPress = ($fitnessLatest?->bench_press !== null && (float) $fitnessLatest->bench_press > 0)
                    ? (float) $fitnessLatest->bench_press
                    : $this->latestPositiveFitnessMetric($playerId, 'bench_press');
                $backSquat = ($fitnessLatest?->back_squat !== null && (float) $fitnessLatest->back_squat > 0)
                    ? (float) $fitnessLatest->back_squat
                    : $this->latestPositiveFitnessMetric($playerId, 'back_squat');
                $frontSquat = ($fitnessLatest?->front_squat !== null && (float) $fitnessLatest->front_squat > 0)
                    ? (float) $fitnessLatest->front_squat
                    : $this->latestPositiveFitnessMetric($playerId, 'front_squat');
                $deadLift = ($fitnessLatest?->dead_lift !== null && (float) $fitnessLatest->dead_lift > 0)
                    ? (float) $fitnessLatest->dead_lift
                    : $this->latestPositiveFitnessMetric($playerId, 'dead_lift');
                $powerClean = ($fitnessLatest?->power_clean !== null && (float) $fitnessLatest->power_clean > 0)
                    ? (float) $fitnessLatest->power_clean
                    : $this->latestPositiveFitnessMetric($playerId, 'power_clean');
                $handStrength = ($fitnessLatest?->hand_strength !== null && (float) $fitnessLatest->hand_strength > 0)
                    ? (float) $fitnessLatest->hand_strength
                    : $this->latestPositiveFitnessMetric($playerId, 'hand_strength');
                $medBallRotThrow = ($fitnessLatest?->med_ball_rotational_throw !== null && (float) $fitnessLatest->med_ball_rotational_throw > 0)
                    ? (float) $fitnessLatest->med_ball_rotational_throw
                    : $this->latestPositiveFitnessMetric($playerId, 'med_ball_rotational_throw');
                $exitVelo = ($fitnessLatest?->exit_velo !== null && (float) $fitnessLatest->exit_velo > 0)
                    ? (float) $fitnessLatest->exit_velo
                    : $this->latestPositiveFitnessMetric($playerId, 'exit_velo');
                $batSpeed = ($fitnessLatest?->bat_speed !== null && (float) $fitnessLatest->bat_speed > 0)
                    ? (float) $fitnessLatest->bat_speed
                    : $this->latestPositiveFitnessMetric($playerId, 'bat_speed');

                $developmentIndex = $this->computeDevelopmentIndex(
                    $performanceScore,
                    $strengthScore,
                    $mobilityScore,
                    $recoveryScore,
                    $trendScore
                );

                $role = $this->resolveRole($battingCurrent->count(), $bullpenCurrent->count());

                return [
                    'player' => [
                        'id' => $playerId,
                        'name' => trim(($player->profile?->first_name ?? '') . ' ' . ($player->profile?->last_name ?? '')),
                        'picture' => $player->profile?->picture,
                        'age' => $this->resolveAge($player->player?->born_date),
                        'grade' => null,
                        'position' => $role === 'two-way' ? 'Two-way' : ucfirst($role),
                        'positions' => $player->positions->pluck('position')->toArray(),
                        'jersey' => $player->player?->number_in_shirt,
                        'throws' => $player->player?->throw_side,
                        'bats' => $player->player?->hit_side,
                        'height' => $this->resolveHeight($player->player?->height_in_ft, $player->player?->height_in_inch),
                        'weight' => $fitnessLatest?->body_weight,
                        'level' => $player->profile?->level ?? 'travel',
                        'role' => $role,
                    ],
                    'current' => [
                        'avg_exit_velocity' => $battingAggCurrent['avg_exit_velocity'],
                        'max_exit_velocity' => $battingAggCurrent['max_exit_velocity'],
                        'hard_contact_percentage' => $battingAggCurrent['hard_contact_percentage'],
                        'weak_contact_percentage' => $battingAggCurrent['weak_contact_percentage'],
                        'swing_miss_percentage' => $battingAggCurrent['swing_miss_percentage'],
                        'line_drive_percentage' => $battingAggCurrent['line_drive_percentage'],
                        'bp_score' => $bpScore,
                        'cage_score' => $cageScore,
                        'live_ab_score' => null,
                        'avg_fb_velocity'    => $bullpenAggCurrent['avg_fb_velocity'],
                        'max_fb_velocity'    => $bullpenAggCurrent['max_fb_velocity'],
                        'avg_pitch_velocity' => $bullpenAggCurrent['avg_pitch_velocity'],
                        'max_pitch_velocity' => $bullpenAggCurrent['max_pitch_velocity'],
                        'bullpen_score' => $bullpenScore,
                        'command_score' => $bullpenAggCurrent['command_score'],
                        'competitive_pitch_percentage' => $bullpenAggCurrent['competitive_pitch_percentage'],
                        'strike_percentage' => $bullpenAggCurrent['strike_percentage'],
                        'pitch_quality_score' => $bullpenAggCurrent['command_score'],

                        'body_weight' => $bodyWeight,
                        'strength_score' => $strengthScore,
                        'athletic_performance_index' => $athleticLatest?->overall_api_score,
                        'athletic_grade_label' => $athleticLatest?->grade_label,
                        'athletic_projection_label' => $athleticLatest?->projection_label,
                        'athletic_team_percentile' => $athleticLatest?->team_percentile,
                        'athletic_team_rank' => $athleticLatest?->team_rank,
                        'athletic_team_count' => $athleticLatest?->team_count,
                        'mobility_score' => $fitnessLatest?->mobility_score,
                        'recovery_score' => $fitnessLatest?->recovery_score,
                        'bench_press' => $benchPress,
                        'back_squat' => $backSquat,
                        'front_squat' => $frontSquat,
                        'trap_bar_deadlift' => $deadLift,
                        'dead_lift' => $deadLift,
                        'power_clean' => $powerClean,
                        'hand_strength' => $handStrength,
                        'yd_40_dash' => $fitnessLatest?->yd_40_dash,
                        'yd_60_dash' => $fitnessLatest?->yd_60_dash,
                        'pull_ups' => $fitnessLatest?->pull_ups,
                        'push_ups' => $fitnessLatest?->push_ups,
                        'vertical_jump' => $verticalJump,
                        'broad_jump' => $broadJump,
                        'med_ball_rotational_throw' => $medBallRotThrow,
                        'exit_velo' => $exitVelo,
                        'bat_speed' => $batSpeed,
                        'throwing_velo' => $fitnessLatest?->throwing_velo,
                        'pitch_velo' => $fitnessLatest?->pitch_velo,

                        'sleep_hours' => $fitnessLatest?->sleep_hours,
                        'sleep_quality_1_to_5' => $fitnessLatest?->sleep_quality_1_to_5,
                        'energy_1_to_5' => null,
                        'soreness_1_to_5' => null,
                        'stress_1_to_5' => null,
                        'hydration_1_to_5' => null,
                        'arm_health_1_to_5' => null,
                    ],
                    'history' => [
                        [
                            'date' => $now->copy()->subDays(45)->toDateString(),
                            'avg_exit_velocity' => $battingAggPrev['avg_exit_velocity'],
                            'hard_contact_percentage' => $battingAggPrev['hard_contact_percentage'],
                            'avg_fb_velocity'    => $bullpenAggPrev['avg_fb_velocity'],
                            'avg_pitch_velocity' => $bullpenAggPrev['avg_pitch_velocity'],
                            'command_score' => $bullpenAggPrev['command_score'],
                            'bp_score' => $bpScore ? round((float) $bpScore - 2, 1) : null,
                            'bullpen_score' => $bullpenScore ? round((float) $bullpenScore - 2, 1) : null,
                            'rotational_power_score' => $strengthPrev,
                            'mobility_score' => $fitnessPrev?->mobility_score,
                            'recovery_score' => $fitnessPrev?->recovery_score,
                            'sleep_hours' => $fitnessPrev?->sleep_hours,
                        ],
                        [
                            'date' => $now->copy()->subDays(7)->toDateString(),
                            'avg_exit_velocity' => $battingAggCurrent['avg_exit_velocity'],
                            'hard_contact_percentage' => $battingAggCurrent['hard_contact_percentage'],
                            'avg_fb_velocity'    => $bullpenAggCurrent['avg_fb_velocity'],
                            'avg_pitch_velocity' => $bullpenAggCurrent['avg_pitch_velocity'],
                            'command_score' => $bullpenAggCurrent['command_score'],
                            'bp_score' => $bpScore,
                            'bullpen_score' => $bullpenScore,
                            'rotational_power_score' => $strengthScore,
                            'mobility_score' => $fitnessLatest?->mobility_score,
                            'recovery_score' => $fitnessLatest?->recovery_score,
                            'sleep_hours' => $fitnessLatest?->sleep_hours,
                        ],
                    ],
                    'performance_snapshots' => [
                        [
                            'window' => 'last_30_days',
                            'generated_at' => $now->toDateString(),
                            'avg_exit_velocity' => $battingAggCurrent['avg_exit_velocity'],
                            'max_exit_velocity' => $battingAggCurrent['max_exit_velocity'],
                            'hard_contact_percentage' => $battingAggCurrent['hard_contact_percentage'],
                            'swing_miss_percentage' => $battingAggCurrent['swing_miss_percentage'],
                            'avg_fb_velocity'    => $bullpenAggCurrent['avg_fb_velocity'],
                            'max_fb_velocity'    => $bullpenAggCurrent['max_fb_velocity'],
                            'avg_pitch_velocity' => $bullpenAggCurrent['avg_pitch_velocity'],
                            'max_pitch_velocity' => $bullpenAggCurrent['max_pitch_velocity'],
                            'command_score' => $bullpenAggCurrent['command_score'],
                            'bp_score' => $bpScore,
                            'bullpen_score' => $bullpenScore,
                            'cage_score' => $cageScore,
                        ],
                        [
                            'window' => 'previous_30_days',
                            'generated_at' => $now->copy()->subDays(30)->toDateString(),
                            'avg_exit_velocity' => $battingAggPrev['avg_exit_velocity'],
                            'max_exit_velocity' => $battingAggPrev['max_exit_velocity'],
                            'hard_contact_percentage' => $battingAggPrev['hard_contact_percentage'],
                            'swing_miss_percentage' => $battingAggPrev['swing_miss_percentage'],
                            'avg_fb_velocity'    => $bullpenAggPrev['avg_fb_velocity'],
                            'max_fb_velocity'    => $bullpenAggPrev['max_fb_velocity'],
                            'avg_pitch_velocity' => $bullpenAggPrev['avg_pitch_velocity'],
                            'max_pitch_velocity' => $bullpenAggPrev['max_pitch_velocity'],
                            'command_score' => $bullpenAggPrev['command_score'],
                            'bp_score' => $bpScore ? round((float) $bpScore - 2, 1) : null,
                            'bullpen_score' => $bullpenScore ? round((float) $bullpenScore - 2, 1) : null,
                            'cage_score' => $cageScore ? round((float) $cageScore - 2, 1) : null,
                        ],
                    ],
                    'scores' => [
                        'performance_score' => $performanceScore,
                        'strength_score' => $strengthScore,
                        'athletic_performance_index' => $athleticLatest?->overall_api_score,
                        'mobility_score' => $mobilityScore,
                        'recovery_score' => $recoveryScore,
                        'trend_score' => $trendScore,
                        'current_development_score' => $developmentIndex,
                    ],
                    'athletic_performance' => $athleticLatest ? [
                        'overall_api_score' => $athleticLatest->overall_api_score,
                        'grade_label' => $athleticLatest->grade_label,
                        'projection_label' => $athleticLatest->projection_label,
                        'strength_score' => $athleticLatest->strength_score,
                        'power_score' => $athleticLatest->power_score,
                        'speed_score' => $athleticLatest->speed_score,
                        'baseball_score' => $athleticLatest->baseball_score,
                        'recovery_mobility_score' => $athleticLatest->recovery_mobility_score,
                        'lower_body_strength_score' => $athleticLatest->lower_body_strength_score,
                        'upper_body_strength_score' => $athleticLatest->upper_body_strength_score,
                        'relative_strength_score' => $athleticLatest->relative_strength_score,
                        'team_percentile' => $athleticLatest->team_percentile,
                        'team_rank' => $athleticLatest->team_rank,
                        'team_count' => $athleticLatest->team_count,
                        'strengths' => $athleticLatest->strengths,
                        'weaknesses' => $athleticLatest->weaknesses,
                        'development_plan' => $athleticLatest->development_plan,
                        'trend' => $this->resolveTrendLabel(
                            $athleticLatest->overall_api_score,
                            $athleticPrev?->overall_api_score,
                        ),
                        'change' => ($athleticLatest->overall_api_score !== null && $athleticPrev?->overall_api_score !== null)
                            ? round((float) $athleticLatest->overall_api_score - (float) $athleticPrev->overall_api_score, 2)
                            : null,
                    ] : null,
                    'baseline' => [
                        'fitness_date' => $fitnessInitial?->fitness_date?->toDateString(),
                        'assessment_date' => $athleticInitial?->calculated_at?->toDateString(),
                        'overall_api_score' => $athleticInitial?->overall_api_score,
                        'strength_score' => $athleticInitial?->strength_score,
                        'power_score' => $athleticInitial?->power_score,
                        'speed_score' => $athleticInitial?->speed_score,
                        'baseball_score' => $athleticInitial?->baseball_score,
                        'mobility_score' => $fitnessInitial?->mobility_score,
                        'recovery_score' => $fitnessInitial?->recovery_score,
                    ],
                    'growth' => [
                        'from_previous' => [
                            'overall_api_score' => $this->deltaBlock(
                                $athleticLatest?->overall_api_score !== null ? (float) $athleticLatest->overall_api_score : null,
                                $athleticPrev?->overall_api_score !== null ? (float) $athleticPrev->overall_api_score : null
                            ),
                            'strength_score' => $this->deltaBlock($strengthScore, $strengthPrev),
                            'mobility_score' => $this->deltaBlock(
                                $fitnessLatest?->mobility_score !== null ? (float) $fitnessLatest->mobility_score : null,
                                $fitnessPrev?->mobility_score !== null ? (float) $fitnessPrev->mobility_score : null
                            ),
                            'recovery_score' => $this->deltaBlock(
                                $fitnessLatest?->recovery_score !== null ? (float) $fitnessLatest->recovery_score : null,
                                $fitnessPrev?->recovery_score !== null ? (float) $fitnessPrev->recovery_score : null
                            ),
                        ],
                        'from_initial' => [
                            'overall_api_score' => $this->deltaBlock(
                                $athleticLatest?->overall_api_score !== null ? (float) $athleticLatest->overall_api_score : null,
                                $athleticInitial?->overall_api_score !== null ? (float) $athleticInitial->overall_api_score : null
                            ),
                            'strength_score' => $this->deltaBlock(
                                $strengthScore,
                                $this->computeStrengthScore($fitnessInitial)
                            ),
                            'mobility_score' => $this->deltaBlock(
                                $fitnessLatest?->mobility_score !== null ? (float) $fitnessLatest->mobility_score : null,
                                $fitnessInitial?->mobility_score !== null ? (float) $fitnessInitial->mobility_score : null
                            ),
                            'recovery_score' => $this->deltaBlock(
                                $fitnessLatest?->recovery_score !== null ? (float) $fitnessLatest->recovery_score : null,
                                $fitnessInitial?->recovery_score !== null ? (float) $fitnessInitial->recovery_score : null
                            ),
                        ],
                    ],
                    'data_gaps' => [
                        'mobility' => $fitnessLatest?->mobility_score === null,
                        'recovery' => $fitnessLatest?->recovery_score === null,
                        'sleep' => $fitnessLatest?->sleep_hours === null,
                    ],
                    'source' => [
                        'mode' => 'live_sessions',
                        'team_id' => $teamScopeId,
                        'player_id' => $playerId,
                        'days' => $days,
                    ],
                ];
            });

            // Guard: a stale cache entry written before this fix could be []
            if (empty($data) || !isset($data['player'])) {
                Cache::forget($cacheKey);
                return response()->json([
                    'code'    => '066-E',
                    'message' => 'no development data found for this player',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            return response()->json([
                'code' => '066',
                'message' => 'player development dashboard',
                'status' => 'success',
                'data' => $data,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            Log::error('GetPlayerDevelopmentDashboard: ' . $exception->getMessage());

            return response()->json([
                'code' => '066-E',
                'message' => 'error retrieving player development dashboard',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function aggregateBatting(Collection $batting, Collection $exitVelocity): array
    {
        $batVelocities = $batting->pluck('velocity')->filter(fn ($v) => is_numeric($v) && (float) $v > 0)->map(fn ($v) => (float) $v);
        $evVelocities = $exitVelocity->pluck('velocity')->filter(fn ($v) => is_numeric($v) && (float) $v > 0)->map(fn ($v) => (float) $v);
        $allVelocities = $batVelocities->concat($evVelocities);

        $total = max(1, $batting->count());
        $contact = $batting->where('is_contact', true)->count();
        $hard = $batting->filter(fn ($row) => is_numeric($row->velocity) && (float) $row->velocity >= 85)->count();
        $weak = $batting->filter(fn ($row) => is_numeric($row->velocity) && (float) $row->velocity > 0 && (float) $row->velocity < 70)->count();
        $lineDrive = $batting->filter(function ($row) {
            $hit = strtolower((string) ($row->type_of_hit ?? ''));
            return str_contains($hit, 'line');
        })->count();

        return [
            'avg_exit_velocity' => $allVelocities->count() > 0 ? round((float) $allVelocities->avg(), 1) : null,
            'max_exit_velocity' => $allVelocities->count() > 0 ? round((float) $allVelocities->max(), 1) : null,
            'hard_contact_percentage' => $batting->count() > 0 ? round(($hard / $total) * 100, 1) : null,
            'weak_contact_percentage' => $batting->count() > 0 ? round(($weak / $total) * 100, 1) : null,
            'swing_miss_percentage' => $batting->count() > 0 ? round((1 - ($contact / $total)) * 100, 1) : null,
            'line_drive_percentage' => $batting->count() > 0 ? round(($lineDrive / $total) * 100, 1) : null,
        ];
    }

    private function aggregateBullpen(Collection $bullpen, ?Collection $allBullpen = null): array
    {
        // Use the broader combined collection (regular + scripted) for FB velocity if provided
        $fbSource = $allBullpen ?? $bullpen;
        $velocities = $bullpen->pluck('miles_per_hour')->filter(fn ($v) => is_numeric($v) && (float) $v > 0)->map(fn ($v) => (float) $v);
        $fbVelocities = $fbSource->where('type_throw', 'FB')->pluck('miles_per_hour')->filter(fn ($v) => is_numeric($v) && (float) $v > 0)->map(fn ($v) => (float) $v);
        $total = max(1, $bullpen->count());
        $strikes = $bullpen->where('is_strike', true)->count();

        $strikePct = $bullpen->count() > 0 ? round(($strikes / $total) * 100, 1) : null;

        return [
            'avg_fb_velocity'    => $fbVelocities->count() > 0 ? round((float) $fbVelocities->avg(), 1) : null,
            'max_fb_velocity'    => $fbVelocities->count() > 0 ? round((float) $fbVelocities->max(), 1) : null,
            'avg_pitch_velocity' => $velocities->count() > 0 ? round((float) $velocities->avg(), 1) : null,
            'max_pitch_velocity' => $velocities->count() > 0 ? round((float) $velocities->max(), 1) : null,
            'strike_percentage' => $strikePct,
            'command_score' => $strikePct,
            'competitive_pitch_percentage' => $strikePct,
        ];
    }

    private function computeStrengthScore(?PlayerFitness $fitness): ?float
    {
        if (!$fitness) {
            return null;
        }

        $bw = (float) ($fitness->body_weight ?? 0);
        if ($bw <= 0) {
            return null;
        }

        $bench = (float) ($fitness->bench_press ?? 0);
        $dead = (float) ($fitness->dead_lift ?? 0);
        $backSq = (float) ($fitness->back_squat ?? 0);
        $frontSq = (float) ($fitness->front_squat ?? 0);
        $clean = (float) ($fitness->power_clean ?? 0);
        $dash40 = (float) ($fitness->yd_40_dash ?? 0);
        $dash60 = (float) ($fitness->yd_60_dash ?? 0);

        $cleanRatio = $clean > 0 ? $clean / $bw : null;
        $deadRatio = $dead > 0 ? $dead / $bw : null;
        $backRatio = $backSq > 0 ? $backSq / $bw : null;
        $frontRatio = $frontSq > 0 ? $frontSq / $bw : null;
        $benchRatio = $bench > 0 ? $bench / $bw : null;

        $cleanScore = $this->mapHigherBetter($cleanRatio, [[0.8, 30], [1.0, 55], [1.2, 78], [1.35, 90], [1.5, 100]]);
        $deadScore = $this->mapHigherBetter($deadRatio, [[1.5, 35], [2.0, 60], [2.5, 85], [3.0, 100]]);
        $backScore = $this->mapHigherBetter($backRatio, [[1.2, 35], [1.6, 60], [2.0, 82], [2.5, 100]]);
        $frontScore = $this->mapHigherBetter($frontRatio, [[1.0, 40], [1.3, 62], [1.5, 78], [2.0, 100]]);
        $benchScore = $this->mapHigherBetter($benchRatio, [[0.9, 40], [1.1, 58], [1.3, 76], [1.5, 90], [1.7, 100]]);
        $dash60Score = $this->mapLowerBetter($dash60 > 0 ? $dash60 : null, [[6.3, 100], [6.5, 92], [6.6, 84], [6.8, 70], [7.4, 30]]);
        $dash40Score = $this->mapLowerBetter($dash40 > 0 ? $dash40 : null, [[4.3, 100], [4.5, 94], [4.7, 84], [4.9, 68], [5.3, 30]]);

        $powerScore = $this->weightedAverage([
            ['value' => $cleanScore, 'weight' => 1.0],
        ], 0.0);

        $strengthScore = $this->weightedAverage([
            ['value' => $deadScore, 'weight' => 0.35],
            ['value' => $backScore, 'weight' => 0.30],
            ['value' => $frontScore, 'weight' => 0.20],
            ['value' => $benchScore, 'weight' => 0.15],
        ], 0.0);

        $speedScore = $this->weightedAverage([
            ['value' => $dash60Score, 'weight' => 0.7],
            ['value' => $dash40Score, 'weight' => 0.3],
        ], 0.0);

        $relativeStrengthScore = $this->weightedAverage([
            ['value' => $cleanScore, 'weight' => 0.6],
            ['value' => $deadScore, 'weight' => 0.4],
        ], 0.0);

        $score = $this->clamp(
            ($powerScore * 0.45)
            + ($strengthScore * 0.30)
            + ($speedScore * 0.20)
            + ($relativeStrengthScore * 0.05),
            0.0,
            100.0
        );

        return round($score, 1);
    }

    /**
     * @param array<int, array{0: float|int, 1: float|int}> $anchors
     */
    private function mapHigherBetter(?float $value, array $anchors): ?float
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        usort($anchors, fn (array $a, array $b) => (float) $a[0] <=> (float) $b[0]);

        if (count($anchors) === 0) {
            return null;
        }

        if ($value <= (float) $anchors[0][0]) {
            return $this->clamp((float) $anchors[0][1], 0.0, 100.0);
        }

        for ($i = 1; $i < count($anchors); $i++) {
            $x1 = (float) $anchors[$i][0];
            $y1 = (float) $anchors[$i][1];
            $x0 = (float) $anchors[$i - 1][0];
            $y0 = (float) $anchors[$i - 1][1];

            if ($value <= $x1) {
                return $this->clamp($this->lerp($value, $x0, $x1, $y0, $y1), 0.0, 100.0);
            }
        }

        return 100.0;
    }

    /**
     * @param array<int, array{0: float|int, 1: float|int}> $anchors
     */
    private function mapLowerBetter(?float $value, array $anchors): ?float
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        usort($anchors, fn (array $a, array $b) => (float) $a[0] <=> (float) $b[0]);

        if (count($anchors) === 0) {
            return null;
        }

        if ($value <= (float) $anchors[0][0]) {
            return $this->clamp((float) $anchors[0][1], 0.0, 100.0);
        }

        for ($i = 1; $i < count($anchors); $i++) {
            $x1 = (float) $anchors[$i][0];
            $y1 = (float) $anchors[$i][1];
            $x0 = (float) $anchors[$i - 1][0];
            $y0 = (float) $anchors[$i - 1][1];

            if ($value <= $x1) {
                return $this->clamp($this->lerp($value, $x0, $x1, $y0, $y1), 0.0, 100.0);
            }
        }

        return $this->clamp((float) $anchors[count($anchors) - 1][1], 0.0, 100.0);
    }

    private function weightedAverage(array $items, float $fallback = 0.0): float
    {
        $valid = array_values(array_filter($items, function (array $item): bool {
            return array_key_exists('value', $item)
                && $item['value'] !== null
                && is_numeric($item['value'])
                && is_finite((float) $item['value']);
        }));

        if (count($valid) === 0) {
            return $fallback;
        }

        $weightSum = array_reduce($valid, fn (float $sum, array $item): float => $sum + (float) ($item['weight'] ?? 0), 0.0);

        if ($weightSum <= 0) {
            return $fallback;
        }

        $weighted = array_reduce(
            $valid,
            fn (float $sum, array $item): float => $sum + ((float) $item['value'] * (float) ($item['weight'] ?? 0)),
            0.0
        );

        return $weighted / $weightSum;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function lerp(float $x, float $x0, float $x1, float $y0, float $y1): float
    {
        if (abs($x1 - $x0) < 0.000001) {
            return $y0;
        }

        return $y0 + (($x - $x0) / ($x1 - $x0)) * ($y1 - $y0);
    }

    private function averageAvailable(array $values): ?float
    {
        $usable = array_filter($values, fn ($value) => $value !== null);
        if (count($usable) === 0) {
            return null;
        }

        return round(array_sum($usable) / count($usable), 1);
    }

    private function deltaBlock(?float $current, ?float $previous): array
    {
        if ($current === null || $previous === null || abs($previous) < 0.0001) {
            return [
                'current' => $current,
                'previous' => $previous,
                'delta' => null,
                'changePct' => null,
                'direction' => 'flat',
            ];
        }

        $delta = $current - $previous;
        $pct = ($delta / abs($previous)) * 100;

        return [
            'current' => round($current, 1),
            'previous' => round($previous, 1),
            'delta' => round($delta, 1),
            'changePct' => round($pct, 1),
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * @param array<string, array<string, float|int|string|null>> $trend
     */
    private function computeTrendScore(array $trend): float
    {
        $score = 50;
        foreach ($trend as $row) {
            $direction = (string) ($row['direction'] ?? 'flat');
            if ($direction === 'up') {
                $score += 4;
            } elseif ($direction === 'down') {
                $score -= 4;
            }
        }

        return (float) max(0, min(100, $score));
    }

    private function computeDevelopmentIndex(
        ?float $performance,
        ?float $strength,
        ?float $mobility,
        ?float $recovery,
        float $trend
    ): float {
        $p = $performance ?? 50;
        $s = $strength ?? 50;
        $m = $mobility ?? 50;
        $r = $recovery ?? 50;

        $index = ($p * 0.40) + ($s * 0.20) + ($m * 0.15) + ($r * 0.15) + ($trend * 0.10);

        return round(max(0, min(100, $index)), 1);
    }

    private function resolveRole(int $battingRows, int $bullpenRows): string
    {
        if ($battingRows > 0 && $bullpenRows > 0) {
            return 'two-way';
        }

        if ($bullpenRows > 0) {
            return 'pitcher';
        }

        return 'hitter';
    }

    private function resolveAge(?string $bornDate): ?int
    {
        if (!$bornDate) {
            return null;
        }

        try {
            return Carbon::parse($bornDate)->age;
        } catch (Exception) {
            return null;
        }
    }

    private function resolveHeight($ft, $in): ?string
    {
        if ($ft === null && $in === null) {
            return null;
        }

        $feet = is_numeric($ft) ? (int) $ft : 0;
        $inch = is_numeric($in) ? (int) $in : 0;

        return "{$feet}'{$inch}\"";
    }

    private function latestPositiveFitnessMetric(string $playerId, string $metric): ?float
    {
        if (!in_array($metric, [
            'vertical_jump',
            'broad_jump',
            'body_weight',
            'bench_press',
            'back_squat',
            'front_squat',
            'dead_lift',
            'power_clean',
            'hand_strength',
            'med_ball_rotational_throw',
            'exit_velo',
            'bat_speed',
        ], true)) {
            return null;
        }

        $value = PlayerFitness::query()
            ->where('user_id', $playerId)
            ->whereNotNull($metric)
            ->where($metric, '>', 0)
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->value($metric);

        return $value !== null ? (float) $value : null;
    }

    private function latestPositiveAssessmentMetric(string $playerId, string $metric): ?float
    {
        if (!in_array($metric, ['vertical_jump_in', 'broad_jump_in'], true)) {
            return null;
        }

        $value = PlayerAssessment::query()
            ->where('user_id', $playerId)
            ->whereNotNull($metric)
            ->where($metric, '>', 0)
            ->orderByDesc('assessment_date')
            ->orderByDesc('created_at')
            ->value($metric);

        return $value !== null ? (float) $value : null;
    }

    private function resolveTrendLabel($current, $previous): string
    {
        if (!is_numeric($current) || !is_numeric($previous)) {
            return 'no_change';
        }

        $delta = (float) $current - (float) $previous;

        if ($delta > 0) {
            return 'improved';
        }

        if ($delta < 0) {
            return 'declined';
        }

        return 'no_change';
    }
}
