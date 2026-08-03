<?php

declare(strict_types=1);

namespace App\Services\Blast;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BlastSessionDevelopmentReportService
{
    private const METRICS = [
        ['swing_quality', 'plane_score', 'hitting.blast_plane_score', 'Plane Score', null, 1],
        ['swing_quality', 'connection_score', 'hitting.blast_connection_score', 'Connection Score', null, 1],
        ['swing_quality', 'rotation_score', 'hitting.blast_rotation_score', 'Rotation Score', null, 1],
        ['speed_power', 'bat_speed', 'hitting.bat_speed', 'Bat Speed', 'mph', 1],
        ['speed_power', 'peak_hand_speed', 'hitting.peak_hand_speed', 'Peak Hand Speed', 'mph', 1],
        ['speed_power', 'rotational_acceleration', 'hitting.rotational_acceleration', 'Rotational Acceleration', 'g', 1],
        ['speed_power', 'power', 'hitting.blast_swing_power', 'Power', 'kW', 2],
        ['swing_shape', 'on_plane_efficiency', 'hitting.on_plane_efficiency', 'On-Plane Efficiency', '%', 1],
        ['swing_shape', 'attack_angle', 'hitting.attack_angle', 'Attack Angle', '°', 1],
        ['swing_shape', 'vertical_bat_angle', 'hitting.vertical_bat_angle', 'Vertical Bat Angle', '°', 1],
        ['connection_sequence', 'early_connection', 'hitting.early_connection', 'Early Connection', '°', 1],
        ['connection_sequence', 'connection_at_impact', 'hitting.connection_at_impact', 'Connection at Impact', '°', 1],
        ['connection_sequence', 'time_to_contact', 'hitting.time_to_contact', 'Time to Contact', 's', 3],
        ['ball_flight', 'exit_velocity', 'hitting.exit_velocity', 'Exit Velocity', 'mph', 1],
        ['ball_flight', 'launch_angle', 'hitting.launch_angle', 'Launch Angle', '°', 1],
        ['ball_flight', 'estimated_distance', 'hitting.projected_distance', 'Estimated Distance', 'ft', 0],
    ];

    public function __construct(private readonly BlastBenchmarkComparator $comparator)
    {
    }

    /** @return array<string, mixed> */
    public function report(string $batchId, string $benchmarkLevel): array
    {
        $batch = DB::table('import_batches as b')->join('translation_snapshots as snap', 'snap.id', '=', 'b.translation_snapshot_id')
            ->join('external_sessions as session', 'session.import_batch_id', '=', 'b.id')
            ->join('platform_definitions as platform', 'platform.id', '=', 'session.platform_definition_id')
            ->join('profiles as profile', 'profile.user_id', '=', 'session.player_id')
            ->where('b.id', $batchId)->where('platform.key', 'blast-motion')->where('b.status', 'completed')
            ->first(['b.id', 'snap.team_id', 'session.id as session_id', 'session.player_id', 'session.label', 'session.metadata', 'profile.first_name', 'profile.last_name', 'profile.level']);
        if (!$batch) throw new RuntimeException('Blast session not found.');

        $rows = DB::table('canonical_events as event')->leftJoin('canonical_metrics as metric', 'metric.canonical_event_id', '=', 'event.id')
            ->leftJoin('baseball_concepts as concept', 'concept.id', '=', 'metric.baseball_concept_id')
            ->where('event.external_session_id', $batch->session_id)->orderBy('event.event_order')
            ->get(['event.id', 'event.event_order', 'event.occurred_at', 'event.source_context', 'concept.canonical_key', 'metric.numeric_value']);
        $events = $rows->groupBy('id')->map(function (Collection $items): array {
            $first = $items->first();
            return ['id' => $first->id, 'order' => (int) $first->event_order, 'occurred_at' => $first->occurred_at,
                'context' => json_decode((string) $first->source_context, true) ?: [],
                'metrics' => $items->filter(fn ($item) => null !== $item->canonical_key)->mapWithKeys(fn ($item) => [$item->canonical_key => null === $item->numeric_value ? null : (float) $item->numeric_value])->all()];
        })->values()->all();
        if ([] === $events) throw new RuntimeException('This Blast session has no swing events.');

        $best = $this->bestSwing($events);
        $metricRows = array_map(function (array $definition) use ($events, $best, $benchmarkLevel): array {
            [$group, $key, $canonical, $label, $unit, $precision] = $definition;
            $values = array_values(array_filter(array_column(array_column($events, 'metrics'), $canonical), fn ($value) => null !== $value));
            $available = [] !== $values;
            $average = $available ? array_sum($values) / count($values) : null;
            return ['group' => $group, 'key' => $key, 'label' => $label, 'unit' => $unit, 'available' => $available,
                'average' => $available ? round($average, $precision) : null,
                'best_swing' => isset($best['metrics'][$canonical]) ? round($best['metrics'][$canonical], $precision) : null,
                'benchmark' => $available ? $this->benchmark($key, $average, $benchmarkLevel) : $this->unavailableBenchmark()];
        }, self::METRICS);
        $dates = array_values(array_filter(array_column($events, 'occurred_at')));
        $contexts = array_column($events, 'context');
        $swingTypes = collect($contexts)->countBy(fn (array $context) => $context['swing_details'] ?? 'Unspecified')->map(fn ($count, $label) => ['label' => $label, 'count' => $count])->values()->all();
        $level = config("blast_benchmarks.levels.{$benchmarkLevel}");
        return [
            'player' => ['id' => $batch->player_id, 'name' => trim($batch->first_name.' '.$batch->last_name), 'handedness' => $contexts[0]['handedness'] ?? null, 'benchmark_level' => $benchmarkLevel],
            'session' => ['id' => $batch->session_id, 'total_swings' => count($events), 'date_start' => $dates ? min($dates) : null, 'date_end' => $dates ? max($dates) : null, 'equipment' => $contexts[0]['equipment'] ?? null, 'swing_types' => $swingTypes],
            'benchmark' => ['key' => $benchmarkLevel, 'label' => $level['label'], 'version' => config('blast_benchmarks.version')],
            'metrics' => $metricRows, 'best_swing_event_id' => $best['id'],
            'ball_flight_available' => collect($metricRows)->where('group', 'ball_flight')->contains('available', true),
            'insights' => $this->insights($metricRows),
            '_scope' => ['team_id' => $batch->team_id, 'player_id' => $batch->player_id],
        ];
    }

    private function bestSwing(array $events): array
    {
        $scores = ['hitting.blast_plane_score', 'hitting.blast_connection_score', 'hitting.blast_rotation_score'];
        usort($events, function (array $a, array $b) use ($scores): int {
            $score = fn (array $event): float => collect($scores)->filter(fn ($key) => isset($event['metrics'][$key]))->avg(fn ($key) => $event['metrics'][$key]) ?? -INF;
            return [$score($b), $b['metrics']['hitting.bat_speed'] ?? -INF, -($b['metrics']['hitting.time_to_contact'] ?? INF), -$b['order']]
                <=> [$score($a), $a['metrics']['hitting.bat_speed'] ?? -INF, -($a['metrics']['hitting.time_to_contact'] ?? INF), -$a['order']];
        });
        return $events[0];
    }

    private function benchmark(string $key, float $value, string $level): array
    {
        if (in_array($key, ['plane_score', 'connection_score', 'rotation_score'], true)) {
            foreach (config('blast_benchmarks.scouting_scale') as $band) if ($value >= $band['min'] && (null === $band['max'] || $value <= $band['max'])) return ['type' => 'scouting_scale', 'source' => 'Blast 20–80 scouting scale', 'range_label' => null === $band['max'] ? '80+' : floor($band['min']).'–'.floor($band['max']), 'status' => $band['key'], 'label' => $band['label']];
        }
        $range = config("blast_benchmarks.levels.{$level}.metrics.{$key}");
        if ($range) return ['type' => 'competition_level', 'source' => config("blast_benchmarks.levels.{$level}.label"), 'range_label' => $range['min'].'–'.$range['max'].' '.$range['unit']] + $this->comparator->compare($value, $range);
        $reference = config("blast_benchmarks.mlb_references.{$key}");
        if ($reference) { $delta = round($value - $reference['value'], 1); return ['type' => 'reference', 'source' => 'Blast MLB reference', 'range_label' => $reference['value'].' '.$reference['unit'], 'status' => 'reference_only', 'label' => abs($delta).' '.($reference['unit'] === '%' ? 'percentage points' : $reference['unit']).' '.($delta < 0 ? 'below' : 'above').' reference']; }
        return ['type' => 'none', 'source' => 'No age-level benchmark available', 'range_label' => null, 'status' => 'unavailable', 'label' => 'No benchmark available'];
    }

    private function unavailableBenchmark(): array { return ['type' => 'unavailable', 'source' => null, 'range_label' => null, 'status' => 'unavailable', 'label' => 'Not captured']; }
    private function insights(array $metrics): array
    {
        $byKey = collect($metrics)->keyBy('key');
        return [
            ['metric' => 'Plane Score', 'current' => $byKey['plane_score']['average'], 'classification' => $byKey['plane_score']['benchmark']['label'], 'direction' => 'Improve plane score and on-plane consistency.'],
            ['metric' => 'Connection Score', 'current' => $byKey['connection_score']['average'], 'classification' => $byKey['connection_score']['benchmark']['label'], 'direction' => 'Improve connection while preserving current bat-speed strength.'],
            ['metric' => 'Swing Sequencing', 'current' => $byKey['time_to_contact']['average'], 'classification' => $byKey['time_to_contact']['benchmark']['label'], 'direction' => 'Maintain bat speed and power while improving swing sequencing.'],
        ];
    }
}
