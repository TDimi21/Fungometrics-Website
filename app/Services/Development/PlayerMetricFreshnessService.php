<?php

declare(strict_types=1);

namespace App\Services\Development;

use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class PlayerMetricFreshnessService
{
    /** @var array<string, list<array{source: string, field: string}>> */
    private const METRIC_SOURCES = [
        'body_weight' => [
            ['source' => 'player_fitness', 'field' => 'body_weight'],
            ['source' => 'player_assessment', 'field' => 'body_weight_lbs'],
        ],
        'bench_press' => [
            ['source' => 'player_fitness', 'field' => 'bench_press'],
            ['source' => 'player_assessment', 'field' => 'bench_lbs'],
        ],
        'front_squat' => [['source' => 'player_fitness', 'field' => 'front_squat']],
        'back_squat' => [
            ['source' => 'player_fitness', 'field' => 'back_squat'],
            ['source' => 'player_assessment', 'field' => 'squat_lbs'],
        ],
        'power_clean' => [['source' => 'player_fitness', 'field' => 'power_clean']],
        'hand_strength' => [['source' => 'player_fitness', 'field' => 'hand_strength']],
        'grip_strength_left' => [['source' => 'player_fitness', 'field' => 'grip_strength_left']],
        'grip_strength_right' => [['source' => 'player_fitness', 'field' => 'grip_strength_right']],
        'dead_lift' => [
            ['source' => 'player_fitness', 'field' => 'dead_lift'],
            ['source' => 'player_assessment', 'field' => 'deadlift_lbs'],
        ],
        'trap_bar_deadlift' => [['source' => 'player_fitness', 'field' => 'trap_bar_deadlift']],
        'vertical_jump' => [
            ['source' => 'player_fitness', 'field' => 'vertical_jump'],
            ['source' => 'player_assessment', 'field' => 'vertical_jump_in'],
        ],
        'broad_jump' => [
            ['source' => 'player_fitness', 'field' => 'broad_jump'],
            ['source' => 'player_assessment', 'field' => 'broad_jump_in'],
        ],
        'med_ball_rotational_throw' => [['source' => 'player_fitness', 'field' => 'med_ball_rotational_throw']],
        'sprint_10yd' => [
            ['source' => 'player_fitness', 'field' => 'sprint_10yd'],
            ['source' => 'player_assessment', 'field' => 'sprint_10yd_sec'],
        ],
        'exit_velo' => [['source' => 'player_fitness', 'field' => 'exit_velo']],
        'bat_speed' => [['source' => 'player_fitness', 'field' => 'bat_speed']],
        'throwing_velo' => [['source' => 'player_fitness', 'field' => 'throwing_velo']],
        'pitch_velo' => [['source' => 'player_fitness', 'field' => 'pitch_velo']],
        'yd_40_dash' => [['source' => 'player_fitness', 'field' => 'yd_40_dash']],
        'yd_60_dash' => [['source' => 'player_fitness', 'field' => 'yd_60_dash']],
        'sleep_hours' => [['source' => 'player_fitness', 'field' => 'sleep_hours']],
        'sleep_quality_1_to_5' => [['source' => 'player_fitness', 'field' => 'sleep_quality_1_to_5']],
        'recovery_score' => [['source' => 'player_fitness', 'field' => 'recovery_score']],
        'mobility_score' => [
            ['source' => 'player_fitness', 'field' => 'mobility_score'],
            ['source' => 'player_assessment', 'field' => 'mobility_overall_score'],
        ],
        'strength_score' => [['source' => 'player_fitness', 'field' => 'strength_score']],
        'overall_api_score' => [['source' => 'player_fitness', 'field' => 'overall_api_score']],
        'pull_ups' => [['source' => 'player_fitness', 'field' => 'pull_ups']],
        'push_ups' => [['source' => 'player_fitness', 'field' => 'push_ups']],
        'plank_hold' => [['source' => 'player_fitness', 'field' => 'plank_hold']],
    ];

    /**
     * Resolve every current physical input independently. A newer sparse row can
     * update one metric without erasing valid values recorded for other metrics.
     *
     * @return array{fitness: PlayerFitness|null, assessment: PlayerAssessment|null, metrics: array<string, array<string, mixed>>}
     */
    public function snapshot(string $playerId, ?string $teamId = null): array
    {
        $fitnessRows = PlayerFitness::query()
            ->where('user_id', $playerId)
            ->get()
            ->sortByDesc(fn (PlayerFitness $fitness): string => $this->rowSortKey($fitness->fitness_date, $fitness->updated_at ?? $fitness->created_at))
            ->values();

        $assessmentRows = PlayerAssessment::query()
            ->where('user_id', $playerId)
            ->when($teamId, function ($query) use ($teamId): void {
                $query->where(function ($scope) use ($teamId): void {
                    $scope->where('team_id', $teamId)->orWhereNull('team_id');
                });
            })
            ->get()
            ->sortByDesc(fn (PlayerAssessment $assessment): string => $this->rowSortKey($assessment->assessment_date, $assessment->updated_at ?? $assessment->created_at))
            ->values();

        $metrics = [];
        foreach (self::METRIC_SOURCES as $metric => $sources) {
            $candidate = $this->latestMetricCandidate($metric, $sources, $fitnessRows, $assessmentRows);
            if ($candidate) {
                $metrics[$metric] = $candidate;
            }
        }

        return [
            'fitness' => $this->coalescedFitness($playerId, $fitnessRows, $metrics),
            'assessment' => $this->coalescedAssessment($assessmentRows),
            'metrics' => $metrics,
        ];
    }

    /**
     * @param list<array{source: string, field: string}> $sources
     * @param Collection<int, PlayerFitness> $fitnessRows
     * @param Collection<int, PlayerAssessment> $assessmentRows
     * @return array<string, mixed>|null
     */
    private function latestMetricCandidate(string $metric, array $sources, Collection $fitnessRows, Collection $assessmentRows): ?array
    {
        $candidates = collect();

        foreach ($sources as $source) {
            $rows = 'player_fitness' === $source['source'] ? $fitnessRows : $assessmentRows;
            $dateField = 'player_fitness' === $source['source'] ? 'fitness_date' : 'assessment_date';

            foreach ($rows as $row) {
                $value = $this->metricValue($metric, $row, $source['field']);
                if (null === $value) {
                    continue;
                }

                $updatedAt = $row->updated_at ?? $row->created_at;
                $recordedAt = $row->{$dateField} ?? $updatedAt;
                $candidates->push([
                    'value' => $value,
                    'source' => $source['source'],
                    'source_field' => $source['field'],
                    'record_id' => (string) $row->id,
                    'recorded_at' => $this->dateString($recordedAt),
                    'updated_at' => $this->dateTimeString($updatedAt),
                    '_recorded_sort' => $this->timestamp($recordedAt),
                    '_updated_sort' => $this->timestamp($updatedAt),
                ]);
            }
        }

        $selected = $candidates
            ->sortByDesc(fn (array $candidate): string => sprintf('%020d:%020d', $candidate['_recorded_sort'], $candidate['_updated_sort']))
            ->first();

        if ( ! is_array($selected)) {
            return null;
        }

        unset($selected['_recorded_sort'], $selected['_updated_sort']);

        return $selected;
    }

    private function metricValue(string $metric, PlayerFitness|PlayerAssessment $row, string $field): ?float
    {
        $value = $row->{$field};
        if ('mobility_score' === $metric && $row instanceof PlayerAssessment) {
            $value = $this->assessmentMobilityScore($row);
        }

        if ( ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        $zeroIsValid = in_array($metric, ['recovery_score', 'strength_score', 'overall_api_score'], true);

        return ($zeroIsValid ? $number >= 0 : $number > 0) ? $number : null;
    }

    private function assessmentMobilityScore(PlayerAssessment $assessment): ?float
    {
        if (is_numeric($assessment->mobility_overall_score) && (float) $assessment->mobility_overall_score > 0) {
            return (float) $assessment->mobility_overall_score;
        }

        $values = collect([
            $assessment->hip_mobility,
            $assessment->shoulder_mobility,
            $assessment->ankle_mobility,
            $assessment->hip_flexor_mobility,
            $assessment->rotational_mobility,
        ])->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0);

        return $values->isNotEmpty() ? round((float) $values->avg() * 10, 1) : null;
    }

    /**
     * @param Collection<int, PlayerFitness> $rows
     * @param array<string, array<string, mixed>> $metrics
     */
    private function coalescedFitness(string $playerId, Collection $rows, array $metrics): ?PlayerFitness
    {
        if ($rows->isEmpty() && [] === $metrics) {
            return null;
        }

        $snapshot = $rows->first() ? clone $rows->first() : new PlayerFitness(['user_id' => $playerId]);
        $fillable = (new PlayerFitness())->getFillable();
        foreach (array_keys(self::METRIC_SOURCES) as $metric) {
            if (in_array($metric, $fillable, true)) {
                $snapshot->setAttribute($metric, $metrics[$metric]['value'] ?? null);
            }
        }

        $metadataRow = $rows->first(fn (PlayerFitness $fitness): bool => $this->filled($fitness->strength_test_metadata));
        if ($metadataRow) {
            $snapshot->setAttribute('strength_test_metadata', $metadataRow->strength_test_metadata);
        }

        $latestRecordedAt = collect($metrics)->pluck('recorded_at')->filter()->max();
        if ($latestRecordedAt) {
            $snapshot->setAttribute('fitness_date', $latestRecordedAt);
        }

        return $snapshot;
    }

    /** @param Collection<int, PlayerAssessment> $rows */
    private function coalescedAssessment(Collection $rows): ?PlayerAssessment
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $snapshot = clone $rows->first();
        foreach ($snapshot->getFillable() as $field) {
            if (in_array($field, ['user_id', 'team_id', 'assessed_by', 'assessment_date', 'type'], true)) {
                continue;
            }

            $row = $rows->first(fn (PlayerAssessment $assessment): bool => $this->filled($assessment->{$field}));
            if ($row) {
                $snapshot->setAttribute($field, $row->{$field});
            }
        }

        return $snapshot;
    }

    private function filled(mixed $value): bool
    {
        if (null === $value || '' === $value) {
            return false;
        }

        return ! is_array($value) || [] !== $value;
    }

    private function timestamp(mixed $value): int
    {
        if ($value instanceof CarbonInterface) {
            return $value->getTimestamp();
        }

        $timestamp = strtotime((string) $value);

        return false === $timestamp ? 0 : $timestamp;
    }

    private function rowSortKey(mixed $recordedAt, mixed $updatedAt): string
    {
        $recordedTimestamp = $this->timestamp($recordedAt);
        $updatedTimestamp = $this->timestamp($updatedAt);

        return sprintf('%020d:%020d', $recordedTimestamp > 0 ? $recordedTimestamp : $updatedTimestamp, $updatedTimestamp);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        $timestamp = strtotime((string) $value);

        return false === $timestamp ? null : date('Y-m-d', $timestamp);
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        $timestamp = strtotime((string) $value);

        return false === $timestamp ? null : date(DATE_ATOM, $timestamp);
    }
}
