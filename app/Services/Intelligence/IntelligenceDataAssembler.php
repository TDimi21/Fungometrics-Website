<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\ArmCareSession;
use App\Models\AthleticPerformanceScore;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\CoachTeam;
use App\Models\ExitVelocityPractice;
use App\Models\LiveABPracticeResult;
use App\Models\LongTossPractice;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Team;
use App\Models\User;
use App\Models\WeightBallPractice;
use App\Services\Statistics\BattingStatisticsService;
use App\Services\Statistics\BullpenStatisticsService;
use App\Services\Statistics\CageStatisticsService;
use App\Services\Statistics\ExitVelocityStatisticsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class IntelligenceDataAssembler
{
    public function __construct(
        private readonly BattingStatisticsService $battingStatistics,
        private readonly BullpenStatisticsService $bullpenStatistics,
        private readonly CageStatisticsService $cageStatistics,
        private readonly ExitVelocityStatisticsService $exitVelocityStatistics,
    ) {
    }

    public function assembleForPlayer(string $teamId, string $playerId, int $days = 60): array
    {
        $since = now()->subDays($days);
        $last30 = now()->subDays(30);
        $prev30Start = now()->subDays(60);
        $sources = [];
        $gaps = [];

        $team = Team::query()->find($teamId);
        $user = User::query()->with(['profile', 'player', 'positions'])->find($playerId);
        $playerTeam = PlayerTeam::query()
            ->where('team_id', $teamId)
            ->where('user_id', $playerId)
            ->first();

        $this->markSource($sources, 'team', $team !== null);
        $this->markSource($sources, 'profile', $user?->profile !== null);
        $this->markSource($sources, 'player', $user?->player !== null);
        $this->markSource($sources, 'player_team', $playerTeam !== null);

        if (! $user) {
            $gaps[] = $this->gap('user', 'player_id', 'Player identity could not be loaded.', 'Confirm the player exists before generating intelligence.');
        }

        if ($user && ! $user->player?->born_date) {
            $gaps[] = $this->gap('player', 'born_date', 'Age-based benchmarks and velocity context may be less accurate.', 'Add date of birth to the player profile.');
        }

        if ($user && ! $user->player?->hit_side) {
            $gaps[] = $this->gap('player', 'hit_side', 'Pull/oppo and hitter identity signals may be less accurate.', 'Add hitter handedness to the player profile.');
        }

        if ($user && ! $user->player?->throw_side) {
            $gaps[] = $this->gap('player', 'throw_side', 'Throwing profile and pitcher context may be less accurate.', 'Add throwing hand to the player profile.');
        }

        $fitnessLatest = PlayerFitness::query()
            ->where('user_id', $playerId)
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->first();

        $fitnessPrevious = PlayerFitness::query()
            ->where('user_id', $playerId)
            ->when($fitnessLatest, fn ($query) => $query->where('id', '!=', $fitnessLatest->id))
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->first();

        $assessmentLatest = PlayerAssessment::query()
            ->where('user_id', $playerId)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)->orWhereNull('team_id');
            })
            ->orderByDesc('assessment_date')
            ->orderByDesc('created_at')
            ->first();

        $athleticLatest = AthleticPerformanceScore::query()
            ->where('player_id', $playerId)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)->orWhereNull('team_id');
            })
            ->orderByDesc('calculated_at')
            ->orderByDesc('created_at')
            ->first();

        $athleticPrevious = AthleticPerformanceScore::query()
            ->where('player_id', $playerId)
            ->when($athleticLatest, fn ($query) => $query->where('id', '!=', $athleticLatest->id))
            ->orderByDesc('calculated_at')
            ->orderByDesc('created_at')
            ->first();

        $this->markSource($sources, 'player_fitness', $fitnessLatest !== null);
        $this->markSource($sources, 'player_assessment', $assessmentLatest !== null);
        $this->markSource($sources, 'athletic_performance_score', $athleticLatest !== null);

        if (! $fitnessLatest) {
            $gaps[] = $this->gap('player_fitness', 'latest_fitness', 'Strength, recovery, sleep, and athletic trend signals are limited.', 'Log a player fitness or recovery entry.');
        }

        if (! $assessmentLatest) {
            $gaps[] = $this->gap('player_assessment', 'latest_assessment', 'Baseline development and assessment-driven context are limited.', 'Complete or sync a player assessment.');
        }

        $batting = BattingPracticeResult::query()
            ->where('team_id', $teamId)
            ->where('batter_id', $playerId)
            ->where('created_at', '>=', $since)
            ->get();

        $bullpen = BullpenPracticeResult::query()
            ->where('team_id', $teamId)
            ->where('pitcher_id', $playerId)
            ->where('created_at', '>=', $since)
            ->get();

        $liveAb = LiveABPracticeResult::query()
            ->whereHas('practice', fn ($query) => $query->where('team_id', $teamId))
            ->where(function ($query) use ($playerId) {
                $query->whereHas('batting', fn ($q) => $q->where('batter_id', $playerId))
                    ->orWhereHas('pitching', fn ($q) => $q->where('pitcher_id', $playerId));
            })
            ->where('created_at', '>=', $since)
            ->get();

        $cage = CagePracticeResult::query()
            ->where('team_id', $teamId)
            ->where('user_id', $playerId)
            ->where('created_at', '>=', $since)
            ->get();

        $weightedBall = WeightBallPractice::query()
            ->where('user_id', $playerId)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)->orWhereNull('team_id');
            })
            ->where('created_at', '>=', $since)
            ->get();

        $exitVelocity = ExitVelocityPractice::query()
            ->where('user_id', $playerId)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)->orWhereNull('team_id');
            })
            ->where('created_at', '>=', $since)
            ->get();

        $longToss = LongTossPractice::query()
            ->where('user_id', $playerId)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)->orWhereNull('team_id');
            })
            ->where('created_at', '>=', $since)
            ->get();

        $armCare = $this->armCareRows($teamId, $playerId, $since);
        if (! Schema::hasTable('arm_care_sessions')) {
            $gaps[] = $this->gap('arm_care_sessions', 'table', 'Arm care intelligence is unavailable in this database.', 'Run the arm care migration before using arm care intelligence.');
        }

        $practices = Practice::query()
            ->where('team_id', $teamId)
            ->where('created_at', '>=', $since)
            ->where(function ($query) use ($playerId) {
                $query->where('user_id', $playerId)
                    ->orWhereHas('lineup', fn ($q) => $q->where('user_id', $playerId));
            })
            ->get();

        $lineups = PracticeLineUp::query()
            ->where('user_id', $playerId)
            ->whereHas('practice', fn ($query) => $query->where('team_id', $teamId)->where('created_at', '>=', $since))
            ->get();

        $this->markSource($sources, 'practices', $practices->isNotEmpty());
        $this->markSource($sources, 'practice_lineups', $lineups->isNotEmpty());
        $this->markSource($sources, 'batting_practice_results', $batting->isNotEmpty());
        $this->markSource($sources, 'bullpen_practice_results', $bullpen->isNotEmpty());
        $this->markSource($sources, 'liveab_practice_results', $liveAb->isNotEmpty());
        $this->markSource($sources, 'cage_practice_results', $cage->isNotEmpty());
        $this->markSource($sources, 'weight_ball_practices', $weightedBall->isNotEmpty());
        $this->markSource($sources, 'exit_velocity_practices', $exitVelocity->isNotEmpty());
        $this->markSource($sources, 'long_toss_practices', $longToss->isNotEmpty());
        $this->markSource($sources, 'arm_care_sessions', $armCare->isNotEmpty());

        foreach ([
            ['batting_practice_results', 'recent_bp_data', $batting->isEmpty(), 'BP intelligence is limited without recent batting data.', 'Score a BP session for this player.'],
            ['bullpen_practice_results', 'recent_bullpen_data', $bullpen->isEmpty(), 'Pitching intelligence is limited without recent bullpen data.', 'Score a bullpen session for this player.'],
            ['weight_ball_practices', 'recent_weighted_ball_data', $weightedBall->isEmpty(), 'Weighted ball transfer and workload signals are limited.', 'Score a weighted ball session for this player.'],
            ['long_toss_practices', 'recent_long_toss_data', $longToss->isEmpty(), 'Throwing distance and carry signals are limited.', 'Score a long toss session for this player.'],
            ['exit_velocity_practices', 'recent_exit_velocity_data', $exitVelocity->isEmpty(), 'Training EV profile signals are limited.', 'Score an exit velocity session for this player.'],
            ['cage_practice_results', 'recent_cage_data', $cage->isEmpty(), 'Cage ball-flight and contact quality signals are limited.', 'Score a cage session for this player.'],
        ] as [$source, $field, $missing, $impact, $action]) {
            if ($missing) {
                $gaps[] = $this->gap($source, $field, $impact, $action);
            }
        }

        return [
            'player_context' => $this->playerContext($user, $playerTeam),
            'team_context' => $this->teamContext($team),
            'assessment_summary' => $this->assessmentSummary($assessmentLatest),
            'physical_development' => $this->physicalSummary($fitnessLatest, $fitnessPrevious, $athleticLatest, $athleticPrevious),
            'batting_summary' => $this->battingSummary($batting),
            'bullpen_summary' => $this->bullpenSummary($bullpen),
            'liveab_summary' => $this->liveAbSummary($liveAb),
            'cage_summary' => $this->cageSummary($cage),
            'weighted_ball_summary' => $this->weightedBallSummary($weightedBall),
            'exit_velocity_summary' => $this->exitVelocitySummary($exitVelocity),
            'long_toss_summary' => $this->longTossSummary($longToss),
            'arm_care_summary' => $this->armCareSummary($armCare),
            'session_summary' => $this->sessionSummary($practices, $lineups),
            'trend_blocks' => $this->trendBlocks($batting, $bullpen, $cage, $exitVelocity, $weightedBall, $longToss, $last30, $prev30Start),
            'data_sources_used' => array_values(array_unique($sources)),
            'data_gaps' => $gaps,
        ];
    }

    public function assembleForTeam(string $teamId, int $days = 60): array
    {
        $team = Team::query()->find($teamId);
        $playerIds = PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $coachCount = CoachTeam::query()->where('team_id', $teamId)->count();
        $sources = [];
        $gaps = [];

        $this->markSource($sources, 'team', $team !== null);
        $this->markSource($sources, 'player_team', $playerIds->isNotEmpty());
        $this->markSource($sources, 'coach_team', $coachCount > 0);

        if (! $team) {
            $gaps[] = $this->gap('team', 'team_id', 'Team identity could not be loaded.', 'Confirm the team exists before generating intelligence.');
        }

        if ($playerIds->isEmpty()) {
            $gaps[] = $this->gap('player_team', 'roster', 'Team intelligence has no players to evaluate.', 'Add players to the team roster.');
        }

        $players = $playerIds->map(fn (string $playerId) => $this->assembleForPlayer($teamId, $playerId, $days))->values()->all();

        foreach ($players as $player) {
            foreach ($player['data_sources_used'] ?? [] as $source) {
                $sources[] = $source;
            }
            foreach ($player['data_gaps'] ?? [] as $gap) {
                $gaps[] = $gap;
            }
        }

        return [
            'team_context' => $this->teamContext($team) + ['coach_count' => $coachCount],
            'roster_count' => $playerIds->count(),
            'players_assembled' => $players,
            'data_sources_used' => array_values(array_unique($sources)),
            'data_gaps' => $gaps,
        ];
    }

    private function playerContext(?User $user, ?PlayerTeam $playerTeam): array
    {
        return [
            'id' => $user?->id,
            'name' => $user?->profile ? trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? '')) : null,
            'first_name' => $user?->profile?->first_name,
            'last_name' => $user?->profile?->last_name,
            'level' => $user?->profile?->level,
            'positions' => $user?->positions?->pluck('position')->filter()->values()->all() ?? [],
            'height_ft' => $user?->player?->height_in_ft,
            'height_in' => $user?->player?->height_in_inch,
            'jersey' => $user?->player?->number_in_shirt,
            'born_date' => $user?->player?->born_date,
            'age' => $this->ageFromDate($user?->player?->born_date),
            'grad_year' => $user?->player?->grad_year,
            'hit_side' => $user?->player?->hit_side,
            'throw_side' => $user?->player?->throw_side,
            'team_id' => $playerTeam?->team_id,
        ];
    }

    private function teamContext(?Team $team): array
    {
        return [
            'id' => $team?->id,
            'name' => $team?->name,
            'state' => $team?->state,
            'zip' => $team?->zip,
        ];
    }

    private function assessmentSummary(?PlayerAssessment $assessment): array
    {
        $pitchingData = is_array($assessment?->pitching_data) ? $assessment->pitching_data : [];
        $hittingData = is_array($assessment?->hitting_data) ? $assessment->hitting_data : [];

        return [
            'id' => $assessment?->id,
            'assessment_date' => $assessment?->assessment_date?->toDateString(),
            'overall_score' => $this->numberOrNull($assessment?->overall_score),
            'strength_overall_score' => $this->numberOrNull($assessment?->strength_overall_score),
            'mobility_overall_score' => $this->numberOrNull($assessment?->mobility_overall_score),
            'hitting_score' => $this->numberOrNull($assessment?->hitting_score),
            'pitching_score' => $this->numberOrNull($assessment?->pitching_score),
            'throwing_workload_score' => $this->numberOrNull($assessment?->throwing_workload_score),
            'throwing_workload_level' => $assessment?->throwing_workload_level,
            'arm_health_score' => $this->numberOrNull($assessment?->arm_health_score),
            'baseline_pitch_velocity' => $this->firstNestedNumber($pitchingData, [
                'pitch_velo',
                'pitch_velocity',
                'fastball_velocity',
                'fb_velocity',
                'max_fb_velocity',
                'avg_fb_velocity',
                'velocity',
                'miles_per_hour',
            ]),
            'baseline_exit_velocity' => $this->firstNestedNumber($hittingData, [
                'exit_velocity',
                'max_exit_velocity',
                'avg_exit_velocity',
                'ev',
                'velocity',
            ]),
            'bench_press' => $this->numberOrNull($assessment?->bench_lbs),
            'squat' => $this->numberOrNull($assessment?->squat_lbs),
            'deadlift' => $this->numberOrNull($assessment?->deadlift_lbs),
            'broad_jump' => $this->numberOrNull($assessment?->broad_jump_in),
            'vertical_jump' => $this->numberOrNull($assessment?->vertical_jump_in),
            'sprint_10yd' => $this->numberOrNull($assessment?->sprint_10yd_sec),
            'body_weight' => $this->numberOrNull($assessment?->body_weight_lbs),
            'hip_mobility_score' => $this->numberOrNull($assessment?->hip_mobility),
            'shoulder_mobility_score' => $this->numberOrNull($assessment?->shoulder_mobility),
            'ankle_mobility_score' => $this->numberOrNull($assessment?->ankle_mobility),
            'hip_flexor_mobility_score' => $this->numberOrNull($assessment?->hip_flexor_mobility),
            't_spine_mobility_score' => $this->numberOrNull($assessment?->rotational_mobility),
            'pitching_data' => $pitchingData,
            'hitting_data' => $hittingData,
            'overall_team_percentile' => $this->numberOrNull($assessment?->overall_team_percentile),
            'overall_age_percentile' => $this->numberOrNull($assessment?->overall_age_percentile),
        ];
    }

    private function physicalSummary(?PlayerFitness $latest, ?PlayerFitness $previous, ?AthleticPerformanceScore $athleticLatest, ?AthleticPerformanceScore $athleticPrevious): array
    {
        return [
            'latest_fitness_date' => $latest?->fitness_date?->toDateString(),
            'body_weight' => $this->numberOrNull($latest?->body_weight),
            'bench_press' => $this->numberOrNull($latest?->bench_press),
            'front_squat' => $this->numberOrNull($latest?->front_squat),
            'back_squat' => $this->numberOrNull($latest?->back_squat),
            'squat' => max(array_filter([
                $this->numberOrNull($latest?->back_squat),
                $this->numberOrNull($latest?->front_squat),
            ], fn ($value) => $value !== null) ?: [null]),
            'deadlift' => $this->numberOrNull($latest?->dead_lift),
            'pull_ups' => $this->numberOrNull($latest?->pull_ups),
            'pushups' => $this->numberOrNull($latest?->push_ups),
            'vertical_jump' => $this->numberOrNull($latest?->vertical_jump),
            'broad_jump' => $this->numberOrNull($latest?->broad_jump),
            '40_yard_dash' => $this->numberOrNull($latest?->yd_40_dash),
            '60_yard_dash' => $this->numberOrNull($latest?->yd_60_dash),
            'exit_velocity' => $this->numberOrNull($latest?->exit_velo),
            'sleep_hours' => $this->numberOrNull($latest?->sleep_hours),
            'sleep_quality_1_to_5' => $this->numberOrNull($latest?->sleep_quality_1_to_5),
            'recovery_score' => $this->numberOrNull($latest?->recovery_score),
            'mobility_score' => $this->numberOrNull($latest?->mobility_score),
            'strength_score' => $this->numberOrNull($athleticLatest?->strength_score ?? $latest?->strength_score),
            'overall_api_score' => $this->numberOrNull($athleticLatest?->overall_api_score ?? $latest?->overall_api_score),
            'pitch_velocity' => $this->numberOrNull($latest?->pitch_velo),
            'grade_label' => $athleticLatest?->grade_label,
            'projection_label' => $athleticLatest?->projection_label,
            'team_percentile' => $this->numberOrNull($athleticLatest?->team_percentile),
            'team_rank' => $this->numberOrNull($athleticLatest?->team_rank),
            'team_count' => $this->numberOrNull($athleticLatest?->team_count),
            'strengths' => $athleticLatest?->strengths ?? [],
            'weaknesses' => $athleticLatest?->weaknesses ?? [],
            'development_plan' => $athleticLatest?->development_plan ?? [],
            'trend' => [
                'overall_api_score' => $this->delta($this->numberOrNull($athleticLatest?->overall_api_score), $this->numberOrNull($athleticPrevious?->overall_api_score)),
                'strength_score' => $this->delta($this->numberOrNull($athleticLatest?->strength_score), $this->numberOrNull($athleticPrevious?->strength_score)),
                'recovery_score' => $this->delta($this->numberOrNull($latest?->recovery_score), $this->numberOrNull($previous?->recovery_score)),
                'mobility_score' => $this->delta($this->numberOrNull($latest?->mobility_score), $this->numberOrNull($previous?->mobility_score)),
            ],
        ];
    }

    private function battingSummary(Collection $rows): array
    {
        $stats = $rows->isNotEmpty() ? $this->battingStatistics->fps($rows) : [];
        $evRows = $rows->filter(fn ($row) => $this->positiveNumber($row->velocity) !== null);

        return [
            'result_count' => $rows->count(),
            'score' => $this->numberOrNull($stats['fps'] ?? null),
            'score_breakdown' => $stats,
            'avg_exit_velocity' => $evRows->isNotEmpty() ? round((float) $evRows->avg('velocity'), 1) : null,
            'max_exit_velocity' => $evRows->isNotEmpty() ? round((float) $evRows->max('velocity'), 1) : null,
        ];
    }

    private function bullpenSummary(Collection $rows): array
    {
        $stats = $rows->isNotEmpty() ? $this->bullpenStatistics->bps($rows) : [];
        $veloRows = $rows->filter(fn ($row) => $this->positiveNumber($row->miles_per_hour) !== null);

        return [
            'result_count' => $rows->count(),
            'score' => $this->numberOrNull($stats['bps'] ?? null),
            'score_breakdown' => $stats,
            'avg_pitch_velocity' => $veloRows->isNotEmpty() ? round((float) $veloRows->avg('miles_per_hour'), 1) : null,
            'max_pitch_velocity' => $veloRows->isNotEmpty() ? round((float) $veloRows->max('miles_per_hour'), 1) : null,
            'strike_rate' => $this->numberOrNull($stats['strikeRate'] ?? null),
        ];
    }

    private function liveAbSummary(Collection $rows): array
    {
        return [
            'result_count' => $rows->count(),
            'runs_scored' => $rows->isNotEmpty() ? (int) $rows->sum('runs_scored') : null,
            'rbi' => $rows->isNotEmpty() ? (int) $rows->sum('rbi') : null,
            'outs_recorded' => $rows->isNotEmpty() ? (int) $rows->sum('outs_recorded') : null,
        ];
    }

    private function cageSummary(Collection $rows): array
    {
        $stats = $rows->isNotEmpty() ? $this->cageStatistics->fcs($rows) : [];

        return [
            'result_count' => $rows->count(),
            'score' => $this->numberOrNull($stats['fcs'] ?? null),
            'score_breakdown' => $stats,
            'avg_exit_velocity' => $this->numberOrNull($stats['avgEV'] ?? null),
            'max_exit_velocity' => $this->numberOrNull($stats['maxEV'] ?? null),
            'avg_distance' => $this->numberOrNull($stats['avgDist'] ?? null),
        ];
    }

    private function weightedBallSummary(Collection $rows): array
    {
        $byWeight = $rows
            ->filter(fn ($row) => $this->positiveNumber($row->weight) !== null && $this->positiveNumber($row->velocity) !== null)
            ->groupBy(fn ($row) => (string) (float) $row->weight)
            ->map(function (Collection $weightRows, string $weight) {
                return [
                    'weight' => (float) $weight,
                    'throws' => $weightRows->count(),
                    'avg_velocity' => round((float) $weightRows->avg('velocity'), 1),
                    'max_velocity' => round((float) $weightRows->max('velocity'), 1),
                ];
            })
            ->sortBy('weight')
            ->values()
            ->all();

        $byWeightMap = collect($byWeight)->keyBy(fn (array $row) => (string) (float) $row['weight']);
        $weight3 = $byWeightMap->get('3');
        $weight5 = $byWeightMap->get('5');
        $weight7 = $byWeightMap->get('7');
        $max3 = $this->numberOrNull($weight3['max_velocity'] ?? null);
        $max5 = $this->numberOrNull($weight5['max_velocity'] ?? null);
        $max7 = $this->numberOrNull($weight7['max_velocity'] ?? null);
        $avg5 = $this->numberOrNull($weight5['avg_velocity'] ?? null);

        return [
            'result_count' => $rows->count(),
            'velocity_by_weight' => $byWeight,
            'max_velocity' => count($byWeight) ? max(array_column($byWeight, 'max_velocity')) : null,
            'five_oz_avg_velocity' => $avg5,
            'five_oz_max_velocity' => $max5,
            'velocity_ratio_5_to_3' => $max5 !== null && $max3 !== null && $max3 > 0 ? round($max5 / $max3, 3) : null,
            'speed_reserve_3_to_5' => $max3 !== null && $max5 !== null ? round($max3 - $max5, 1) : null,
            'strength_reserve_5_to_7' => $max5 !== null && $max7 !== null ? round($max5 - $max7, 1) : null,
            'force_drop_off_per_oz' => $max3 !== null && $max7 !== null ? round(($max3 - $max7) / 4, 1) : null,
            'profile_label' => $this->weightedBallProfileLabel($max3, $max5, $max7),
            'total_throws' => $rows->count() ?: null,
        ];
    }

    private function exitVelocitySummary(Collection $rows): array
    {
        $stats = $rows->isNotEmpty() ? $this->exitVelocityStatistics->evs($rows) : [];
        $veloRows = $rows->filter(fn ($row) => $this->positiveNumber($row->velocity) !== null);

        return [
            'result_count' => $rows->count(),
            'score' => $this->numberOrNull($stats['evs'] ?? null),
            'score_breakdown' => $stats,
            'avg_exit_velocity' => $veloRows->isNotEmpty() ? round((float) $veloRows->avg('velocity'), 1) : null,
            'max_exit_velocity' => $veloRows->isNotEmpty() ? round((float) $veloRows->max('velocity'), 1) : null,
        ];
    }

    private function longTossSummary(Collection $rows): array
    {
        $distanceRows = $rows->filter(fn ($row) => $this->positiveNumber($row->distance) !== null);

        return [
            'result_count' => $rows->count(),
            'avg_distance' => $distanceRows->isNotEmpty() ? round((float) $distanceRows->avg('distance'), 1) : null,
            'max_distance' => $distanceRows->isNotEmpty() ? round((float) $distanceRows->max('distance'), 1) : null,
            'by_hops' => $distanceRows->groupBy('hop')->map(fn (Collection $hopRows) => [
                'throws' => $hopRows->count(),
                'avg_distance' => round((float) $hopRows->avg('distance'), 1),
                'max_distance' => round((float) $hopRows->max('distance'), 1),
            ])->all(),
        ];
    }

    private function armCareSummary(Collection $rows): array
    {
        return [
            'session_count' => $rows->count(),
            'latest_session_at' => $rows->max('created_at')?->toDateTimeString(),
        ];
    }

    private function sessionSummary(Collection $practices, Collection $lineups): array
    {
        return [
            'practice_count' => $practices->count(),
            'lineup_entries' => $lineups->count(),
            'completed_practices' => $practices->where('is_completed', true)->count(),
            'types' => $practices->pluck('type')->filter()->countBy()->all(),
        ];
    }

    private function trendBlocks(Collection $batting, Collection $bullpen, Collection $cage, Collection $exitVelocity, Collection $weightedBall, Collection $longToss, Carbon $last30, Carbon $prev30Start): array
    {
        return [
            'batting_avg_ev' => $this->collectionTrend($batting, 'velocity', $last30, $prev30Start),
            'bullpen_avg_velocity' => $this->collectionTrend($bullpen, 'miles_per_hour', $last30, $prev30Start),
            'cage_avg_ev' => $this->collectionTrend($cage, 'launch_angle_velocity', $last30, $prev30Start),
            'exit_velocity_avg' => $this->collectionTrend($exitVelocity, 'velocity', $last30, $prev30Start),
            'weighted_ball_avg_velocity' => $this->collectionTrend($weightedBall, 'velocity', $last30, $prev30Start),
            'long_toss_avg_distance' => $this->collectionTrend($longToss, 'distance', $last30, $prev30Start),
        ];
    }

    private function collectionTrend(Collection $rows, string $field, Carbon $last30, Carbon $prev30Start): array
    {
        $current = $rows
            ->filter(fn ($row) => $row->created_at && $row->created_at->gte($last30))
            ->map(fn ($row) => $this->positiveNumber($row->{$field} ?? null))
            ->filter(fn ($value) => $value !== null);

        $previous = $rows
            ->filter(fn ($row) => $row->created_at && $row->created_at->lt($last30) && $row->created_at->gte($prev30Start))
            ->map(fn ($row) => $this->positiveNumber($row->{$field} ?? null))
            ->filter(fn ($value) => $value !== null);

        $currentAvg = $current->isNotEmpty() ? round((float) $current->avg(), 1) : null;
        $previousAvg = $previous->isNotEmpty() ? round((float) $previous->avg(), 1) : null;

        return $this->delta($currentAvg, $previousAvg) + [
            'current_count' => $current->count(),
            'previous_count' => $previous->count(),
        ];
    }

    private function armCareRows(string $teamId, string $playerId, Carbon $since): Collection
    {
        if (! class_exists(ArmCareSession::class) || ! Schema::hasTable('arm_care_sessions')) {
            return collect();
        }

        return ArmCareSession::query()
            ->where('user_id', $playerId)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)->orWhereNull('team_id');
            })
            ->where('created_at', '>=', $since)
            ->get();
    }

    private function delta(?float $current, ?float $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $current !== null && $previous !== null ? round($current - $previous, 1) : null,
            'direction' => $current === null || $previous === null ? null : ($current > $previous ? 'up' : ($current < $previous ? 'down' : 'flat')),
        ];
    }

    private function markSource(array &$sources, string $source, bool $present): void
    {
        if ($present) {
            $sources[] = $source;
        }
    }

    private function gap(string $source, string $missingField, string $impact, string $action): array
    {
        return [
            'source' => $source,
            'missing_field' => $missingField,
            'impact' => $impact,
            'recommended_collection_action' => $action,
        ];
    }

    private function ageFromDate(mixed $date): ?int
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse((string) $date)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function positiveNumber(mixed $value): ?float
    {
        $number = $this->numberOrNull($value);

        return $number !== null && $number > 0 ? $number : null;
    }

    private function firstNestedNumber(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $this->findNestedValue($data, $key);
            $number = $this->positiveNumber($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function findNestedValue(array $data, string $targetKey): mixed
    {
        foreach ($data as $key => $value) {
            if (strtolower((string) $key) === strtolower($targetKey)) {
                return $value;
            }

            if (is_array($value)) {
                $nested = $this->findNestedValue($value, $targetKey);
                if ($nested !== null && $nested !== '') {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function weightedBallProfileLabel(?float $max3, ?float $max5, ?float $max7): ?string
    {
        if ($max3 === null || $max5 === null || $max7 === null) {
            return null;
        }

        $speedReserve = $max3 - $max5;
        $strengthReserve = $max5 - $max7;

        if ($speedReserve >= 7 && $strengthReserve >= 7) {
            return 'Irregular Spectrum';
        }

        if ($speedReserve >= 7) {
            return 'Speed Dominant';
        }

        if ($strengthReserve <= 4) {
            return 'Strength Dominant';
        }

        if (abs($speedReserve - $strengthReserve) <= 2) {
            return 'Balanced Power Profile';
        }

        return 'Developing Spectrum';
    }
}
