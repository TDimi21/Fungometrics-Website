<?php

declare(strict_types=1);

namespace App\Services\Blast;

use Illuminate\Support\Facades\DB;

final class BlastPlayerMetricService
{
    /** @return array<string, mixed>|null */
    public function latestBatSpeedSession(string $playerId, ?string $teamId = null): ?array
    {
        $session = DB::table('external_sessions as session')
            ->join('import_batches as batch', 'batch.id', '=', 'session.import_batch_id')
            ->join('platform_definitions as platform', 'platform.id', '=', 'session.platform_definition_id')
            ->where('session.player_id', $playerId)
            ->when($teamId, fn ($query) => $query->where('session.team_id', $teamId))
            ->where('platform.key', 'blast-motion')
            ->where('batch.status', 'completed')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('canonical_events as bat_speed_event')
                    ->join('canonical_metrics as bat_speed_metric', 'bat_speed_metric.canonical_event_id', '=', 'bat_speed_event.id')
                    ->join('baseball_concepts as bat_speed_concept', 'bat_speed_concept.id', '=', 'bat_speed_metric.baseball_concept_id')
                    ->whereColumn('bat_speed_event.external_session_id', 'session.id')
                    ->where('bat_speed_concept.canonical_key', 'hitting.bat_speed')
                    ->whereNotNull('bat_speed_metric.numeric_value')
                    ->where('bat_speed_metric.numeric_value', '>', 0);
            })
            ->orderByRaw('COALESCE(session.occurred_at, batch.completed_at, session.created_at) DESC')
            ->orderByDesc('session.created_at')
            ->first(['session.id', 'session.occurred_at']);

        if ( ! $session) {
            return null;
        }

        $values = DB::table('canonical_events as event')
            ->join('canonical_metrics as metric', 'metric.canonical_event_id', '=', 'event.id')
            ->join('baseball_concepts as concept', 'concept.id', '=', 'metric.baseball_concept_id')
            ->where('event.external_session_id', $session->id)
            ->where('concept.canonical_key', 'hitting.bat_speed')
            ->whereNotNull('metric.numeric_value')
            ->pluck('metric.numeric_value')
            ->map(fn ($value): float => (float) $value)
            ->filter(fn (float $value): bool => $value > 0)
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return [
            'session_id' => (string) $session->id,
            'occurred_at' => $session->occurred_at,
            'average' => round((float) $values->avg(), 1),
            'best' => round((float) $values->max(), 1),
            'swing_count' => $values->count(),
            'source' => 'blast_hub_latest_completed_session',
        ];
    }
}
