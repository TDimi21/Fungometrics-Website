<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AssessmentRequest;
use App\Models\Player;
use App\Models\PlayerAssessment;
use App\Models\PlayerTeam;
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
                if (!isset($data[$rawKey]) || $data[$rawKey] === null || $data[$rawKey] === '') {
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
            $safe = fn ($k) => min(100, max(0, (int) ($data[$k] ?? 0)));

            $sq  = $safe('squat_percentile');
            $dl  = $safe('deadlift_percentile');
            $lng = $safe('lunge_percentile');
            $bp  = $safe('bench_press_percentile');
            $pu  = $safe('pull_up_percentile');
            $psh = $safe('push_up_percentile');
            $bj  = $safe('broad_jump_percentile');
            $vj  = $safe('vertical_jump_percentile');
            $sp  = $safe('sprint_10yd_percentile');
            $mb  = $safe('med_ball_rotational_percentile');
            $ev  = $safe('exit_velocity_percentile');
            $bs  = $safe('bat_speed_percentile');

            $lowerBody       = (int) round($sq * 0.60 + $dl * 0.25 + $lng * 0.15);
            $upperBody       = (int) round($bp * 0.50 + $pu * 0.25 + $psh * 0.25);
            $explosivePower  = (int) round($bj * 0.40 + $vj * 0.40 + $sp * 0.20);
            $rotationalPower = (int) round($mb * 0.60 + $ev * 0.25 + $bs * 0.15);
            $strengthOverall = (int) round(
                $lowerBody      * 0.30 +
                $upperBody      * 0.20 +
                $explosivePower * 0.25 +
                $rotationalPower * 0.25
            );

            // Mobility: average of provided 0-10 fields scaled to 0-100
            $mobilityFields = ['hip_mobility', 'shoulder_mobility', 'ankle_mobility', 'hip_flexor_mobility', 'rotational_mobility'];
            $mobilityVals   = array_filter(array_map(fn ($f) => isset($data[$f]) ? (int) $data[$f] : null, $mobilityFields), fn ($v) => $v !== null);
            $mobilityOverall = count($mobilityVals) > 0
                ? (int) round((array_sum($mobilityVals) / count($mobilityVals)) * 10)
                : 0;

            // Combined overall
            $overall = $strengthOverall > 0 && $mobilityOverall > 0
                ? (int) round($strengthOverall * 0.70 + $mobilityOverall * 0.30)
                : max($strengthOverall, $mobilityOverall);

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

            // Bust relevant caches
            $teamIds = PlayerTeam::where('user_id', (string) $request->user_id)
                ->whereNotNull('team_id')
                ->pluck('team_id')
                ->unique()
                ->values();

            foreach ($teamIds as $teamId) {
                Cache::forget("player_cards_v3_{$teamId}");
                Cache::forget("player_dev_board_{$teamId}");
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
