<?php

declare(strict_types=1);

namespace App\Services\FreeAssessment;

use App\Models\{FreeAssessment, User};
use App\Services\Intelligence\{PopulationPercentileEngine, ResearchPercentileEngine};
use Carbon\Carbon;

class AssessmentReportService
{
    public function player(User $user, ?string $on = null): array
    {
        $p = $user->player;
        return [
            'id' => $user->id, 'first_name' => $user->profile?->first_name, 'last_name' => $user->profile?->last_name,
            'name' => trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')),
            'age' => $p?->born_date ? Carbon::parse($p->born_date)->diffInYears($on ? Carbon::parse($on) : now()) : null,
            'born_date' => $p?->born_date, 'grad_year' => $p?->grad_year,
            'height' => null !== $p?->height_in_ft ? $p->height_in_ft.'′ '.($p->height_in_inch ?? 0).'″' : null,
            'weight' => $user->fitness?->body_weight, 'position' => $user->positions->pluck('position')->implode('/'),
            'bats' => $p?->hit_side, 'throws' => $p?->throw_side,
            'claimed' => filled($user->email) || filled($user->password),
        ];
    }
    public function snapshot(FreeAssessment $event): array
    {
        $players = $event->participants()->with(['profile', 'player', 'fitness', 'positions'])->get()->map(fn ($p) => $this->player($p, $event->assessment_date->toDateString()))->values();
        $results = $event->results()->with(['coach.profile', 'currentAttempts'])->orderByDesc('updated_at')->orderBy('id')->get()->map(function ($r) {
            return [
                'id' => $r->id, 'player_id' => $r->player_id, 'station' => $r->station, 'revision' => $r->revision,
                'summary' => $r->summary, 'notes' => $r->notes, 'protocol' => $r->protocol,
                'values' => $r->currentAttempts->sortBy(fn ($a) => $a->side.sprintf('%03d', $a->attempt_number))->pluck('value')->values()->all(),
                'updated_at' => $r->updated_at->toIso8601String(),
                'coach' => trim(($r->coach?->profile?->first_name ?? '').' '.($r->coach?->profile?->last_name ?? '')) ?: 'Coach',
            ];
        })->values();
        return ['assessment' => $event, 'stations' => Stations::all(), 'players' => $players, 'results' => $results];
    }
    public function rankings(FreeAssessment $event): array
    {
        $snapshot = $this->snapshot($event);
        $players = $snapshot['players']->keyBy('id');
        $results = $snapshot['results'];
        return $results->map(function ($r) use ($players, $results, $event) {
            $p = $players[$r['player_id']] ?? null;
            $def = Stations::all()[$r['station']];
            $value = 'grip_strength' === $r['station'] ? $r['summary']['left']['best'] : $r['summary']['best'];
            $peers = $results->where('station', $r['station']);
            if (in_array($r['station'], ['pull_strength', 'pitching_velocity'], true)) {
                $peers = $peers->where('protocol', $r['protocol']);
            }
            // Grip uses left-hand values consistently for both ranking and benchmarks.
            $peerValue = fn ($row) => 'grip_strength' === $r['station'] ? $row['summary']['left']['best'] : $row['summary']['best'];
            $rank = 1 + $peers->filter(fn ($other) => ($def['lower'] ?? false) ? $peerValue($other) < $value : $peerValue($other) > $value)->count();
            $context = ['team_id' => $event->team_id, 'age' => $p['age'], 'body_weight' => $p['weight'], 'position' => $p['position']];
            $supported = ! in_array($r['station'], ['shuttle_5_10_5', 'pull_strength'], true) && ! ('pitching_velocity' === $r['station'] && 'fastball' !== $r['protocol']);
            $supported = $supported && (app(\App\Services\Intelligence\PopulationValueGuardrail::class)->validate($def['metric'], $value)['included'] ?? false);
            $research = $supported ? app(ResearchPercentileEngine::class)->percentileForMetric($def['metric'], $value, null, $context) : [];
            $population = $supported ? app(PopulationPercentileEngine::class)->percentileFromRepository($def['metric'], $value, $context) : [];
            return $r + ['player' => $p, 'value' => $value, 'rank' => $rank, 'participants' => $peers->count(),
                'peer_percentile' => $research['percentile_estimate'] ?? null,
                'population_percentile' => $population['percentile'] ?? null,
                'confidence' => $population['confidence'] ?? 'unavailable',
                'peer_confidence' => $research['confidence'] ?? 'unavailable',
                'benchmark_note' => $supported ? null : 'No compatible benchmark or valid comparison range is available.',
            ];
        })->all();
    }
    public function csv(FreeAssessment $event, bool $includePhone = false): string
    {
        $snapshot = $this->snapshot($event);
        $rankings = collect($this->rankings($event));
        $stream = fopen('php://temp', 'r+');
        $headers = ['Player ID', 'First Name', 'Last Name', 'Age', 'Graduation Year', 'Height', 'Weight (lbs)', 'Position', 'Bats', 'Throws'];
        if ($includePhone) {
            $headers[] = 'Phone';
        }
        foreach (Stations::all() as $key => $station) {
            $prefix = $station['name'].' ('.$station['unit'].')';
            foreach ('grip_strength' === $key ? ['Left Best', 'Left Average', 'Right Best', 'Right Average'] : ['Best', 'Average'] as $label) {
                $headers[] = "{$prefix} {$label}";
            }
            foreach (['Assessment Rank', 'Age/Peer Percentile', 'Yard/FMTRX Percentile', 'Confidence', 'Protocol'] as $label) {
                $headers[] = $station['name'].' '.$label;
            }
        }
        fputcsv($stream, $headers);
        $phones = $includePhone ? $event->participants()->pluck('phone', 'users.id') : collect();
        foreach ($snapshot['players'] as $p) {
            $row = [$p['id'], $p['first_name'], $p['last_name'], $p['age'], $p['grad_year'], $p['height'], $p['weight'], $p['position'], $p['bats'], $p['throws']];
            if ($includePhone) {
                $row[] = $phones[$p['id']] ?? '';
            }
            foreach (Stations::all() as $key => $def) {
                $r = $rankings->first(fn ($r) => $r['player_id'] === $p['id'] && $r['station'] === $key);
                $s = $r['summary'] ?? [];
                foreach ('grip_strength' === $key ? [$s['left']['best'] ?? null, $s['left']['average'] ?? null, $s['right']['best'] ?? null, $s['right']['average'] ?? null] : [$s['best'] ?? null, $s['average'] ?? null] as $value) {
                    $row[] = $value;
                }
                foreach (['rank', 'peer_percentile', 'population_percentile', 'confidence', 'protocol'] as $field) {
                    $row[] = $r[$field] ?? null;
                }
            }
            // Prevent spreadsheet formula execution from names and other user-entered cells.
            fputcsv($stream, array_map(fn ($v) => is_string($v) && preg_match('/^[\s]*[=+@\-]/u', $v) ? "'".$v : $v, $row));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }
}
