<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PopulationMetricRepository
{
    private const SUPPORTED_METRICS = [
        'average_exit_velocity',
        'max_exit_velocity',
        'average_fastball_velocity',
        'max_fastball_velocity',
        'strike_percentage',
        'long_toss_max_distance',
        'weighted_ball_5oz_velocity',
        'bench_press',
        'squat',
        'deadlift',
        'forty_yard_dash',
        'sixty_yard_dash',
        'broad_jump',
        'vertical_jump',
        'mobility_score',
    ];

    public function valuesForMetric(string $metricKey, array $context = [], int $days = 365): array
    {
        try {
            $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
            $days = max(1, $days);
            $rows = match ($metricKey) {
                'average_exit_velocity' => $this->exitVelocityRows($context, $days, 'avg'),
                'max_exit_velocity' => $this->exitVelocityRows($context, $days, 'max'),
                'average_fastball_velocity' => $this->aggregateRows($this->sourceRows('bullpen_practice_results', 'pitcher_id', 'miles_per_hour', $context, $days), 'avg'),
                'max_fastball_velocity' => $this->aggregateRows($this->sourceRows('bullpen_practice_results', 'pitcher_id', 'miles_per_hour', $context, $days), 'max'),
                'strike_percentage' => $this->strikePercentageRows($context, $days),
                'long_toss_max_distance' => $this->aggregateRows($this->sourceRows('long_toss_practices', 'user_id', 'distance', $context, $days), 'max'),
                'weighted_ball_5oz_velocity' => $this->aggregateRows($this->sourceRows('weight_ball_practices', 'user_id', 'velocity', $context, $days, ['weight' => 5]), 'max'),
                'bench_press' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'bench_press'],
                    ['player_assessments', 'user_id', 'bench_lbs'],
                ], 'max'),
                'squat' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'back_squat'],
                    ['player_fitnesses', 'user_id', 'front_squat'],
                    ['player_assessments', 'user_id', 'squat_lbs'],
                ], 'max'),
                'deadlift' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'dead_lift'],
                    ['player_assessments', 'user_id', 'deadlift_lbs'],
                ], 'max'),
                'forty_yard_dash' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'yd_40_dash'],
                ], 'min'),
                'sixty_yard_dash' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'yd_60_dash'],
                ], 'min'),
                'broad_jump' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'broad_jump'],
                    ['player_assessments', 'user_id', 'broad_jump_in'],
                ], 'max'),
                'vertical_jump' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'vertical_jump'],
                    ['player_assessments', 'user_id', 'vertical_jump_in'],
                ], 'max'),
                'mobility_score' => $this->strengthRows($context, $days, [
                    ['player_fitnesses', 'user_id', 'mobility_score'],
                    ['player_assessments', 'user_id', 'mobility_overall_score'],
                ], 'latest'),
                default => collect(),
            };

            return $this->filterRowsByContext($rows, $context)
                ->map(fn (array $row) => $this->numberOrNull($row['value'] ?? null, $metricKey))
                ->filter(fn (?float $value) => $value !== null && $this->validPopulationValue($metricKey, $value))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function countForMetric(string $metricKey, array $context = [], int $days = 365): int
    {
        return count($this->valuesForMetric($metricKey, $context, $days));
    }

    public function availableMetrics(array $context = [], int $days = 365): array
    {
        $metrics = [];

        foreach (self::SUPPORTED_METRICS as $metricKey) {
            $count = $this->countForMetric($metricKey, $context, $days);
            if ($count > 0) {
                $metrics[$metricKey] = $count;
            }
        }

        return $metrics;
    }

    public function supportedMetricKeys(): array
    {
        return self::SUPPORTED_METRICS;
    }

    private function exitVelocityRows(array $context, int $days, string $aggregate): Collection
    {
        $rows = collect()
            ->merge($this->sourceRows('exit_velocity_practices', 'user_id', 'velocity', $context, $days))
            ->merge($this->sourceRows('batting_practice_results', 'batter_id', 'velocity', $context, $days))
            ->merge($this->sourceRows('cage_practice_results', 'user_id', 'launch_angle_velocity', $context, $days));

        return $this->aggregateRows($rows, $aggregate);
    }

    private function strengthRows(array $context, int $days, array $sources, string $aggregate): Collection
    {
        $rows = collect();

        foreach ($sources as [$table, $userColumn, $valueColumn]) {
            $rows = $rows->merge($this->sourceRows($table, $userColumn, $valueColumn, $context, $days));
        }

        return $this->aggregateRows($rows, $aggregate);
    }

    private function strikePercentageRows(array $context, int $days): Collection
    {
        $rows = $this->sourceRows('bullpen_practice_results', 'pitcher_id', 'is_strike', $context, $days, [], true);

        return $rows
            ->groupBy('user_id')
            ->map(function (Collection $playerRows) {
                $values = $playerRows
                    ->map(fn (array $row) => $this->numberOrNull($row['value'] ?? null, 'strike_percentage'))
                    ->filter(fn ($value) => $value !== null);

                return [
                    'user_id' => $playerRows->first()['user_id'] ?? null,
                    'value' => $values->isNotEmpty() ? round((float) $values->avg() * 100, 1) : null,
                ];
            })
            ->values();
    }

    private function sourceRows(string $table, string $userColumn, string $valueColumn, array $context, int $days, array $where = [], bool $allowZero = false): Collection
    {
        if (! $this->hasColumns($table, [$userColumn, $valueColumn])) {
            return collect();
        }

        $query = DB::table($table)
            ->select([
                $userColumn.' as user_id',
                $valueColumn.' as value',
            ]);

        if (Schema::hasColumn($table, 'created_at')) {
            $query->addSelect('created_at')->where('created_at', '>=', now()->subDays($days));
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, (string) $column)) {
                $query->where((string) $column, $value);
            }
        }

        $this->applyTeamScope($query, $table, $userColumn, $context);

        return collect($query->get())
            ->map(function ($row) use ($allowZero) {
                $value = $this->parseStoredValue($row->value ?? null);
                if ($value === null || (! $allowZero && $value <= 0)) {
                    return null;
                }

                return [
                    'user_id' => (string) ($row->user_id ?? ''),
                    'value' => $value,
                    'created_at' => $row->created_at ?? null,
                ];
            })
            ->filter()
            ->values();
    }

    private function aggregateRows(Collection $rows, string $aggregate): Collection
    {
        return $rows
            ->groupBy('user_id')
            ->map(function (Collection $playerRows) use ($aggregate) {
                $values = $playerRows
                    ->pluck('value')
                    ->map(fn ($value) => is_numeric($value) ? (float) $value : null)
                    ->filter(fn ($value) => $value !== null);

                if ($values->isEmpty()) {
                    $value = null;
                } else {
                    $value = match ($aggregate) {
                        'avg' => round((float) $values->avg(), 1),
                        'min' => round((float) $values->min(), 2),
                        'latest' => $this->latestValue($playerRows),
                        default => round((float) $values->max(), 1),
                    };
                }

                return [
                    'user_id' => $playerRows->first()['user_id'] ?? null,
                    'value' => $value,
                ];
            })
            ->values();
    }

    private function latestValue(Collection $rows): ?float
    {
        $latest = $rows
            ->sortByDesc(fn (array $row) => $row['created_at'] ?? '')
            ->first();

        return $this->numberOrNull($latest['value'] ?? null);
    }

    private function applyTeamScope($query, string $table, string $userColumn, array $context): void
    {
        $teamId = $context['team_id'] ?? $context['teamId'] ?? null;
        if (! $teamId) {
            return;
        }

        $rosterIds = $this->rosterUserIds((string) $teamId);
        if (! empty($rosterIds)) {
            $query->whereIn($userColumn, $rosterIds);
        }

        if (Schema::hasColumn($table, 'team_id')) {
            $query->where(function ($scope) use ($teamId) {
                $scope->where('team_id', $teamId)->orWhereNull('team_id');
            });
        }
    }

    private function filterRowsByContext(Collection $rows, array $context): Collection
    {
        if (! $this->hasBucketFilters($context)) {
            return $rows;
        }

        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
        $userContexts = $this->userContexts($userIds);

        return $rows->filter(function (array $row) use ($context, $userContexts) {
            $userContext = $userContexts[(string) ($row['user_id'] ?? '')] ?? null;

            return $userContext !== null && $this->matchesContext($userContext, $context);
        })->values();
    }

    private function matchesContext(array $userContext, array $context): bool
    {
        if ($this->filled($context['age_group'] ?? null) && strtoupper((string) $context['age_group']) !== $userContext['age_group']) {
            return false;
        }

        if ($this->filled($context['level'] ?? null) && strtolower((string) $context['level']) !== strtolower((string) ($userContext['level'] ?? ''))) {
            return false;
        }

        if ($this->filled($context['throws'] ?? $context['throw_side'] ?? null) && $this->normalizeSide($context['throws'] ?? $context['throw_side']) !== $this->normalizeSide($userContext['throws'] ?? null)) {
            return false;
        }

        if ($this->filled($context['bats'] ?? $context['hit_side'] ?? null) && $this->normalizeSide($context['bats'] ?? $context['hit_side']) !== $this->normalizeSide($userContext['bats'] ?? null)) {
            return false;
        }

        if ($this->filled($context['position'] ?? $context['positions'] ?? null) && ! $this->positionMatches($context['position'] ?? $context['positions'], $userContext['positions'] ?? [])) {
            return false;
        }

        if ($this->filled($context['bodyweight_band'] ?? null) && (string) $context['bodyweight_band'] !== $this->bodyWeightBand($userContext['body_weight'] ?? null)) {
            return false;
        }

        if ($this->filled($context['height_band'] ?? null) && (string) $context['height_band'] !== $this->heightBand($userContext['height_inches'] ?? null)) {
            return false;
        }

        return true;
    }

    private function userContexts(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return User::query()
            ->with(['profile', 'player', 'positions'])
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(function (User $user) {
                $bornDate = $user->player?->born_date;
                $heightInches = $this->heightInches($user->player?->height_in_ft, $user->player?->height_in_inch);

                return [(string) $user->id => [
                    'age_group' => $this->ageGroupFromDate($bornDate),
                    'level' => $user->profile?->level,
                    'positions' => $user->positions?->pluck('position')->filter()->values()->all() ?? [],
                    'body_weight' => $this->playerBodyWeight($user),
                    'height_inches' => $heightInches,
                    'throws' => $user->player?->throw_side,
                    'bats' => $user->player?->hit_side,
                ]];
            })
            ->all();
    }

    private function playerBodyWeight(User $user): ?float
    {
        $playerWeight = $user->player && isset($user->player->weight) ? $this->numberOrNull($user->player->weight) : null;
        if ($playerWeight !== null) {
            return $playerWeight;
        }

        if ($this->hasColumns('player_fitnesses', ['user_id', 'body_weight'])) {
            $fitnessWeight = DB::table('player_fitnesses')
                ->where('user_id', $user->id)
                ->when(Schema::hasColumn('player_fitnesses', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->orderByDesc('fitness_date')
                ->orderByDesc('created_at')
                ->value('body_weight');

            if ($this->numberOrNull($fitnessWeight) !== null) {
                return (float) $fitnessWeight;
            }
        }

        if ($this->hasColumns('player_assessments', ['user_id', 'body_weight_lbs'])) {
            $assessmentWeight = DB::table('player_assessments')
                ->where('user_id', $user->id)
                ->when(Schema::hasColumn('player_assessments', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->orderByDesc('assessment_date')
                ->orderByDesc('created_at')
                ->value('body_weight_lbs');

            return $this->numberOrNull($assessmentWeight);
        }

        return null;
    }

    private function rosterUserIds(string $teamId): array
    {
        if (! $this->hasColumns('player_teams', ['team_id', 'user_id'])) {
            return [];
        }

        return DB::table('player_teams')
            ->where('team_id', $teamId)
            ->when(Schema::hasColumn('player_teams', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function hasBucketFilters(array $context): bool
    {
        foreach (['age_group', 'position', 'positions', 'level', 'bodyweight_band', 'height_band', 'throws', 'throw_side', 'bats', 'hit_side'] as $key) {
            if ($this->filled($context[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function numberOrNull(mixed $value, ?string $metricKey = null): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && str_contains($value, ':')) {
            return $this->timeStringToSeconds($value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function parseStoredValue(mixed $value): ?float
    {
        return $this->numberOrNull($value);
    }

    private function timeStringToSeconds(string $value): ?float
    {
        $parts = array_map('trim', explode(':', $value));
        if (count($parts) === 0) {
            return null;
        }

        $seconds = 0.0;
        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                return null;
            }
            $seconds = ($seconds * 60) + (float) $part;
        }

        return round($seconds, 2);
    }

    private function validPopulationValue(string $metricKey, float $value): bool
    {
        if ($metricKey === 'strike_percentage') {
            return $value >= 0;
        }

        return $value > 0;
    }

    private function ageGroupFromDate(mixed $date): string
    {
        if (! $date) {
            return BenchmarkDefinitions::AGE_UNKNOWN;
        }

        try {
            return BenchmarkDefinitions::ageGroup(Carbon::parse((string) $date)->age);
        } catch (\Throwable) {
            return BenchmarkDefinitions::AGE_UNKNOWN;
        }
    }

    private function positionMatches(mixed $requested, array $actual): bool
    {
        $requested = $this->normalizeList($requested);
        $actual = $this->normalizeList($actual);

        return ! empty(array_intersect($requested, $actual));
    }

    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,\/|;]+/', (string) $value) ?: [];
        }

        return collect($items)
            ->map(fn ($item) => strtolower(trim((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeSide(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function heightInches(mixed $feet, mixed $inches): ?float
    {
        $feet = is_numeric($feet) ? (float) $feet : null;
        $inches = is_numeric($inches) ? (float) $inches : null;

        if ($feet === null && $inches === null) {
            return null;
        }

        return (($feet ?? 0.0) * 12) + ($inches ?? 0.0);
    }

    private function bodyWeightBand(mixed $value): string
    {
        $value = $this->numberOrNull($value);
        if ($value === null || $value <= 0) {
            return 'unknown';
        }

        return match (true) {
            $value < 120 => 'under_120',
            $value < 150 => '120_149',
            $value < 180 => '150_179',
            $value < 210 => '180_209',
            default => '210_plus',
        };
    }

    private function heightBand(mixed $value): string
    {
        $value = $this->numberOrNull($value);
        if ($value === null || $value <= 0) {
            return 'unknown';
        }

        return match (true) {
            $value < 63 => 'under_63',
            $value < 66 => '63_65',
            $value < 69 => '66_68',
            $value < 72 => '69_71',
            $value < 75 => '72_74',
            default => '75_plus',
        };
    }

    private function filled(mixed $value): bool
    {
        if (is_array($value)) {
            return ! empty(array_filter($value, fn ($item) => trim((string) $item) !== ''));
        }

        $value = trim((string) $value);

        return $value !== '' && strtolower($value) !== 'unknown';
    }
}
