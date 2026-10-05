<?php

declare(strict_types=1);

namespace App\Services\FreeAssessment;

use App\Models\FreeAssessment;

class TeamAssessmentReportService
{
    public function build(FreeAssessment $event): array
    {
        $snapshot = app(AssessmentReportService::class)->snapshot($event);
        $players = $snapshot['players']->keyBy('id');
        $results = $snapshot['results']->filter(fn ($r) => $players->has($r['player_id']));
        $rows = [];
        foreach (Stations::all() as $station => $definition) {
            $stationResults = $results->where('station', $station);
            $groups = in_array($station, ['pitching_velocity', 'pull_strength'], true)
                ? $stationResults->groupBy(fn ($r) => $r['protocol'] ?? 'Unspecified')
                : collect(['' => $stationResults]);
            if ($groups->isEmpty()) $groups = collect(['' => collect()]);
            foreach ($groups as $protocol => $group) {
                foreach ($station === 'grip_strength' ? ['left', 'right'] : [null] as $side) {
                    $values = $group->map(function ($r) use ($side, $players) {
                        $summary = $side ? ($r['summary'][$side] ?? []) : $r['summary'];
                        return ['player_id' => $r['player_id'], 'name' => $players[$r['player_id']]['name'], 'best' => $summary['best'] ?? null, 'average' => $summary['average'] ?? null];
                    })->filter(fn ($r) => is_numeric($r['best']))->values();
                    $lower = $definition['lower'] ?? false;
                    $sorted = $values->sortBy(fn ($r) => $lower ? (float) $r['best'] : -(float) $r['best'])->values();
                    $leaders = $sorted->map(function ($r) use ($values, $lower) {
                        $rank = 1 + $values->filter(fn ($other) => $lower ? $other['best'] < $r['best'] : $other['best'] > $r['best'])->count();
                        return $r + ['rank' => $rank];
                    })->filter(fn ($r) => $r['rank'] <= 3)->values();
                    $rows[] = [
                        'station' => $station, 'name' => $definition['name'].($side ? ' — '.ucfirst($side) : ''),
                        'side' => $side, 'protocol' => $protocol ?: null, 'unit' => $definition['unit'],
                        'lower_is_better' => $lower, 'tested' => $values->count(), 'total_players' => $players->count(),
                        'average_best' => $values->isEmpty() ? null : round((float) $values->avg('best'), 3),
                        'average_attempts' => $values->whereNotNull('average')->isEmpty() ? null : round((float) $values->avg('average'), 3),
                        'best' => $sorted->first()['best'] ?? null, 'leaders' => $leaders,
                    ];
                }
            }
        }
        $complete = $players->filter(fn ($p) => $results->where('player_id', $p['id'])->count() === count(Stations::all()))->count();
        return [
            'assessment' => ['id' => $event->id, 'name' => $event->name, 'assessment_date' => $event->assessment_date->toDateString(), 'location' => $event->location, 'team_name' => $event->team?->name],
            'total_players' => $players->count(), 'complete_players' => $complete,
            'average_stations_completed' => $players->isEmpty() ? 0 : round($results->count() / $players->count(), 2),
            'total_stations' => count(Stations::all()), 'stations' => $rows,
        ];
    }

    public function csv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Station', 'Protocol', 'Unit', 'Tested', 'Enrolled', 'Team average of player bests', 'Team average of player attempt averages', 'Best result', 'Top rank', 'Player', 'Player result']);
        foreach ($report['stations'] as $station) {
            $leaders = collect($station['leaders']);
            if ($leaders->isEmpty()) $leaders = collect([[]]);
            foreach ($leaders as $leader) {
                $row = [$station['name'], $station['protocol'], $station['unit'], $station['tested'], $station['total_players'], $station['average_best'], $station['average_attempts'], $station['best'], $leader['rank'] ?? null, $leader['name'] ?? null, $leader['best'] ?? null];
                fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+@\-]/u', $value) ? "'".$value : $value, $row));
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }
}
