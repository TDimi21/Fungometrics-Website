<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeamBenchmarkOverrideService
{
    public const TIERS = ['p5', 'p25', 'p50', 'p75', 'p95'];
    private ?bool $tableAvailable = null;

    private function available(): bool
    {
        return $this->tableAvailable ??= Schema::hasTable('team_benchmark_overrides');
    }

    public function effectiveAnchors(string $metricKey, string $ageGroup, mixed $teamId = null): ?array
    {
        $defaults = app(BenchmarkLibrary::class)->percentileAnchors($metricKey, $ageGroup);
        if (! $defaults || ! $teamId || ! $this->available()) {
            return $defaults;
        }

        $row = DB::table('team_benchmark_overrides')
            ->where('team_id', (string) $teamId)
            ->where('metric_key', BenchmarkDefinitions::normalizeMetricKey($metricKey))
            ->where('age_group', $ageGroup)
            ->first(self::TIERS);

        if (! $row) return $defaults;

        return collect(self::TIERS)->mapWithKeys(fn (string $tier) => [$tier => (float) $row->{$tier}])->all();
    }

    public function overridesForTeam(string $teamId): array
    {
        if (! $this->available()) return [];

        return DB::table('team_benchmark_overrides')->where('team_id', $teamId)->get()
            ->keyBy(fn ($row) => $row->metric_key.'|'.$row->age_group)
            ->map(fn ($row) => collect(self::TIERS)->mapWithKeys(fn (string $tier) => [$tier => (float) $row->{$tier}])->all())
            ->all();
    }
}
