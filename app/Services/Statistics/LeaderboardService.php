<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\AthleticPerformanceScore;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Builds the single, rotating FMTRX Hall of Fame leaderboard payload.
 *
 * All twelve categories are calculated on the server from team-scoped records.
 * Null means FMTRX does not have an authoritative source for that measurement;
 * the client deliberately renders an em dash rather than inventing a value.
 */
final class LeaderboardService
{
    private const CATEGORIES = [
        ['key' => 'hitter', 'label' => 'Top Hitter', 'icon' => '⚾', 'color' => '#ef4444', 'metric' => 'hitter_score', 'unit' => '', 'bigLabel' => 'Hitting Score', 'sub' => [['Avg EV', 'avg_ev', 'mph'], ['Max EV', 'max_ev', 'mph'], ['Hard Hit', 'hard_hit_pct', '%'], ['Barrel', 'barrel_pct', '%'], ['Sweet Spot', 'sweet_spot_pct', '%'], ['Line Drive', 'line_drive_pct', '%']]],
        ['key' => 'pitcher', 'label' => 'Top Pitcher', 'icon' => '◆', 'color' => '#3b82f6', 'metric' => 'pitcher_score', 'unit' => '', 'bigLabel' => 'Pitching Score', 'sub' => [['Avg FB', 'avg_fb', 'mph'], ['Top FB', 'max_fb', 'mph'], ['Strike', 'strike_pct', '%'], ['Swing Miss', 'swing_miss_pct', '%'], ['WHIP', 'whip', ''], ['Command', 'command_score', '']]],
        ['key' => 'avg_ev', 'label' => 'Top Average Exit Velocity', 'icon' => '↗', 'color' => '#22c55e', 'metric' => 'avg_ev', 'unit' => 'mph', 'bigLabel' => 'Average Exit Velocity', 'sub' => [['Max EV', 'max_ev', 'mph'], ['Hard Hit', 'hard_hit_pct', '%'], ['Sweet Spot', 'sweet_spot_pct', '%'], ['Line Drive', 'line_drive_pct', '%']]],
        ['key' => 'max_ev', 'label' => 'Top Max Exit Velocity', 'icon' => '⚡', 'color' => '#16a34a', 'metric' => 'max_ev', 'unit' => 'mph', 'bigLabel' => 'Max Exit Velocity', 'sub' => [['Avg EV', 'avg_ev', 'mph'], ['Hard Hit', 'hard_hit_pct', '%'], ['Sweet Spot', 'sweet_spot_pct', '%']]],
        ['key' => 'avg_fb', 'label' => 'Top Average Fastball Velocity', 'icon' => '◎', 'color' => '#38bdf8', 'metric' => 'avg_fb', 'unit' => 'mph', 'bigLabel' => 'Average Fastball', 'sub' => [['Top FB', 'max_fb', 'mph'], ['Strike', 'strike_pct', '%'], ['Command', 'command_score', '']]],
        ['key' => 'max_fb', 'label' => 'Top Max Fastball Velocity', 'icon' => '⚡', 'color' => '#2563eb', 'metric' => 'max_fb', 'unit' => 'mph', 'bigLabel' => 'Max Fastball', 'sub' => [['Avg FB', 'avg_fb', 'mph'], ['Strike', 'strike_pct', '%'], ['Command', 'command_score', '']]],
        ['key' => 'bullpen', 'label' => 'Top Bullpen Score', 'icon' => '◈', 'color' => '#6366f1', 'metric' => 'bullpen_score', 'unit' => '', 'bigLabel' => 'Bullpen Score', 'sub' => [['Avg FB', 'avg_fb', 'mph'], ['Top FB', 'max_fb', 'mph'], ['Strike', 'strike_pct', '%'], ['Pitch Mix', 'pitch_mix_score', ''], ['First Pitch', 'first_pitch_score', '%'], ['Command', 'command_score', '']]],
        ['key' => 'cage', 'label' => 'Top Cage Score', 'icon' => '▦', 'color' => '#14b8a6', 'metric' => 'cage_score', 'unit' => '', 'bigLabel' => 'Cage Score', 'sub' => [['Avg EV', 'cage_avg_ev', 'mph'], ['Max EV', 'cage_max_ev', 'mph'], ['Avg Distance', 'cage_avg_distance', 'ft'], ['Sweet Spot', 'sweet_spot_pct', '%'], ['Line Drive', 'cage_line_drive_pct', '%']]],
        ['key' => 'long_toss', 'label' => 'Top Long Toss', 'icon' => '➤', 'color' => '#f59e0b', 'metric' => 'long_toss_score', 'unit' => '', 'bigLabel' => 'Long Toss Score', 'sub' => [['Max Carry', 'long_toss_max', 'ft'], ['Average Carry', 'long_toss_avg', 'ft'], ['Zero-Hop', 'zero_hop_pct', '%'], ['Carry Consistency', 'long_toss_consistency', '%'], ['Carry Efficiency', 'carry_efficiency', '%'], ['Average Hops', 'average_hops', ''], ['30-Day Growth', 'long_toss_growth', 'ft'], ['Arm Endurance', 'arm_endurance', ''], ['Velocity Transfer', 'velocity_transfer', '%']]],
        ['key' => 'strength', 'label' => 'Top Strength Score', 'icon' => '✦', 'color' => '#06b6d4', 'metric' => 'strength_score', 'unit' => '', 'bigLabel' => 'Strength Score', 'sub' => [['Bench', 'bench_press', 'lb'], ['Squat', 'squat', 'lb'], ['Deadlift', 'dead_lift', 'lb'], ['Power Clean', 'power_clean', 'lb'], ['Grip', 'hand_strength', 'lb'], ['Rotational Power', 'rotational_power', 'ft']]],
        ['key' => 'mobility', 'label' => 'Top Mobility Score', 'icon' => '◇', 'color' => '#a78bfa', 'metric' => 'mobility_score', 'unit' => '', 'bigLabel' => 'Mobility Score', 'sub' => [['Hip', 'hip_mobility', ''], ['Shoulder', 'shoulder_mobility', ''], ['T-Spine', 't_spine_mobility', ''], ['Hamstring', 'hamstring_mobility', ''], ['Ankle', 'ankle_mobility', '']]],
        ['key' => 'recovery', 'label' => 'Top Recovery Score', 'icon' => '☀', 'color' => '#fb923c', 'metric' => 'recovery_score', 'unit' => '', 'bigLabel' => 'Recovery Score', 'sub' => [['Sleep', 'sleep_hours', 'hrs'], ['Readiness', 'readiness_score', ''], ['Arm Care', 'arm_health_score', ''], ['Soreness', 'soreness_score', ''], ['Workload', 'workload_score', '']]],
    ];

    public function __construct(
        private readonly BattingStatisticsService $battingStatistics,
        private readonly BullpenStatisticsService $bullpenStatistics,
        private readonly CageStatisticsService $cageStatistics,
    ) {
    }

    public function forTeam(string $teamId, int $range = 0): array
    {
        $range = in_array($range, [0, 3, 6, 12], true) ? $range : 0;
        $days = match ($range) {
            3 => 7,
            6 => 30,
            12 => 365,
            default => null,
        };

        $playerIds = PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(static fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $teamName = Team::query()->whereKey($teamId)->value('name');
        if ([] === $playerIds) {
            return $this->response($teamId, $teamName, $range, $days, []);
        }

        $now = Carbon::now();
        $queryStart = null === $days ? null : $now->copy()->subDays($days * 2);
        $data = $this->loadTeamData($teamId, $playerIds, $queryStart);
        $players = $this->buildPlayers($playerIds, $teamName, $data, $now, $days);

        $categories = array_map(
            fn (array $category) => $this->buildCategory($category, $players),
            self::CATEGORIES,
        );

        return $this->response($teamId, $teamName, $range, $days, $categories);
    }

    private function response(string $teamId, ?string $teamName, int $range, ?int $days, array $categories): array
    {
        if ([] === $categories) {
            $categories = array_map(fn (array $category) => $this->emptyCategory($category), self::CATEGORIES);
        }

        return [
            'team' => ['id' => $teamId, 'name' => $teamName],
            'range' => ['value' => $range, 'days' => $days, 'label' => null === $days ? 'All time' : "Last {$days} days"],
            'generated_at' => Carbon::now()->toIso8601String(),
            'categories' => $categories,
        ];
    }

    private function loadTeamData(string $teamId, array $playerIds, ?Carbon $queryStart): array
    {
        $dateFilter = static function ($query, string $column = 'created_at') use ($queryStart): void {
            if (null !== $queryStart) {
                $query->where($column, '>=', $queryStart);
            }
        };

        $batting = BattingPracticeResult::query()->where('team_id', $teamId)->whereIn('batter_id', $playerIds);
        $dateFilter($batting);
        $bullpen = BullpenPracticeResult::query()->where('team_id', $teamId)->whereIn('pitcher_id', $playerIds);
        $dateFilter($bullpen);
        $cage = CagePracticeResult::query()->where('team_id', $teamId)->whereIn('user_id', $playerIds);
        $dateFilter($cage);
        $exitVelocity = ExitVelocityPractice::query()->where('team_id', $teamId)->whereIn('user_id', $playerIds);
        $dateFilter($exitVelocity);
        $longToss = LongTossPractice::query()->where('team_id', $teamId)->whereIn('user_id', $playerIds);
        $dateFilter($longToss);
        $fitness = PlayerFitness::query()->whereIn('user_id', $playerIds);
        $dateFilter($fitness, 'fitness_date');
        $assessments = PlayerAssessment::query()->where('team_id', $teamId)->whereIn('user_id', $playerIds);
        $dateFilter($assessments, 'assessment_date');
        $athletic = AthleticPerformanceScore::query()->where('team_id', $teamId)->whereIn('player_id', $playerIds);
        $dateFilter($athletic, 'calculated_at');

        return [
            'users' => User::query()->whereIn('id', $playerIds)->with(['profile', 'player', 'positions'])->get()->keyBy(fn (User $user) => (string) $user->id),
            'batting' => $batting->get()->groupBy(fn ($row) => (string) $row->batter_id),
            'bullpen' => $bullpen->get()->groupBy(fn ($row) => (string) $row->pitcher_id),
            'cage' => $cage->get()->groupBy(fn ($row) => (string) $row->user_id),
            'exit_velocity' => $exitVelocity->get()->groupBy(fn ($row) => (string) $row->user_id),
            'long_toss' => $longToss->get()->groupBy(fn ($row) => (string) $row->user_id),
            'fitness' => $fitness->get()->groupBy(fn ($row) => (string) $row->user_id),
            'assessments' => $assessments->get()->groupBy(fn ($row) => (string) $row->user_id),
            'athletic' => $athletic->get()->groupBy(fn ($row) => (string) $row->player_id),
        ];
    }

    private function buildPlayers(array $playerIds, ?string $teamName, array $data, Carbon $now, ?int $days): array
    {
        $currentStart = null === $days ? null : $now->copy()->subDays($days);
        $previousStart = null === $days ? null : $now->copy()->subDays($days * 2);
        $previousEnd = null === $days ? $now->copy()->subDays(30) : $currentStart;
        $players = [];

        foreach ($playerIds as $playerId) {
            /** @var User|null $user */
            $user = $data['users']->get($playerId);
            if ( ! $user) {
                continue;
            }

            $all = [];
            foreach (['batting', 'bullpen', 'cage', 'exit_velocity', 'long_toss', 'fitness', 'assessments', 'athletic'] as $key) {
                $all[$key] = collect($data[$key]->get($playerId, collect()));
            }

            $current = $this->sliceData($all, $currentStart, null);
            $previous = $this->sliceData($all, $previousStart, $previousEnd);
            $metrics = $this->metrics($current);
            $previousMetrics = $this->metrics($previous);
            $series = $this->series($all, $now, $days);
            $born = $user->player?->born_date;
            $age = null;
            if ($born) {
                try {
                    $candidate = Carbon::parse((string) $born)->age;
                    $age = $candidate > 0 && $candidate < 80 ? $candidate : null;
                } catch (Throwable) {
                    $age = null;
                }
            }

            $positions = $user->positions->pluck('position')->filter()->values()->all();
            $primaryPosition = $positions[0] ?? null;
            $players[] = [
                'id' => (string) $user->id,
                'name' => trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: 'Player',
                'avatar' => $user->profile?->picture,
                'team' => $teamName,
                'position' => $primaryPosition,
                'level' => $user->profile?->level,
                'age' => $age,
                'throws' => $this->side($user->player?->throw_side),
                'bats' => $this->side($user->player?->hit_side),
                'height' => $this->height($user->player?->height_in_ft, $user->player?->height_in_inch),
                'metrics' => $metrics,
                'previous' => $previousMetrics,
                'series' => $series,
            ];
        }

        return $players;
    }

    private function sliceData(array $data, ?Carbon $start, ?Carbon $end): array
    {
        $out = [];
        foreach ($data as $key => $rows) {
            $out[$key] = $rows->filter(function ($row) use ($key, $start, $end): bool {
                $date = $this->rowDate($row, $key);
                if ( ! $date) {
                    return false;
                }
                if ($start && $date->lt($start)) {
                    return false;
                }
                return ! ($end && $date->gte($end));
            })->values();
        }
        return $out;
    }

    private function metrics(array $data): array
    {
        $latestFitness = $this->latest($data['fitness'], 'fitness');
        $latestAssessment = $this->latest($data['assessments'], 'assessments');
        $latestAthletic = $this->latest($data['athletic'], 'athletic');
        $batting = $data['batting'];
        $bullpen = $data['bullpen'];
        $cage = $data['cage'];
        $longToss = $data['long_toss'];

        $fps = $this->battingStatistics->fps($batting);
        $bps = $this->bullpenStatistics->bps($bullpen);
        $fcs = $this->cageStatistics->fcs($cage);

        $evValues = $batting->pluck('velocity')->merge($data['exit_velocity']->pluck('velocity'))
            ->filter(fn ($value) => is_numeric($value) && (float) $value > 0)
            ->map(fn ($value) => (float) $value);
        $fb = $bullpen->filter(fn ($row) => 'FB' === mb_strtoupper((string) ($row->type_throw ?? '')) && is_numeric($row->miles_per_hour) && (float) $row->miles_per_hour > 0);
        $validLongToss = $longToss->filter(fn ($row) => is_numeric($row->distance) && (float) $row->distance > 0);
        $zeroHopThrows = $validLongToss->filter(fn ($row) => is_numeric($row->hop) && 0 === (int) $row->hop);
        $contacts = $batting->reject(fn ($row) => 'TAKE' === mb_strtoupper((string) ($row->type_of_hit ?? '')));
        $count = $contacts->count();
        $hard = $contacts->filter(fn ($row) => in_array(mb_strtoupper((string) ($row->quality_of_contact ?? '')), ['H', 'HARD'], true))->count();
        $lineDrives = $contacts->filter(fn ($row) => in_array(mb_strtoupper((string) ($row->type_of_hit ?? '')), ['LD', 'LINE_DRIVE'], true))->count();
        $zeroHop = $zeroHopThrows->count();
        $longTossMax = $this->maximum($zeroHopThrows->pluck('distance'));
        $longTossAvg = $this->average($validLongToss->pluck('distance'));
        $zeroHopPct = $this->percentage($zeroHop, $validLongToss->count());
        $longTossConsistency = $this->consistencyScore($validLongToss->pluck('distance'));
        $longTossGrowth = $this->thirtyDayGrowth($validLongToss);
        $armEndurance = $this->armEnduranceScore($validLongToss);
        $longTossScore = $this->longTossPerformanceScore($longTossMax, $longTossAvg, $zeroHopPct, $longTossConsistency, $longTossGrowth);
        $velocityTransfer = $this->velocityTransferScore($longTossMax, $this->maximum($fb->pluck('miles_per_hour')));

        $throwing = is_array($latestAssessment?->throwing_workload_data) ? $latestAssessment->throwing_workload_data : [];
        $hittingScore = $this->positive($latestAssessment?->hitting_score) ?? $this->positive($fps['fps'] ?? null);
        $pitchingScore = $this->positive($latestAssessment?->pitching_score) ?? $this->positive($bps['bps'] ?? null);
        $strengthScore = $this->positive($latestAthletic?->strength_score)
            ?? $this->positive($latestFitness?->strength_score)
            ?? $this->positive($latestAssessment?->strength_overall_score);
        $mobilityScore = $this->positive($latestFitness?->mobility_score)
            ?? $this->positive($latestAssessment?->mobility_overall_score);

        return [
            'hitter_score' => $hittingScore,
            'pitcher_score' => $pitchingScore,
            'avg_ev' => $this->average($evValues),
            'max_ev' => $this->maximum($evValues),
            'hard_hit_pct' => $this->percentage($hard, $count),
            'barrel_pct' => null,
            'sweet_spot_pct' => $this->number($fcs['sweetSpotPct'] ?? null),
            'line_drive_pct' => $this->percentage($lineDrives, $count),
            'avg_fb' => $this->average($fb->pluck('miles_per_hour')),
            'max_fb' => $this->maximum($fb->pluck('miles_per_hour')),
            'strike_pct' => $this->number($bps['strikeRate'] ?? null),
            'swing_miss_pct' => null,
            'whip' => null,
            'command_score' => $this->number($bps['fpScore'] ?? null),
            'bullpen_score' => $this->number($bps['bps'] ?? null),
            'pitch_mix_score' => $this->number($bps['mixScore'] ?? null),
            'first_pitch_score' => $this->number($bps['fpScore'] ?? null),
            'cage_score' => $this->number($fcs['fcs'] ?? null),
            'cage_avg_ev' => $this->number($fcs['avgEV'] ?? null),
            'cage_max_ev' => $this->number($fcs['maxEV'] ?? null),
            'cage_avg_distance' => $this->number($fcs['avgDist'] ?? null),
            'cage_line_drive_pct' => $this->number($fcs['ldPct'] ?? null),
            'long_toss_score' => $longTossScore,
            'long_toss_max' => $longTossMax,
            'long_toss_avg' => $longTossAvg,
            'zero_hop_pct' => $zeroHopPct,
            'long_toss_consistency' => $longTossConsistency,
            'carry_efficiency' => null === $longTossMax || null === $longTossAvg ? null : round(min(100.0, ($longTossAvg / max(1.0, $longTossMax)) * 100.0), 1),
            'average_hops' => $this->averageIncludingZero($validLongToss->pluck('hop')),
            'long_toss_growth' => $longTossGrowth,
            'arm_endurance' => $armEndurance,
            'velocity_transfer' => $velocityTransfer,
            'strength_score' => $strengthScore,
            'bench_press' => $this->positive($latestFitness?->bench_press) ?? $this->positive($latestAssessment?->bench_lbs),
            'squat' => $this->positive($latestFitness?->back_squat) ?? $this->positive($latestFitness?->front_squat) ?? $this->positive($latestAssessment?->squat_lbs),
            'dead_lift' => $this->positive($latestFitness?->dead_lift) ?? $this->positive($latestAssessment?->deadlift_lbs),
            'power_clean' => $this->positive($latestFitness?->power_clean),
            'hand_strength' => $this->positive($latestFitness?->hand_strength),
            'rotational_power' => $this->positive($latestFitness?->med_ball_rotational_throw),
            'mobility_score' => $mobilityScore,
            'hip_mobility' => $this->positive($latestAssessment?->hip_mobility),
            'shoulder_mobility' => $this->positive($latestAssessment?->shoulder_mobility),
            't_spine_mobility' => $this->positive($latestAssessment?->rotational_mobility),
            'hamstring_mobility' => null,
            'ankle_mobility' => $this->positive($latestAssessment?->ankle_mobility),
            'recovery_score' => $this->positive($latestFitness?->recovery_score),
            'sleep_hours' => $this->positive($latestFitness?->sleep_hours),
            'readiness_score' => null,
            'arm_health_score' => $this->positive($latestAssessment?->arm_health_score),
            'soreness_score' => $this->positive($throwing['arm_soreness'] ?? null),
            'workload_score' => $this->positive($latestAssessment?->throwing_workload_score),
            'body_weight' => $this->positive($latestFitness?->body_weight) ?? $this->positive($latestAssessment?->body_weight_lbs),
        ];
    }

    private function series(array $all, Carbon $now, ?int $days): array
    {
        $windowDays = $days ?? 180;
        $bucketDays = max(1, (int) ceil($windowDays / 6));
        $start = $now->copy()->subDays($bucketDays * 6);
        $series = [];

        for ($index = 0; $index < 6; $index++) {
            $from = $start->copy()->addDays($bucketDays * $index);
            $to = 5 === $index ? null : $from->copy()->addDays($bucketDays);
            $metrics = $this->metrics($this->sliceData($all, $from, $to));
            foreach (self::CATEGORIES as $category) {
                $value = $metrics[$category['metric']] ?? null;
                if (null !== $value) {
                    $series[$category['key']][] = $value;
                }
            }
        }

        return $series;
    }

    private function buildCategory(array $category, array $players): array
    {
        $metric = $category['metric'];
        $ranked = array_values(array_filter($players, fn (array $player) => null !== $player['metrics'][$metric]));
        usort($ranked, fn (array $a, array $b) => $b['metrics'][$metric] <=> $a['metrics'][$metric]);

        $previous = array_values(array_filter($players, fn (array $player) => null !== $player['previous'][$metric]));
        usort($previous, fn (array $a, array $b) => $b['previous'][$metric] <=> $a['previous'][$metric]);
        $previousRanks = [];
        foreach ($previous as $index => $player) {
            $previousRanks[$player['id']] = $index + 1;
        }

        $top = array_slice($ranked, 0, 25);
        $base = $this->emptyCategory($category);
        $base['rows'] = array_map(function (array $player, int $index) use ($category, $metric, $previousRanks): array {
            $currentRank = $index + 1;
            $previousRank = $previousRanks[$player['id']] ?? null;
            return [
                'player_id' => $player['id'],
                'name' => $player['name'],
                'avatar' => $player['avatar'],
                'subtitle' => $this->subtitle($player),
                'value' => $player['metrics'][$metric],
                'trend' => null === $previousRank ? null : $previousRank - $currentRank,
                'spark' => $player['series'][$category['key']] ?? [],
                'evidence' => 'long_toss' === $category['key'] && null !== $player['metrics']['long_toss_max']
                    ? ((int) round($player['metrics']['long_toss_max'])).' ft max carry'
                    : null,
            ];
        }, $top, array_keys($top));

        if ([] !== $top) {
            $leader = $top[0];
            $current = $leader['metrics'][$metric];
            $prior = $leader['previous'][$metric];
            $base['featured'] = [
                'player_id' => $leader['id'],
                'name' => $leader['name'],
                'avatar' => $leader['avatar'],
                'subtitle' => $this->subtitle($leader),
                'bigValue' => $current,
                'trend' => null === $prior ? null : round($current - $prior, 1),
                'spark' => $leader['series'][$category['key']] ?? [],
                'bio' => [
                    ['k' => 'Team', 'v' => $leader['team']],
                    ['k' => 'Position', 'v' => $leader['position']],
                    ['k' => 'Age', 'v' => $leader['age']],
                    ['k' => 'Throws', 'v' => $leader['throws']],
                    ['k' => 'Bats', 'v' => $leader['bats']],
                    ['k' => 'Height', 'v' => $leader['height']],
                    ['k' => 'Weight', 'v' => $leader['metrics']['body_weight'] ? ((int) round($leader['metrics']['body_weight'])).' lb' : null],
                ],
                'subMetrics' => array_map(fn (array $sub) => [
                    'label' => $sub[0],
                    'value' => $leader['metrics'][$sub[1]] ?? null,
                    'unit' => $sub[2],
                ], $category['sub']),
                'insight' => 'long_toss' === $category['key'] ? $this->longTossInsight($leader['metrics']) : null,
            ];
        }

        return $base;
    }

    private function emptyCategory(array $category): array
    {
        return [
            'key' => $category['key'],
            'label' => $category['label'],
            'subtitle' => 'Team leaderboard • authoritative FMTRX data',
            'icon' => $category['icon'],
            'color' => $category['color'],
            'unit' => $category['unit'],
            'bigLabel' => $category['bigLabel'],
            'rows' => [],
            'featured' => null,
        ];
    }

    private function latest(Collection $rows, string $key): mixed
    {
        return $rows->sortByDesc(fn ($row) => $this->rowDate($row, $key)?->getTimestamp() ?? 0)->first();
    }

    private function rowDate(mixed $row, string $key): ?Carbon
    {
        $value = match ($key) {
            'fitness' => $row->fitness_date ?? $row->created_at ?? null,
            'assessments' => $row->assessment_date ?? $row->created_at ?? null,
            'athletic' => $row->calculated_at ?? $row->created_at ?? null,
            default => $row->created_at ?? null,
        };
        if ( ! $value) {
            return null;
        }
        try {
            return $value instanceof Carbon ? $value->copy() : Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function subtitle(array $player): string
    {
        return implode(' • ', array_values(array_filter([
            $player['position'],
            $player['age'] ? $player['age'].'U' : null,
            $player['level'],
        ])));
    }

    private function side(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return '' === $value ? null : mb_strtoupper(mb_substr($value, 0, 1));
    }

    private function height(mixed $feet, mixed $inches): ?string
    {
        if ( ! is_numeric($feet) || (int) $feet <= 0) {
            return null;
        }
        return (int) $feet."'".(is_numeric($inches) ? (int) $inches : 0).'"';
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 1) : null;
    }

    private function average(iterable $values): ?float
    {
        $values = collect($values)->filter(fn ($value) => is_numeric($value) && (float) $value > 0);
        return $values->isEmpty() ? null : round((float) $values->average(), 1);
    }

    private function maximum(iterable $values): ?float
    {
        $values = collect($values)->filter(fn ($value) => is_numeric($value) && (float) $value > 0);
        return $values->isEmpty() ? null : round((float) $values->max(), 1);
    }

    private function averageIncludingZero(iterable $values): ?float
    {
        $values = collect($values)->filter(fn ($value) => is_numeric($value) && (float) $value >= 0);

        return $values->isEmpty() ? null : round((float) $values->average(), 1);
    }

    private function percentage(int $part, int $whole): ?float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : null;
    }

    private function longTossPerformanceScore(?float $maxCarry, ?float $averageCarry, ?float $zeroHopPct, ?float $consistency, ?float $growthFeet): ?float
    {
        if (null === $averageCarry || null === $consistency || null === $zeroHopPct) {
            return null;
        }

        // Distance components are normalized to demanding, attainable baseball
        // benchmarks. Growth is neutral at zero and rewards up to +20 ft/month.
        $maxScore = min(100.0, (($maxCarry ?? 0.0) / 350.0) * 100.0);
        $averageScore = min(100.0, ($averageCarry / 320.0) * 100.0);
        $growthScore = max(0.0, min(100.0, 50.0 + (($growthFeet ?? 0.0) * 2.5)));

        return round(($maxScore * .40) + ($averageScore * .25) + ($zeroHopPct * .15) + ($consistency * .10) + ($growthScore * .10), 1);
    }

    private function consistencyScore(iterable $values): ?float
    {
        $values = collect($values)->filter(fn ($value) => is_numeric($value) && (float) $value > 0)->map(fn ($value) => (float) $value)->values();
        if ($values->isEmpty()) {
            return null;
        }
        if (1 === $values->count()) {
            return 100.0;
        }

        $mean = (float) $values->average();
        $variance = (float) $values->map(fn (float $value) => ($value - $mean) ** 2)->average();
        $coefficientOfVariation = sqrt($variance) / max(1.0, $mean);

        return round(max(0.0, min(100.0, (1.0 - $coefficientOfVariation) * 100.0)), 1);
    }

    private function thirtyDayGrowth(Collection $throws): ?float
    {
        $now = Carbon::now();
        $currentStart = $now->copy()->subDays(30);
        $previousStart = $now->copy()->subDays(60);
        $current = $throws->filter(fn ($row) => ($date = $this->rowDate($row, 'long_toss')) && $date->gte($currentStart));
        $previous = $throws->filter(fn ($row) => ($date = $this->rowDate($row, 'long_toss')) && $date->gte($previousStart) && $date->lt($currentStart));
        $currentAverage = $this->average($current->pluck('distance'));
        $previousAverage = $this->average($previous->pluck('distance'));

        return null === $currentAverage || null === $previousAverage ? null : round($currentAverage - $previousAverage, 1);
    }

    private function armEnduranceScore(Collection $throws): ?float
    {
        if ($throws->count() < 4) {
            return null;
        }

        $ordered = $throws->sortBy(fn ($row) => sprintf('%s-%06d-%06d', (string) ($row->practice_id ?? ''), (int) ($row->set ?? 0), (int) ($row->sort ?? 0)))->values();
        $segmentSize = max(1, (int) floor($ordered->count() / 3));
        $opening = $this->average($ordered->take($segmentSize)->pluck('distance'));
        $closing = $this->average($ordered->take(-$segmentSize)->pluck('distance'));
        if (null === $opening || null === $closing) {
            return null;
        }

        return round(max(0.0, min(100.0, ($closing / max(1.0, $opening)) * 100.0)), 1);
    }

    private function velocityTransferScore(?float $maxCarry, ?float $maxFastball): ?float
    {
        if (null === $maxCarry || null === $maxFastball) {
            return null;
        }

        // A 300 ft zero-hop carry corresponds to roughly a 90 mph transfer
        // target; scale the target with carry and cap the displayed efficiency.
        $targetVelocity = max(60.0, $maxCarry * .30);

        return round(max(0.0, min(100.0, ($maxFastball / $targetVelocity) * 100.0)), 1);
    }

    private function longTossInsight(array $metrics): array
    {
        $lines = [];
        $lines[] = ($metrics['long_toss_max'] ?? 0) >= 300 ? 'Elite carry.' : 'Carry distance is still developing.';
        $lines[] = ($metrics['long_toss_consistency'] ?? 0) >= 90 ? 'Excellent consistency.' : 'More repeatable carry will raise the score.';
        if (null !== ($metrics['velocity_transfer'] ?? null)) {
            $lines[] = $metrics['velocity_transfer'] >= 85 ? 'Velocity transfer is above average.' : 'Needs better mound transfer.';
        }

        $growth = $metrics['long_toss_growth'] ?? null;
        $projectedMph = null === $growth ? null : round(max(-3.0, min(3.0, $growth / 15.0)), 1);

        return ['lines' => $lines, 'projected_mph_gain' => $projectedMph];
    }
}
