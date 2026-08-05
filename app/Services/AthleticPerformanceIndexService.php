<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AthleticPerformanceScore;
use App\Models\Player;
use App\Models\PlayerFitness;
use App\Models\PlayerPosition;
use App\Models\PlayerTeam;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use App\Services\Intelligence\BenchmarkDefinitions;
use App\Services\Intelligence\StrengthBenchmarkService;
use Throwable;

class AthleticPerformanceIndexService
{
    /**
     * Metric fields that should be coalesced from history (latest non-zero wins).
     */
    private const COALESCE_FIELDS = [
        'body_weight', 'front_squat', 'back_squat', 'bench_press', 'dead_lift', 'trap_bar_deadlift',
        'power_clean', 'hand_strength', 'grip_strength_left', 'grip_strength_right', 'push_ups', 'pull_ups', 'plank_hold', 'vertical_jump',
        'broad_jump', 'med_ball_rotational_throw', 'sprint_10yd', 'yd_40_dash',
        'yd_60_dash', 'exit_velo', 'bat_speed', 'throwing_velo', 'pitch_velo',
        'sleep_hours', 'sleep_quality_1_to_5', 'recovery_score', 'mobility_score',
    ];

    /**
     * Each save writes only the entered fields (others null/0), so a single row
     * is an incomplete picture. Build a virtual assessment that carries the
     * latest known (positive) value per metric across the player's history, so
     * the score reflects everything on record — not just the last entry.
     */
    private function coalesceFitness(PlayerFitness $assessment): PlayerFitness
    {
        $history = PlayerFitness::query()
            ->where('user_id', (string) $assessment->user_id)
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $merged = $assessment->replicate();
        $merged->id = $assessment->id; // keep linkage for assessment_id

        foreach (self::COALESCE_FIELDS as $field) {
            if ($this->toFloat($merged->{$field}) > 0) {
                continue;
            }
            foreach ($history as $row) {
                if ($this->toFloat($row->{$field}) > 0) {
                    $merged->{$field} = $row->{$field};
                    break;
                }
            }
        }

        return $merged;
    }

    public function calculate(PlayerFitness $assessment): array
    {
        $strengthAssessment = $this->assessmentForStrengthBenchmark($assessment);
        // Score from the latest KNOWN value per metric, not just this row.
        $assessment = $this->coalesceFitness($assessment);

        $role = $this->resolveRole((string) $assessment->user_id);
        $ageYears = $this->resolveAgeYears((string) $assessment->user_id, $assessment->fitness_date?->toDateString());
        $ageGroup = $this->resolveAgeGroup($ageYears);

        $benchmarks = $this->resolveBenchmarks($ageGroup, $role);

        $weight = $this->toFloat($assessment->body_weight);
        $frontSquat = $this->toFloat($assessment->front_squat);
        $backSquat = $this->toFloat($assessment->back_squat);
        $bench = $this->toFloat($assessment->bench_press);
        $deadlift = $this->toFloat($assessment->dead_lift);
        $pushUps = $this->toFloat($assessment->push_ups);
        $pullUps = $this->toFloat($assessment->pull_ups);
        $powerClean = $this->toFloat($assessment->power_clean);
        $handStrength = $this->toFloat($assessment->hand_strength);
        $verticalJump = $this->toFloat($assessment->vertical_jump);
        $broadJump = $this->toFloat($assessment->broad_jump);
        $medBall = $this->toFloat($assessment->med_ball_rotational_throw);
        $tenYard = $this->toFloat($assessment->sprint_10yd);
        $fortyYard = $this->toFloat($assessment->yd_40_dash);
        $sixtyYard = $this->toFloat($assessment->yd_60_dash);
        $sleepHours = $this->toFloat($assessment->sleep_hours);
        $sleepQuality = $this->toFloat($assessment->sleep_quality_1_to_5);
        $recoveryScore = $this->toFloat($assessment->recovery_score);
        $mobilityScore = $this->toFloat($assessment->mobility_score);
        $exitVelo = $this->toFloat($assessment->exit_velo);
        $batSpeed = $this->toFloat($assessment->bat_speed);
        $throwingVelo = $this->toFloat($assessment->throwing_velo);
        $pitchVelo = $this->toFloat($assessment->pitch_velo);

        $backSquatRatio = $this->ratio($backSquat, $weight);
        $frontSquatRatio = $this->ratio($frontSquat, $weight);
        $deadliftRatio = $this->ratio($deadlift, $weight);
        $benchRatio = $this->ratio($bench, $weight);
        $powerCleanRatio = $this->ratio($powerClean, $weight);

        $backSquatScore = $this->scoreMetric('back_squat_ratio', $backSquatRatio, $benchmarks);
        $frontSquatScore = $this->scoreMetric('front_squat_ratio', $frontSquatRatio, $benchmarks);
        $deadliftScore = $this->scoreMetric('deadlift_ratio', $deadliftRatio, $benchmarks);
        $benchScore = $this->scoreMetric('bench_ratio', $benchRatio, $benchmarks);
        $pullUpScore = $this->scoreMetric('pull_ups_reps', $pullUps, $benchmarks);
        $pushUpScore = $this->scoreMetric('push_ups_reps', $pushUps, $benchmarks);

        $powerCleanScore = $this->scoreMetric('power_clean_ratio', $powerCleanRatio, $benchmarks);
        $handStrengthScore = $this->scoreMetric('hand_strength_lbs', $handStrength, $benchmarks);
        $verticalJumpScore = $this->scoreMetric('vertical_jump_inches', $verticalJump, $benchmarks);
        $broadJumpScore = $this->scoreMetric('broad_jump_inches', $broadJump, $benchmarks);
        $medBallScore = $this->scoreMetric('med_ball_rotational_throw_ft', $medBall, $benchmarks);

        $tenYardScore = $this->scoreMetric('ten_yard_sec', $tenYard, $benchmarks, true);
        $fortyYardScore = $this->scoreMetric('forty_yard_sec', $fortyYard, $benchmarks, true);
        $sixtyYardScore = $this->scoreMetric('sixty_yard_sec', $sixtyYard, $benchmarks, true);

        $exitVeloScore = $this->scoreMetric('exit_velo_mph', $exitVelo, $benchmarks);
        $batSpeedScore = $this->scoreMetric('bat_speed_mph', $batSpeed, $benchmarks);
        $throwingVeloScore = $this->scoreMetric('throwing_velo_mph', $throwingVelo, $benchmarks);
        $pitchVeloScore = $this->scoreMetric('pitch_velo_mph', $pitchVelo, $benchmarks);

        $sleepHoursScore = $this->scoreMetric('sleep_hours', $sleepHours, $benchmarks);
        $sleepQualityScore = $this->scoreMetric('sleep_quality', $sleepQuality, $benchmarks);

        $lowerBodyStrength = $this->weightedScore([
            ['value' => $backSquatScore, 'weight' => 0.40],
            ['value' => $frontSquatScore, 'weight' => 0.25],
            ['value' => $deadliftScore, 'weight' => 0.35],
        ]);

        $upperBodyStrength = $this->weightedScore([
            ['value' => $benchScore, 'weight' => 0.50],
            ['value' => $pullUpScore, 'weight' => 0.30],
            ['value' => $pushUpScore, 'weight' => 0.20],
        ]);

        $relativeStrength = $this->weightedScore([
            ['value' => $pullUpScore, 'weight' => 0.60],
            ['value' => $pushUpScore, 'weight' => 0.40],
        ]);

        $legacyStrengthScore = $this->weightedScore([
            ['value' => $lowerBodyStrength, 'weight' => 0.40],
            ['value' => $upperBodyStrength, 'weight' => 0.40],
            ['value' => $relativeStrength, 'weight' => 0.20],
        ]);

        $strengthBenchmark = app(StrengthBenchmarkService::class)->benchmark($strengthAssessment, null, [
            'age' => $ageYears,
            'age_group' => BenchmarkDefinitions::ageGroup($ageYears),
            'dob' => $this->resolveDob((string) $assessment->user_id),
            'role' => $role,
            'position' => $role,
            'level' => null !== $ageYears && $ageYears <= 18 ? 'high_school' : 'college',
            'player_id' => (string) $assessment->user_id,
        ]);
        $strengthScore = is_numeric($strengthBenchmark['score'] ?? null)
            ? (float) $strengthBenchmark['score']
            : null;

        $powerScore = $this->weightedScore([
            ['value' => $powerCleanScore, 'weight' => 0.15],
            ['value' => $handStrengthScore, 'weight' => 0.10],
            ['value' => $verticalJumpScore, 'weight' => 0.25],
            ['value' => $broadJumpScore, 'weight' => 0.20],
            ['value' => $medBallScore, 'weight' => 0.30],
        ]);

        $speedScore = $this->weightedScore([
            ['value' => $tenYardScore, 'weight' => 0.50],
            ['value' => $fortyYardScore, 'weight' => 0.20],
            ['value' => $sixtyYardScore, 'weight' => 0.30],
        ]);

        $hitterBaseballScore = $this->weightedScore([
            ['value' => $exitVeloScore, 'weight' => 0.40],
            ['value' => $batSpeedScore, 'weight' => 0.35],
            ['value' => $throwingVeloScore, 'weight' => 0.15],
            ['value' => $mobilityScore, 'weight' => 0.10],
        ]);

        $pitcherBaseballScore = $this->weightedScore([
            ['value' => $pitchVeloScore, 'weight' => 0.50],
            ['value' => $throwingVeloScore, 'weight' => 0.25],
            ['value' => $mobilityScore, 'weight' => 0.15],
            ['value' => $recoveryScore, 'weight' => 0.10],
        ]);

        $baseballScore = match ($role) {
            'pitcher' => $pitcherBaseballScore,
            'two_way' => $this->weightedScore([
                ['value' => $hitterBaseballScore, 'weight' => 0.50],
                ['value' => $pitcherBaseballScore, 'weight' => 0.50],
            ]),
            default => $hitterBaseballScore,
        };

        $recoveryMobilityScore = $this->weightedScore([
            ['value' => $recoveryScore, 'weight' => 0.40],
            ['value' => $mobilityScore, 'weight' => 0.40],
            ['value' => $sleepHoursScore, 'weight' => 0.10],
            ['value' => $sleepQualityScore, 'weight' => 0.10],
        ]);

        $overallApiScore = $this->weightedScore([
            ['value' => $strengthScore, 'weight' => 0.25],
            ['value' => $powerScore, 'weight' => 0.25],
            ['value' => $speedScore, 'weight' => 0.15],
            ['value' => $baseballScore, 'weight' => 0.25],
            ['value' => $recoveryMobilityScore, 'weight' => 0.10],
        ]);

        $categoryScores = [
            'strength_score' => $strengthScore,
            'power_score' => $powerScore,
            'speed_score' => $speedScore,
            'baseball_score' => $baseballScore,
            'recovery_mobility_score' => $recoveryMobilityScore,
            'lower_body_strength_score' => $lowerBodyStrength,
            'upper_body_strength_score' => $upperBodyStrength,
            'relative_strength_score' => $relativeStrength,
        ];

        $strengths = $this->getStrengths($categoryScores);
        $weaknesses = $this->getWeaknesses($categoryScores);

        $teamId = PlayerTeam::query()
            ->where('user_id', (string) $assessment->user_id)
            ->whereNotNull('team_id')
            ->value('team_id');

        $teamScores = AthleticPerformanceScore::query()
            ->where('team_id', $teamId)
            ->whereNotNull('overall_api_score')
            ->pluck('overall_api_score')
            ->map(fn ($v) => (float) $v)
            ->all();

        $teamPercentile = $this->getPercentile($overallApiScore, $teamScores, true);
        $teamRankData = $this->getTeamRank($overallApiScore, $teamScores);

        $developmentPlan = $this->buildDevelopmentPlan($weaknesses);

        return [
            'player_id' => (string) $assessment->user_id,
            'team_id' => $teamId ? (string) $teamId : null,
            'assessment_id' => (string) $assessment->id,
            'role' => $role,
            'overall_api_score' => $overallApiScore,
            'strength_score' => $strengthScore,
            'power_score' => $powerScore,
            'speed_score' => $speedScore,
            'baseball_score' => $baseballScore,
            'recovery_mobility_score' => $recoveryMobilityScore,
            'lower_body_strength_score' => $lowerBodyStrength,
            'upper_body_strength_score' => $upperBodyStrength,
            'relative_strength_score' => $relativeStrength,
            'projection_label' => $this->getProjectionLabel($overallApiScore),
            'grade_label' => $this->getGradeLabel($overallApiScore),
            'team_percentile' => $teamPercentile,
            'team_rank' => $teamRankData['rank'],
            'team_count' => $teamRankData['count'],
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'development_plan' => $developmentPlan,
            'calculated_at' => now(),
            'benchmark_context' => [
                'age_group' => $ageGroup,
                'age_years' => $ageYears,
            ],
            'category_scores' => $categoryScores,
            'strength_benchmark_v1' => $strengthBenchmark,
            'legacy_strength_score' => $legacyStrengthScore,
        ];
    }

    public function calculateAndSave(PlayerFitness $assessment): AthleticPerformanceScore
    {
        $payload = $this->calculate($assessment);

        /** @var AthleticPerformanceScore $score */
        $score = AthleticPerformanceScore::query()->updateOrCreate(
            ['assessment_id' => (string) $assessment->id],
            Arr::only($payload, [
                'player_id',
                'team_id',
                'assessment_id',
                'role',
                'overall_api_score',
                'strength_score',
                'power_score',
                'speed_score',
                'baseball_score',
                'recovery_mobility_score',
                'lower_body_strength_score',
                'upper_body_strength_score',
                'relative_strength_score',
                'projection_label',
                'grade_label',
                'team_percentile',
                'team_rank',
                'team_count',
                'strengths',
                'weaknesses',
                'development_plan',
                'calculated_at',
            ])
        );

        // Mirror the canonical scores back onto the fitness row so every client
        // (app + web) reads ONE server-computed value instead of running its own
        // divergent formula. saveQuietly avoids re-triggering observers.
        $strength = $payload['strength_score'] ?? null;
        $overall = $payload['overall_api_score'] ?? null;
        $assessment->forceFill([
            'strength_score' => null !== $strength ? (int) round((float) $strength) : null,
            'overall_api_score' => null !== $overall ? round((float) $overall, 2) : null,
        ])->saveQuietly();

        return $score;
    }

    public function getTrend(?float $latest, ?float $previous): string
    {
        if (null === $latest || null === $previous) {
            return 'no_change';
        }

        if ($latest > $previous) {
            return 'improved';
        }

        if ($latest < $previous) {
            return 'declined';
        }

        return 'no_change';
    }

    private function resolveDob(string $userId): ?string
    {
        $value = Player::query()->where('user_id', $userId)->value('born_date');

        return $value ? (string) $value : null;
    }

    private function assessmentForStrengthBenchmark(PlayerFitness $assessment): PlayerFitness
    {
        $hasBodyWeight = $this->toFloat($assessment->body_weight) > 0;
        $maximumCount = collect(['front_squat', 'back_squat', 'bench_press', 'dead_lift', 'trap_bar_deadlift'])
            ->filter(fn (string $field): bool => $this->toFloat($assessment->{$field}) > 0)
            ->count();
        $hasSupport = collect(['power_clean', 'vertical_jump', 'broad_jump', 'med_ball_rotational_throw', 'pull_ups', 'push_ups', 'plank_hold'])
            ->contains(fn (string $field): bool => $this->toFloat($assessment->{$field}) > 0);

        if ($hasBodyWeight && $maximumCount >= 2 && $hasSupport) {
            return $assessment;
        }

        return PlayerFitness::query()
            ->where('user_id', (string) $assessment->user_id)
            ->where('body_weight', '>', 0)
            ->where(function ($query): void {
                foreach (['front_squat', 'back_squat', 'bench_press', 'dead_lift', 'trap_bar_deadlift'] as $field) {
                    $query->orWhere($field, '>', 0);
                }
            })
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->first() ?? $assessment;
    }

    /**
     * @param array<int|string, float|int> $benchmarks
     */
    public function scoreHigherIsBetter(?float $value, array $benchmarks): ?float
    {
        if (null === $value || $value <= 0 || empty($benchmarks)) {
            return null;
        }

        $pairs = [];
        foreach ($benchmarks as $percentile => $rawValue) {
            if ( ! is_numeric($percentile) || ! is_numeric($rawValue)) {
                continue;
            }
            $pairs[] = ['value' => (float) $rawValue, 'percentile' => (float) $percentile];
        }

        if (count($pairs) < 2) {
            return null;
        }

        usort($pairs, fn (array $a, array $b) => $a['value'] <=> $b['value']);

        if ($value <= $pairs[0]['value']) {
            return $this->clamp($pairs[0]['percentile']);
        }

        $last = $pairs[count($pairs) - 1];
        if ($value >= $last['value']) {
            return $this->clamp($last['percentile']);
        }

        for ($i = 1; $i < count($pairs); $i++) {
            $x0 = $pairs[$i - 1]['value'];
            $x1 = $pairs[$i]['value'];
            $y0 = $pairs[$i - 1]['percentile'];
            $y1 = $pairs[$i]['percentile'];

            if ($value <= $x1) {
                return $this->clamp($this->lerp($value, $x0, $x1, $y0, $y1));
            }
        }

        return null;
    }

    /**
     * @param array<int|string, float|int> $benchmarks
     */
    public function scoreLowerIsBetter(?float $value, array $benchmarks): ?float
    {
        if (null === $value || $value <= 0 || empty($benchmarks)) {
            return null;
        }

        $inverted = [];
        foreach ($benchmarks as $percentile => $rawValue) {
            if ( ! is_numeric($percentile) || ! is_numeric($rawValue)) {
                continue;
            }
            $inverted[(string) $percentile] = -1 * (float) $rawValue;
        }

        return $this->scoreHigherIsBetter(-1 * $value, $inverted);
    }

    public function getGradeLabel(?float $score): string
    {
        if (null === $score) {
            return 'Foundation Needed';
        }

        if ($score >= 90) {
            return 'Elite';
        }

        if ($score >= 80) {
            return 'Advanced';
        }

        if ($score >= 70) {
            return 'Strong';
        }

        if ($score >= 60) {
            return 'Developing';
        }

        if ($score >= 50) {
            return 'Needs Work';
        }

        return 'Foundation Needed';
    }

    public function getProjectionLabel(?float $score): string
    {
        if (null === $score) {
            return 'Foundation Needed';
        }

        if ($score >= 90) {
            return 'Elite Prospect';
        }

        if ($score >= 80) {
            return 'College Prospect';
        }

        if ($score >= 70) {
            return 'Varsity Impact Player';
        }

        if ($score >= 60) {
            return 'Developing Varsity Player';
        }

        if ($score >= 50) {
            return 'JV / Developmental Player';
        }

        return 'Foundation Needed';
    }

    /**
     * @param array<int, float|int|null> $peerValues
     */
    public function getPercentile(?float $playerValue, array $peerValues, bool $higherIsBetter): ?int
    {
        if (null === $playerValue) {
            return null;
        }

        $clean = array_values(array_filter(
            array_map(fn ($v) => is_numeric($v) ? (float) $v : null, $peerValues),
            fn ($v) => null !== $v
        ));

        if (0 === count($clean)) {
            return null;
        }

        $n = count($clean);
        $less = 0;
        $equal = 0;

        foreach ($clean as $value) {
            if ($value < $playerValue) {
                $less++;
            } elseif ($value === $playerValue) {
                $equal++;
            }
        }

        $pct = (($less + (0.5 * $equal)) / $n) * 100.0;

        if ( ! $higherIsBetter) {
            $pct = 100.0 - $pct;
        }

        return (int) round($this->clamp($pct));
    }

    /**
     * @param array<int, float|int|null> $teamScores
     * @return array{rank: int|null, count: int}
     */
    public function getTeamRank(?float $playerScore, array $teamScores): array
    {
        $clean = array_values(array_filter(
            array_map(fn ($v) => is_numeric($v) ? (float) $v : null, $teamScores),
            fn ($v) => null !== $v
        ));

        if (null !== $playerScore) {
            $clean[] = $playerScore;
        }

        if (0 === count($clean)) {
            return ['rank' => null, 'count' => 0];
        }

        rsort($clean);
        $rank = null;

        foreach ($clean as $idx => $score) {
            if (null !== $playerScore && $score === $playerScore) {
                $rank = $idx + 1;
                break;
            }
        }

        return [
            'rank' => $rank,
            'count' => count($clean),
        ];
    }

    /**
     * @param array<string, float|null> $scores
     * @return array<int, array{metric: string, score: float}>
     */
    public function getStrengths(array $scores): array
    {
        $rows = [];
        foreach ($scores as $metric => $score) {
            if (null === $score) {
                continue;
            }
            $rows[] = ['metric' => $metric, 'score' => round($score, 1)];
        }

        usort($rows, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        return array_slice($rows, 0, 3);
    }

    /**
     * @param array<string, float|null> $scores
     * @return array<int, array{metric: string, score: float}>
     */
    public function getWeaknesses(array $scores): array
    {
        $rows = [];
        foreach ($scores as $metric => $score) {
            if (null === $score) {
                continue;
            }
            $rows[] = ['metric' => $metric, 'score' => round($score, 1)];
        }

        usort($rows, fn (array $a, array $b) => $a['score'] <=> $b['score']);
        return array_slice($rows, 0, 3);
    }

    /**
     * @param array<int, array{metric: string, score: float}> $weaknesses
     * @return array<int, array{focus: string, action: string}>
     */
    private function buildDevelopmentPlan(array $weaknesses): array
    {
        $actions = [
            'strength_score' => 'Prioritize 2 lower-body and 1 upper-body strength sessions weekly with progressive loading.',
            'power_score' => 'Add 2 explosive sessions weekly: jumps, med-ball rotational work, and olympic lift derivatives.',
            'speed_score' => 'Run acceleration + sprint mechanics 2x/week with timed 10y/40y efforts and full recovery.',
            'baseball_score' => 'Target baseball output: bat speed / exit velo work or velocity + intent throwing blocks 3x/week.',
            'recovery_mobility_score' => 'Lock in sleep/recovery routine: 8h target sleep, daily mobility, hydration, and readiness checks.',
            'lower_body_strength_score' => 'Emphasize squats/deadlifts and unilateral force production with weekly progression.',
            'upper_body_strength_score' => 'Build upper-body output via pressing, pulling, and shoulder integrity circuits.',
            'relative_strength_score' => 'Improve bodyweight strength with pull-up and push-up progression ladders.',
        ];

        $plan = [];
        foreach ($weaknesses as $row) {
            $metric = $row['metric'];
            $plan[] = [
                'focus' => $metric,
                'action' => $actions[$metric] ?? 'Address this metric with targeted progressive training and weekly retesting.',
            ];
        }

        return $plan;
    }

    /**
     * @param array<int, array{value: float|null, weight: float}> $items
     */
    private function weightedScore(array $items): ?float
    {
        $valid = array_values(array_filter($items, fn (array $row) => null !== $row['value']));

        if (0 === count($valid)) {
            return null;
        }

        $weightSum = array_sum(array_map(fn (array $row) => $row['weight'], $valid));
        if ($weightSum <= 0) {
            return null;
        }

        $score = 0.0;
        foreach ($valid as $row) {
            $score += ((float) $row['value']) * ((float) $row['weight'] / $weightSum);
        }

        return round($this->clamp($score), 1);
    }

    /**
     * @param array<string, mixed> $benchmarks
     */
    private function scoreMetric(string $metricName, ?float $value, array $benchmarks, bool $forceLowerIsBetter = false): ?float
    {
        if (null === $value) {
            return null;
        }

        /** @var array<int|string, float|int> $metricBenchmarks */
        $metricBenchmarks = $benchmarks[$metricName] ?? [];

        if (empty($metricBenchmarks)) {
            return null;
        }

        $lowerMetrics = config('fmtrx_benchmarks.lower_is_better_metrics', []);
        $isLower = $forceLowerIsBetter || in_array($metricName, $lowerMetrics, true);

        return $isLower
            ? $this->scoreLowerIsBetter($value, $metricBenchmarks)
            : $this->scoreHigherIsBetter($value, $metricBenchmarks);
    }

    /**
     * @return array<string, array<int|string, float|int>>
     */
    private function resolveBenchmarks(string $ageGroup, string $role): array
    {
        $ageRole = config("fmtrx_benchmarks.age_groups.{$ageGroup}.{$role}.metrics", []);
        $ageHitter = config("fmtrx_benchmarks.age_groups.{$ageGroup}.hitter.metrics", []);
        $fallbackRole = config("fmtrx_benchmarks.fallback.{$role}.metrics", []);

        /** @var array<string, array<int|string, float|int>> $merged */
        $merged = array_merge($ageHitter, $fallbackRole, $ageRole);

        return $merged;
    }

    private function resolveRole(string $playerId): string
    {
        $positions = PlayerPosition::query()
            ->where('player_id', $playerId)
            ->pluck('position')
            ->map(fn ($pos) => mb_strtoupper((string) $pos))
            ->all();

        if (empty($positions)) {
            return 'hitter';
        }

        $hasPitch = collect($positions)->contains(fn ($pos) => 'P' === $pos || str_contains($pos, 'PITCH'));
        $hasNonPitch = collect($positions)->contains(fn ($pos) => 'P' !== $pos && ! str_contains($pos, 'PITCH'));

        if ($hasPitch && $hasNonPitch) {
            return 'two_way';
        }

        return $hasPitch ? 'pitcher' : 'hitter';
    }

    private function resolveAgeYears(string $playerId, ?string $asOfDate): ?int
    {
        $player = Player::query()->where('user_id', $playerId)->first();
        if ( ! $player || ! $player->born_date) {
            return null;
        }

        try {
            $dob = Carbon::parse((string) $player->born_date);
            $asOf = $asOfDate ? Carbon::parse($asOfDate) : now();
            return max(0, $dob->diffInYears($asOf));
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveAgeGroup(?int $ageYears): string
    {
        if (null === $ageYears) {
            return '15-18';
        }

        if ($ageYears <= 14) {
            return '12-14';
        }

        if ($ageYears <= 18) {
            return '15-18';
        }

        return '19-22';
    }

    private function ratio(?float $numerator, ?float $denominator): ?float
    {
        if (null === $numerator || null === $denominator || $denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator, 4);
    }

    private function toFloat(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ( ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function clamp(float $value, float $min = 0.0, float $max = 100.0): float
    {
        return max($min, min($max, $value));
    }

    private function lerp(float $x, float $x0, float $x1, float $y0, float $y1): float
    {
        if ($x1 === $x0) {
            return $y0;
        }

        return $y0 + (($x - $x0) / ($x1 - $x0)) * ($y1 - $y0);
    }
}
