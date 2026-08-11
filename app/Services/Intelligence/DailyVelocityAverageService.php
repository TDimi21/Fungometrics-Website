<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DailyVelocityAverageService
{
    /**
     * Combine every supported hitting/pitching velocity source and return one
     * average per player/date. A session with many readings contributes every
     * valid reading; a manual or assessment value contributes one reading.
     *
     * @return array<int, array<string, int|float|string|null>>
     */
    public function forPlayer(string $teamId, string $playerId, CarbonInterface $since): array
    {
        $samples = collect();

        $this->addLegacySamples($samples, 'batting_practice_results', 'batter_id', 'velocity', 'hitting', $teamId, $playerId, $since);
        $this->addLegacySamples($samples, 'cage_practice_results', 'user_id', 'launch_angle_velocity', 'hitting', $teamId, $playerId, $since);
        $this->addLegacySamples($samples, 'exit_velocity_practices', 'user_id', 'velocity', 'hitting', $teamId, $playerId, $since);
        $this->addLegacySamples($samples, 'bullpen_practice_results', 'pitcher_id', 'miles_per_hour', 'pitching', $teamId, $playerId, $since);
        $this->addFitnessSamples($samples, $playerId, $since);
        $this->addAssessmentSamples($samples, $teamId, $playerId, $since);
        $this->addCanonicalSamples($samples, $teamId, $playerId, $since);

        return $samples
            ->groupBy('date')
            ->sortKeys()
            ->map(function (Collection $day, string $date): array {
                $hitting = $day->where('type', 'hitting')->pluck('value');
                $pitching = $day->where('type', 'pitching')->pluck('value');

                return [
                    'date' => $date,
                    'average_hitting_velocity' => $hitting->isNotEmpty() ? round((float) $hitting->avg(), 2) : null,
                    'hitting_sample_count' => $hitting->count(),
                    'average_pitching_velocity' => $pitching->isNotEmpty() ? round((float) $pitching->avg(), 2) : null,
                    'pitching_sample_count' => $pitching->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function addLegacySamples(Collection $samples, string $table, string $playerColumn, string $valueColumn, string $type, string $teamId, string $playerId, CarbonInterface $since): void
    {
        if ( ! Schema::hasTable($table) || ! $this->hasColumns($table, [$playerColumn, $valueColumn, 'created_at'])) {
            return;
        }

        $query = DB::table($table.' as result')
            ->leftJoin('practices as practice', 'practice.id', '=', 'result.practice_id')
            ->where('result.'.$playerColumn, $playerId)
            ->where('result.created_at', '>=', $since)
            ->where(function ($scope) use ($teamId): void {
                $scope->where('result.team_id', $teamId)->orWhereNull('result.team_id');
            });

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('result.deleted_at');
        }

        $query->get([
            'result.'.$valueColumn.' as value',
            'result.created_at',
            'practice.started as practice_started',
        ])->each(function ($row) use ($samples, $type): void {
            $this->push($samples, $type, $row->value ?? null, $row->practice_started ?? $row->created_at ?? null);
        });
    }

    private function addFitnessSamples(Collection $samples, string $playerId, CarbonInterface $since): void
    {
        PlayerFitness::query()
            ->where('user_id', $playerId)
            ->where('fitness_date', '>=', $since->toDateString())
            ->get(['fitness_date', 'exit_velo', 'pitch_velo'])
            ->each(function (PlayerFitness $row) use ($samples): void {
                $this->push($samples, 'hitting', $row->exit_velo, $row->fitness_date);
                $this->push($samples, 'pitching', $row->pitch_velo, $row->fitness_date);
            });
    }

    private function addAssessmentSamples(Collection $samples, string $teamId, string $playerId, CarbonInterface $since): void
    {
        PlayerAssessment::query()
            ->where('user_id', $playerId)
            ->where('assessment_date', '>=', $since->toDateString())
            ->where(function ($query) use ($teamId): void {
                $query->where('team_id', $teamId)->orWhereNull('team_id');
            })
            ->get(['assessment_date', 'hitting_data', 'pitching_data'])
            ->each(function (PlayerAssessment $row) use ($samples): void {
                $this->push($samples, 'hitting', $this->firstNumber($row->hitting_data, [
                    'avg_exit_velo', 'avg_exit_velocity', 'average_exit_velocity', 'exit_velocity', 'ev', 'velocity',
                ]), $row->assessment_date);
                $this->push($samples, 'pitching', $this->firstNumber($row->pitching_data, [
                    'avg_fastball_velocity', 'avg_fb_velocity', 'average_fastball_velocity', 'fastball_velocity', 'pitch_velocity', 'pitch_velo', 'velocity',
                ]), $row->assessment_date);
            });
    }

    private function addCanonicalSamples(Collection $samples, string $teamId, string $playerId, CarbonInterface $since): void
    {
        foreach (['canonical_metrics', 'canonical_events', 'external_sessions', 'baseball_concepts'] as $table) {
            if ( ! Schema::hasTable($table)) {
                return;
            }
        }

        $rows = DB::table('canonical_metrics as metric')
            ->join('baseball_concepts as concept', 'concept.id', '=', 'metric.baseball_concept_id')
            ->join('canonical_events as event', 'event.id', '=', 'metric.canonical_event_id')
            ->join('external_sessions as session', 'session.id', '=', 'event.external_session_id')
            ->where('session.team_id', $teamId)
            ->where('event.player_id', $playerId)
            ->whereIn('concept.canonical_key', ['hitting.exit_velocity', 'pitching.release_velocity'])
            ->whereNotNull('metric.numeric_value')
            ->where(function ($query) use ($since): void {
                $query->where('event.occurred_at', '>=', $since)
                    ->orWhere(function ($fallback) use ($since): void {
                        $fallback->whereNull('event.occurred_at')->where('session.occurred_at', '>=', $since);
                    })
                    ->orWhere(function ($fallback) use ($since): void {
                        $fallback->whereNull('event.occurred_at')->whereNull('session.occurred_at')->where('event.created_at', '>=', $since);
                    });
            })
            ->get([
                'event.id as event_id', 'event.occurred_at as event_date', 'event.created_at as created_date',
                'session.occurred_at as session_date', 'concept.canonical_key', 'metric.numeric_value',
            ]);

        if ($rows->isEmpty()) {
            return;
        }

        $eventDates = DB::table('canonical_metrics as metric')
            ->join('baseball_concepts as concept', 'concept.id', '=', 'metric.baseball_concept_id')
            ->whereIn('metric.canonical_event_id', $rows->pluck('event_id')->unique()->all())
            ->where('concept.canonical_key', 'session_context.event_date')
            ->pluck('metric.value', 'metric.canonical_event_id');

        $rows->each(function ($row) use ($samples, $eventDates): void {
            $date = $eventDates->get($row->event_id) ?: $row->event_date ?: $row->session_date ?: $row->created_date;
            $type = 'hitting.exit_velocity' === $row->canonical_key ? 'hitting' : 'pitching';
            $this->push($samples, $type, $row->numeric_value, $date);
        });
    }

    private function push(Collection $samples, string $type, mixed $value, mixed $date): void
    {
        $number = is_numeric($value) ? (float) $value : null;
        if (null === $number || $number <= 0 || $number > 150 || ! $date) {
            return;
        }

        try {
            $day = \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return;
        }

        $samples->push(['date' => $day, 'type' => $type, 'value' => $number]);
    }

    private function firstNumber(mixed $payload, array $keys): ?float
    {
        if ( ! is_array($payload)) {
            return null;
        }

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return null;
    }

    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if ( ! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
