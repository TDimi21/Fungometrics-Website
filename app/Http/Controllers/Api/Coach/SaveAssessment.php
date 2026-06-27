<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AssessmentRequest;
use App\Models\Player;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Services\AthleticPerformanceIndexService;
use App\Services\CreateServiceData;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveAssessment extends Controller
{
    /**
     * Metrics that can be percentile-scored from raw values.
     * `higher` means higher raw value is better for percentile rank.
     */
    private const RAW_PERCENTILE_METRICS = [
        'squat_lbs'       => ['percentile_key' => 'squat_percentile', 'higher' => true],
        'deadlift_lbs'    => ['percentile_key' => 'deadlift_percentile', 'higher' => true],
        'bench_lbs'       => ['percentile_key' => 'bench_press_percentile', 'higher' => true],
        'broad_jump_in'   => ['percentile_key' => 'broad_jump_percentile', 'higher' => true],
        'vertical_jump_in'=> ['percentile_key' => 'vertical_jump_percentile', 'higher' => true],
        'sprint_10yd_sec' => ['percentile_key' => 'sprint_10yd_percentile', 'higher' => false],
    ];

    private function computeAgeYears(?string $bornDate, ?string $assessmentDate): ?int
    {
        if (!$bornDate) {
            return null;
        }

        try {
            $dob = Carbon::parse($bornDate);
            $asOf = $assessmentDate ? Carbon::parse($assessmentDate) : Carbon::now();
            return max(0, $dob->diffInYears($asOf));
        } catch (Exception) {
            return null;
        }
    }

    private function percentileRank(array $values, float $value, bool $higherIsBetter): int
    {
        $clean = array_values(array_filter($values, fn ($v) => $v !== null && is_numeric($v)));
        if (!count($clean)) {
            return 0;
        }

        $n = count($clean);
        $less = 0;
        $equal = 0;

        foreach ($clean as $v) {
            $vf = (float) $v;
            if ($vf < $value) {
                $less++;
            } elseif ($vf === $value) {
                $equal++;
            }
        }

        // Standard percentile rank with tie handling.
        $pct = (($less + (0.5 * $equal)) / $n) * 100.0;

        // For sprint time and similar lower-is-better metrics, invert.
        if (!$higherIsBetter) {
            $pct = 100.0 - $pct;
        }

        return (int) round(max(0, min(100, $pct)));
    }

    private function cohortValuesForMetric(string $metricKey, ?string $teamId, ?int $ageYears, ?string $assessmentDate): array
    {
        $teamValues = [];
        $ageValues = [];

        if ($teamId) {
            $teamValues = PlayerAssessment::query()
                ->whereNotNull($metricKey)
                ->where('team_id', $teamId)
                ->pluck($metricKey)
                ->map(fn ($v) => (float) $v)
                ->values()
                ->all();
        }

        if ($ageYears !== null) {
            $asOf = $assessmentDate ?: Carbon::now()->toDateString();
            $ageValues = DB::table('player_assessments')
                ->join('players', 'players.user_id', '=', 'player_assessments.user_id')
                ->whereNull('player_assessments.deleted_at')
                ->whereNotNull("player_assessments.{$metricKey}")
                ->whereNotNull('players.born_date')
                ->whereRaw('TIMESTAMPDIFF(YEAR, players.born_date, ?) = ?', [$asOf, $ageYears])
                ->pluck("player_assessments.{$metricKey}")
                ->map(fn ($v) => (float) $v)
                ->values()
                ->all();
        }

        return [$teamValues, $ageValues];
    }

    /**
     * Build a fitness snapshot payload from assessment data so assessment becomes
     * the authoritative update for player metrics.
     */
    private function buildFitnessSnapshotData(array $data): array
    {
        $toFloat = static fn ($k) => isset($data[$k]) && $data[$k] !== '' && $data[$k] !== null
            ? (float) $data[$k]
            : null;

        $toInt = static fn ($k) => isset($data[$k]) && $data[$k] !== '' && $data[$k] !== null
            ? (int) round((float) $data[$k])
            : null;

        $mobilityFields = ['hip_mobility', 'shoulder_mobility', 'ankle_mobility', 'hip_flexor_mobility', 'rotational_mobility'];
        $mobilityVals = array_values(array_filter(
            array_map(fn (string $f) => $toInt($f), $mobilityFields),
            fn ($v) => $v !== null
        ));

        $mobilityScore = count($mobilityVals) > 0
            ? (int) round((array_sum($mobilityVals) / count($mobilityVals)) * 10)
            : null;

        return array_filter([
            'user_id' => (string) ($data['user_id'] ?? ''),
            'fitness_date' => isset($data['assessment_date']) && $data['assessment_date'] ? (string) $data['assessment_date'] : now()->toDateString(),
            'body_weight' => $toFloat('body_weight_lbs'),
            'back_squat' => $toInt('squat_lbs'),
            'dead_lift' => $toInt('deadlift_lbs'),
            'bench_press' => $toInt('bench_lbs'),
            'broad_jump' => $toFloat('broad_jump_in'),
            'vertical_jump' => $toFloat('vertical_jump_in'),
            'sprint_10yd' => $toFloat('sprint_10yd_sec'),
            'mobility_score' => $mobilityScore,
            'strength_score' => isset($data['strength_overall_score']) ? (int) $data['strength_overall_score'] : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function __invoke(AssessmentRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (!isset($data['assessed_by']) || !$data['assessed_by']) {
                $data['assessed_by'] = (string) optional($request->user())->id;
            }

            // Mobile may send null/empty team_id in some flows; infer from player-team pivot.
            if (!isset($data['team_id']) || !$data['team_id']) {
                $inferredTeamId = PlayerTeam::query()
                    ->where('user_id', (string) $request->user_id)
                    ->whereNotNull('team_id')
                    ->value('team_id');

                if ($inferredTeamId) {
                    $data['team_id'] = (string) $inferredTeamId;
                }
            }

            $player = Player::query()->where('user_id', (string) $request->user_id)->first();
            $assessmentDate = isset($data['assessment_date']) ? (string) $data['assessment_date'] : null;
            $ageYears = $this->computeAgeYears(optional($player)->born_date, $assessmentDate);

            // Build team + age-group percentiles from raw values.
            $teamPercentiles = [];
            $agePercentiles = [];

            foreach (self::RAW_PERCENTILE_METRICS as $rawKey => $cfg) {
                // Only score metrics the coach actually entered. A missing or zero
                // value is treated as "not tested" and excluded entirely.
                if (!isset($data[$rawKey]) || $data[$rawKey] === null || $data[$rawKey] === '' || (float) $data[$rawKey] <= 0) {
                    continue;
                }

                $rawValue = (float) $data[$rawKey];
                [$teamValues, $ageValues] = $this->cohortValuesForMetric(
                    $rawKey,
                    isset($data['team_id']) ? (string) $data['team_id'] : null,
                    $ageYears,
                    $assessmentDate
                );

                // Include current attempt in cohort rank.
                $teamValues[] = $rawValue;
                $ageValues[] = $rawValue;

                $teamPct = $this->percentileRank($teamValues, $rawValue, (bool) $cfg['higher']);
                $agePct = $this->percentileRank($ageValues, $rawValue, (bool) $cfg['higher']);

                $percentileKey = (string) $cfg['percentile_key'];
                $derivedPct = (int) round(($teamPct + $agePct) / 2);

                // This drives strength score computation.
                $data[$percentileKey] = $derivedPct;

                $teamPercentiles[$percentileKey] = $teamPct;
                $agePercentiles[$percentileKey] = $agePct;
            }

            $teamPctValues = array_values($teamPercentiles);
            $agePctValues = array_values($agePercentiles);
            $overallTeamPercentile = count($teamPctValues)
                ? (int) round(array_sum($teamPctValues) / count($teamPctValues))
                : null;
            $overallAgePercentile = count($agePctValues)
                ? (int) round(array_sum($agePctValues) / count($agePctValues))
                : null;

            // ── Compute scores server-side from percentiles ───────────────────
            // Only metrics the coach actually entered (percentile > 0) count. Missing
            // or zero inputs are excluded and the remaining weights renormalized, so
            // an un-tested area stays blank (null) instead of being scored as 0 and
            // dragging down the overall.
            $weightedPresent = function (array $weighted) use ($data): ?int {
                $sum = 0.0;
                $weight = 0.0;
                foreach ($weighted as [$key, $w]) {
                    $v = (int) ($data[$key] ?? 0);
                    if ($v > 0) {
                        $sum += min(100, $v) * $w;
                        $weight += $w;
                    }
                }
                return $weight > 0.0 ? (int) round($sum / $weight) : null;
            };

            // Same idea but over already-computed sub-scores, which may be null.
            $weightedScores = function (array $weighted): ?int {
                $sum = 0.0;
                $weight = 0.0;
                foreach ($weighted as [$score, $w]) {
                    if ($score !== null && $score > 0) {
                        $sum += $score * $w;
                        $weight += $w;
                    }
                }
                return $weight > 0.0 ? (int) round($sum / $weight) : null;
            };

            $lowerBody = $weightedPresent([
                ['squat_percentile', 0.60], ['deadlift_percentile', 0.25], ['lunge_percentile', 0.15],
            ]);
            $upperBody = $weightedPresent([
                ['bench_press_percentile', 0.50], ['pull_up_percentile', 0.25], ['push_up_percentile', 0.25],
            ]);
            $explosivePower = $weightedPresent([
                ['broad_jump_percentile', 0.40], ['vertical_jump_percentile', 0.40], ['sprint_10yd_percentile', 0.20],
            ]);
            $rotationalPower = $weightedPresent([
                ['med_ball_rotational_percentile', 0.60], ['exit_velocity_percentile', 0.25], ['bat_speed_percentile', 0.15],
            ]);
            $strengthOverall = $weightedScores([
                [$lowerBody, 0.30], [$upperBody, 0.20], [$explosivePower, 0.25], [$rotationalPower, 0.25],
            ]);

            // Mobility: average of entered (>0) 0-10 fields scaled to 0-100; blank if none.
            $mobilityFields = ['hip_mobility', 'shoulder_mobility', 'ankle_mobility', 'hip_flexor_mobility', 'rotational_mobility'];
            $mobilityVals   = array_filter(
                array_map(fn ($f) => isset($data[$f]) ? (int) $data[$f] : null, $mobilityFields),
                fn ($v) => $v !== null && $v > 0
            );
            $mobilityOverall = count($mobilityVals) > 0
                ? (int) round((array_sum($mobilityVals) / count($mobilityVals)) * 10)
                : null;

            // Combined overall: weighted over whichever of strength / mobility exists.
            $overall = $weightedScores([[$strengthOverall, 0.70], [$mobilityOverall, 0.30]]);

            $data = array_merge($data, [
                'strength_lower_body_score'    => $lowerBody,
                'strength_upper_body_score'    => $upperBody,
                'strength_explosive_score'     => $explosivePower,
                'strength_rotational_score'    => $rotationalPower,
                'strength_overall_score'       => $strengthOverall,
                'mobility_overall_score'       => $mobilityOverall,
                'overall_score'                => $overall,
                'team_percentiles'             => $teamPercentiles ?: null,
                'age_group_percentiles'        => $agePercentiles ?: null,
                'overall_team_percentile'      => $overallTeamPercentile,
                'overall_age_percentile'       => $overallAgePercentile,
                'age_group_years'              => $ageYears,
            ]);

            $assessment = (new CreateServiceData(new PlayerAssessment()))->handle($data);

            // Keep player metrics in sync with coach assessment (assessment is authoritative).
            try {
                $fitnessSnapshot = $this->buildFitnessSnapshotData($data);
                if (!empty($fitnessSnapshot['user_id'])) {
                    $fitnessDate = (string) ($fitnessSnapshot['fitness_date'] ?? now()->toDateString());

                    $playerFitness = PlayerFitness::query()->updateOrCreate(
                        [
                            'user_id' => (string) $fitnessSnapshot['user_id'],
                            'fitness_date' => $fitnessDate,
                        ],
                        $fitnessSnapshot
                    );

                    (new AthleticPerformanceIndexService())->calculateAndSave($playerFitness);
                }
            } catch (Exception $fitnessException) {
                Log::warning('SaveAssessment fitness sync warning: ' . $fitnessException->getMessage());
            }

            try {
                $latestFitness = PlayerFitness::query()
                    ->where('user_id', (string) $request->user_id)
                    ->orderByDesc('fitness_date')
                    ->orderByDesc('created_at')
                    ->first();

                if ($latestFitness) {
                    (new AthleticPerformanceIndexService())->calculateAndSave($latestFitness);
                }
            } catch (Exception $scoreException) {
                Log::warning('SaveAssessment score sync warning: ' . $scoreException->getMessage());
            }

            // Bust relevant caches
            $teamIds = PlayerTeam::where('user_id', (string) $request->user_id)
                ->whereNotNull('team_id')
                ->pluck('team_id')
                ->unique()
                ->values();

            foreach ($teamIds as $teamId) {
                Cache::forget("player_cards_v3_{$teamId}");
                Cache::forget("player_dev_board_{$teamId}");
                Cache::forget("performance_overview_{$teamId}");
                Cache::forget("dashboard_graphics_{$teamId}");
                foreach ([30, 60, 90, 120] as $days) {
                    Cache::forget("dev_dashboard_{$teamId}_{$request->user_id}_{$days}");
                    Cache::forget("dev_dashboard_v2_{$teamId}_{$request->user_id}_{$days}");
                }
            }

            return response()->json([
                'code'    => '060',
                'message' => 'assessment saved for player ' . $request->user_id,
                'status'  => 'success',
                'data'    => $assessment,
            ], HttpCodes::HTTP_CREATED);

        } catch (Exception $e) {
            Log::error('SaveAssessment: ' . $e->getMessage());
            return response()->json([
                'code'    => '060-E',
                'message' => 'failed to save assessment',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
