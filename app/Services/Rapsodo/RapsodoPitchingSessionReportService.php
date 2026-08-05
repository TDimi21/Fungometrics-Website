<?php

declare(strict_types=1);

namespace App\Services\Rapsodo;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RapsodoPitchingSessionReportService
{
    private const PITCH_ORDER = ['FB' => 1, '2FB' => 2, 'CV' => 3, 'SL' => 4, 'KN' => 5];

    private const METRICS = [
        'pitch_type' => 'pitching.tagged_pitch_type',
        'velocity' => 'pitching.release_velocity',
        'spin_rate' => 'pitching.spin_rate',
        'true_spin' => 'pitching.true_spin_rate',
        'spin_efficiency' => 'pitching.spin_efficiency',
        'spin_direction' => 'pitching.spin_direction_clock',
        'horizontal_break' => 'pitching.horizontal_break',
        'vertical_break' => 'pitching.vertical_break',
        'release_height' => 'pitching.release_height',
        'release_side' => 'pitching.release_side',
        'strike' => 'pitching.strike_result',
    ];

    public function __construct(private readonly RapsodoTiltAverager $tilts)
    {
    }

    /** @return array{batch_id:string,team_id:string,player_id:string,platform:string,status:string} */
    public function scope(string $batchId, ?string $requestedPlayerId = null): array
    {
        $batch = DB::table('import_batches as batch')
            ->join('translation_snapshots as snapshot', 'snapshot.id', '=', 'batch.translation_snapshot_id')
            ->join('platform_definitions as platform', 'platform.id', '=', 'snapshot.platform_definition_id')
            ->where('batch.id', $batchId)
            ->first(['batch.id', 'batch.status', 'snapshot.team_id', 'platform.key as platform']);
        if (!$batch) {
            throw new RapsodoReportException('report_not_found', 'The requested Rapsodo report was not found.', 404);
        }
        if ('rapsodo' !== $batch->platform) {
            throw new RapsodoReportException('wrong_platform', 'This Import Batch is not a Rapsodo import.');
        }
        if ('completed' !== $batch->status) {
            throw new RapsodoReportException('import_not_completed', 'The Rapsodo Import Batch is not complete.');
        }

        $players = DB::table('external_sessions')
            ->where('import_batch_id', $batchId)
            ->whereNotNull('player_id')
            ->distinct()
            ->pluck('player_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
        if ([] === $players) {
            throw new RapsodoReportException('player_mapping_required', 'An approved Player Mapping is required for this report.');
        }
        if (count($players) > 1 && null === $requestedPlayerId) {
            throw new RapsodoReportException('player_selection_required', 'Select a mapped player from this Import Batch.');
        }
        $playerId = $requestedPlayerId ?? $players[0];
        if (!in_array($playerId, $players, true)) {
            throw new RapsodoReportException('player_not_in_batch', 'The selected player is not mapped to this Import Batch.');
        }

        return [
            'batch_id' => (string) $batch->id,
            'team_id' => (string) $batch->team_id,
            'player_id' => $playerId,
            'platform' => (string) $batch->platform,
            'status' => (string) $batch->status,
        ];
    }

    /** @return array<string, mixed> */
    public function report(string $batchId, string $playerId): array
    {
        $scope = $this->scope($batchId, $playerId);
        $player = DB::table('users as user')
            ->leftJoin('profiles as profile', 'profile.user_id', '=', 'user.id')
            ->leftJoin('players as player', 'player.user_id', '=', 'user.id')
            ->leftJoin('player_teams as membership', function ($join) use ($scope): void {
                $join->on('membership.user_id', '=', 'user.id')
                    ->where('membership.team_id', '=', $scope['team_id'])
                    ->whereNull('membership.deleted_at');
            })
            ->leftJoin('teams as team', 'team.id', '=', 'membership.team_id')
            ->where('user.id', $playerId)
            ->first(['user.id', 'profile.first_name', 'profile.last_name', 'player.throw_side', 'team.name as team_name']);

        $sessionRows = DB::table('external_sessions')
            ->where('import_batch_id', $batchId)
            ->where('player_id', $playerId)
            ->orderBy('occurred_at')
            ->get(['id', 'occurred_at', 'label', 'metadata']);
        $sessionIds = $sessionRows->pluck('id')->all();
        $metricRows = DB::table('canonical_events as event')
            ->leftJoin('canonical_metrics as metric', 'metric.canonical_event_id', '=', 'event.id')
            ->leftJoin('baseball_concepts as concept', 'concept.id', '=', 'metric.baseball_concept_id')
            ->whereIn('event.external_session_id', $sessionIds)
            ->where('event.player_id', $playerId)
            ->orderBy('event.occurred_at')
            ->orderBy('event.event_order')
            ->get([
                'event.id', 'event.event_order', 'event.occurred_at', 'event.source_context',
                'concept.canonical_key', 'metric.value', 'metric.numeric_value',
            ]);
        $events = $this->events($metricRows);
        if ([] === $events) {
            throw new RapsodoReportException('no_valid_pitches', 'This Rapsodo report contains no valid pitches.', 404);
        }

        $pitchTypes = $this->pitchTypes($events);
        $timestamps = array_values(array_filter(array_column($events, 'occurred_at')));
        $start = [] === $timestamps ? null : min($timestamps);
        $end = [] === $timestamps ? null : max($timestamps);
        $velocities = $this->numbers($events, 'velocity');
        $spinRates = $this->numbers($events, 'spin_rate');
        $strikeValues = array_values(array_filter(array_column($events, 'strike'), fn ($value): bool => null !== $value));

        return [
            'player' => [
                'id' => $playerId,
                'name' => trim((string) ($player->first_name ?? '').' '.(string) ($player->last_name ?? '')) ?: 'FMTRX Player',
                'throws' => $this->throwSide($player->throw_side ?? null),
                'team' => $player->team_name ?? null,
            ],
            'session' => [
                'import_batch_id' => $batchId,
                'platform' => 'rapsodo',
                'date' => $this->date($start ?? ($sessionRows->first()->occurred_at ?? null)),
                'total_pitches' => count($events),
                'start_time' => $this->time($start),
                'end_time' => $this->time($end),
                'duration_minutes' => $this->duration($start, $end),
            ],
            'summary' => [
                'average_velocity' => $this->average($velocities, 1),
                'maximum_velocity' => [] === $velocities ? null : round(max($velocities), 1),
                'strike_percentage' => $this->percentage($strikeValues, 1),
                'average_spin_rate' => $this->average($spinRates, 1),
                'pitch_type_count' => count($pitchTypes),
            ],
            'pitch_types' => $pitchTypes,
            'movement_points' => array_values(array_filter(array_map(fn (array $event): ?array =>
                null === $event['horizontal_break'] || null === $event['vertical_break'] ? null : [
                    'event_id' => $event['id'], 'pitch_number' => $event['order'], 'pitch_type' => $event['pitch_type'],
                    'horizontal_break' => $event['horizontal_break'], 'vertical_break' => $event['vertical_break'],
                ], $events))),
            'release_points' => array_values(array_filter(array_map(fn (array $event): ?array =>
                null === $event['release_side'] || null === $event['release_height'] ? null : [
                    'event_id' => $event['id'], 'pitch_number' => $event['order'], 'pitch_type' => $event['pitch_type'],
                    'release_side' => $event['release_side'], 'release_height' => $event['release_height'],
                ], $events))),
            'insights' => $this->insights($pitchTypes, $this->percentage($strikeValues, 1)),
            'availability' => [
                'pitch_location' => false,
                'batter_context' => false,
                'pitch_outcome' => false,
                'external_benchmark' => false,
            ],
            'notes' => [
                'Strike percentage is the Rapsodo source Y/N classification.',
                'Movement and release values are descriptive session measurements, not grades.',
                'Pitch location, batter context, and pitch outcomes were not available in this source.',
            ],
            '_scope' => $scope,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function events(Collection $rows): array
    {
        return $rows->groupBy('id')->map(function (Collection $items): ?array {
            $first = $items->first();
            $context = json_decode((string) $first->source_context, true) ?: [];
            $metrics = $items->filter(fn ($item): bool => null !== $item->canonical_key)
                ->mapWithKeys(fn ($item): array => [(string) $item->canonical_key => [
                    'value' => $item->value,
                    'number' => null === $item->numeric_value ? null : (float) $item->numeric_value,
                ]])->all();
            $text = fn (string $name, array $fallbacks): ?string => $this->textMetric($metrics, self::METRICS[$name])
                ?? $this->contextText($context, $fallbacks);
            $number = fn (string $name, array $fallbacks): ?float => $this->numberMetric($metrics, self::METRICS[$name])
                ?? $this->contextNumber($context, $fallbacks);
            $pitchType = $text('pitch_type', ['pitch_type', 'tagged_pitch_type', 'pitch_type_canonical']);
            if (null === $pitchType) {
                return null;
            }

            return [
                'id' => (string) $first->id,
                'order' => (int) $first->event_order,
                'occurred_at' => $first->occurred_at,
                'pitch_type' => mb_strtoupper(trim($pitchType)),
                'velocity' => $number('velocity', ['velocity', 'pitch_velocity_mph']),
                'spin_rate' => $number('spin_rate', ['spin_rate', 'total_spin_rate_rpm']),
                'true_spin' => $number('true_spin', ['true_spin', 'true_spin_rate_rpm']),
                'spin_efficiency' => $number('spin_efficiency', ['spin_efficiency', 'spin_efficiency_percent']),
                'spin_direction' => $text('spin_direction', ['spin_direction', 'spin_direction_clock']),
                'horizontal_break' => $number('horizontal_break', ['horizontal_break', 'horizontal_break_in']),
                'vertical_break' => $number('vertical_break', ['vertical_break', 'vertical_break_in']),
                'release_height' => $number('release_height', ['release_height', 'release_height_ft']),
                'release_side' => $number('release_side', ['release_side', 'release_side_ft']),
                'strike' => $this->strike($this->metricValue($metrics, self::METRICS['strike']) ?? ($context['strike'] ?? $context['strike_boolean'] ?? null)),
            ];
        })->filter()->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function pitchTypes(array $events): array
    {
        $total = count($events);
        $groups = collect($events)->groupBy('pitch_type')->map(function (Collection $items, string $label) use ($total): array {
            $rows = $items->values()->all();
            $velocities = $this->numbers($rows, 'velocity');
            $strikes = array_values(array_filter(array_column($rows, 'strike'), fn ($value): bool => null !== $value));
            $horizontal = $this->numbers($rows, 'horizontal_break');
            $vertical = $this->numbers($rows, 'vertical_break');

            return [
                'pitch_type' => $label,
                'display_name' => $this->pitchName($label),
                'count' => count($rows),
                'usage_percentage' => round((count($rows) / $total) * 100, 1),
                'minimum_velocity' => [] === $velocities ? null : round(min($velocities), 1),
                'average_velocity' => $this->average($velocities, 1),
                'maximum_velocity' => [] === $velocities ? null : round(max($velocities), 1),
                'average_spin_rate' => $this->average($this->numbers($rows, 'spin_rate'), 0),
                'average_true_spin' => $this->average($this->numbers($rows, 'true_spin'), 0),
                'average_spin_efficiency' => $this->average($this->numbers($rows, 'spin_efficiency'), 1),
                'average_horizontal_break' => $this->average($horizontal, 1),
                'average_vertical_break' => $this->average($vertical, 1),
                'average_release_height' => $this->average($this->numbers($rows, 'release_height'), 2),
                'average_release_side' => $this->average($this->numbers($rows, 'release_side'), 2),
                'strike_percentage' => $this->percentage($strikes, 1),
                'average_tilt' => $this->tilts->average(array_values(array_filter(array_column($rows, 'spin_direction'), fn ($value): bool => null !== $value && '' !== $value))),
                'centroid' => [
                    'horizontal_break' => $this->average($horizontal, 1),
                    'vertical_break' => $this->average($vertical, 1),
                ],
            ];
        })->values()->all();
        usort($groups, fn (array $a, array $b): int => [self::PITCH_ORDER[$a['pitch_type']] ?? 999, $a['pitch_type']] <=> [self::PITCH_ORDER[$b['pitch_type']] ?? 999, $b['pitch_type']]);

        return $groups;
    }

    /** @return array<int, array{title:string,body:string}> */
    private function insights(array $pitchTypes, ?float $strikePercentage): array
    {
        $byType = collect($pitchTypes)->keyBy('pitch_type');
        $insights = [];
        if ($byType->has('FB') && $byType->has('2FB')) {
            $fb = $byType['FB'];
            $two = $byType['2FB'];
            $insights[] = ['title' => 'Fastball separation', 'body' => sprintf(
                'In this session, the four-seam fastball averaged %.1f mph with %.1f inches of vertical break. The two-seam fastball averaged %.1f mph with more arm-side horizontal movement and less vertical break.',
                $fb['average_velocity'], $fb['average_vertical_break'], $two['average_velocity']
            )];
        }
        if ($byType->has('CV') && $byType->has('SL')) {
            $cv = $byType['CV'];
            $sl = $byType['SL'];
            $insights[] = ['title' => 'Breaking-ball identity', 'body' => sprintf(
                'Descriptive comparison: the curveball averaged %.0f rpm of total spin with %.1f inches of vertical break. The slider averaged %.1f%% spin efficiency with a %.1f-inch vertical-break profile.',
                $cv['average_spin_rate'], $cv['average_vertical_break'], $sl['average_spin_efficiency'], $sl['average_vertical_break']
            )];
        }
        $available = array_values(array_filter($pitchTypes, fn (array $row): bool => null !== $row['strike_percentage']));
        if (null !== $strikePercentage && [] !== $available) {
            usort($available, fn (array $a, array $b): int => [$b['strike_percentage'], $a['pitch_type']] <=> [$a['strike_percentage'], $b['pitch_type']]);
            $highest = $available[0];
            $lowest = $available[count($available) - 1];
            $insights[] = ['title' => 'Strike execution priority', 'body' => sprintf(
                'The source recorded %.1f%% of pitches as strikes. %s had the highest Rapsodo source strike percentage in this session, while %s had the lowest.',
                $strikePercentage, $highest['display_name'], $lowest['display_name']
            )];
        }

        return array_slice($insights, 0, 3);
    }

    /** @return array<int, float> */
    private function numbers(array $events, string $key): array
    {
        return array_values(array_map('floatval', array_filter(array_column($events, $key), fn ($value): bool => null !== $value && is_numeric($value))));
    }

    private function average(array $values, int $precision): ?float
    {
        return [] === $values ? null : round(array_sum($values) / count($values), $precision);
    }

    private function percentage(array $values, int $precision): ?float
    {
        return [] === $values ? null : round((count(array_filter($values, fn ($value): bool => true === $value)) / count($values)) * 100, $precision);
    }

    private function metricValue(array $metrics, string $key): mixed
    {
        return $metrics[$key]['value'] ?? null;
    }

    private function textMetric(array $metrics, string $key): ?string
    {
        $value = $this->metricValue($metrics, $key);
        return null === $value || '' === trim((string) $value) ? null : trim((string) $value);
    }

    private function numberMetric(array $metrics, string $key): ?float
    {
        $value = $metrics[$key]['number'] ?? null;
        if (null !== $value) {
            return (float) $value;
        }
        $raw = $this->metricValue($metrics, $key);
        return is_numeric($raw) ? (float) $raw : null;
    }

    private function contextText(array $context, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($context[$key]) && '' !== trim((string) $context[$key])) {
                return trim((string) $context[$key]);
            }
        }
        return null;
    }

    private function contextNumber(array $context, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $context) && is_numeric($context[$key])) {
                return (float) $context[$key];
            }
        }
        return null;
    }

    private function strike(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return match (mb_strtolower(trim((string) $value))) {
            'y', 'yes', 'true', '1' => true,
            'n', 'no', 'false', '0' => false,
            default => null,
        };
    }

    private function date(?string $value): ?string
    {
        $timestamp = null === $value ? false : strtotime($value);
        return false === $timestamp ? null : date('Y-m-d', $timestamp);
    }

    private function time(?string $value): ?string
    {
        $timestamp = null === $value ? false : strtotime($value);
        return false === $timestamp ? null : date('g:i A', $timestamp);
    }

    private function duration(?string $start, ?string $end): ?float
    {
        $startAt = null === $start ? false : strtotime($start);
        $endAt = null === $end ? false : strtotime($end);
        return false === $startAt || false === $endAt ? null : round(max(0, $endAt - $startAt) / 60, 1);
    }

    private function throwSide(mixed $value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'r', 'right' => 'right',
            'l', 'left' => 'left',
            's', 'switch' => 'switch',
            default => null,
        };
    }

    private function pitchName(string $label): string
    {
        return match ($label) {
            'FB' => 'Four-Seam Fastball',
            '2FB' => 'Two-Seam Fastball',
            'CV' => 'Curveball',
            'SL' => 'Slider',
            'KN' => 'Knuckleball',
            default => $label,
        };
    }
}
