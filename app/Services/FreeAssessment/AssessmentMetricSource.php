<?php

declare(strict_types=1);

namespace App\Services\FreeAssessment;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Schema};

// Read only finalized revisions that have a canonical fitness snapshot. Attempts
// remain event evidence; they never become synthetic bullpen/game pitch records.
class AssessmentMetricSource
{
    public function velocities(string $station, ?string $teamId, ?string $playerId, $since, bool $fastballOnly = false): Collection
    {
        if ( ! Schema::hasTable('free_assessment_results')) {
            return collect();
        }
        return DB::table('free_assessment_attempts as a')
            ->join('free_assessment_results as r', function ($join): void { $join->on('r.id', '=', 'a.result_id')->on('r.revision', '=', 'a.revision'); })
            ->join('free_assessments as e', 'e.id', '=', 'r.assessment_id')
            ->join('player_fitnesses as f', function ($join): void { $join->on('f.free_assessment_id', '=', 'e.id')->on('f.user_id', '=', 'r.player_id'); })
            ->whereNull('f.deleted_at')->where('r.station', $station)->whereDate('e.assessment_date', '>=', $since)
            ->when($teamId, fn ($q) => $q->where('e.team_id', $teamId))
            ->when($playerId, fn ($q) => $q->where('r.player_id', $playerId))
            ->when($fastballOnly, fn ($q) => $q->where('r.protocol', 'fastball'))
            ->get(['r.player_id as user_id', 'a.value', 'r.protocol', 'e.assessment_date'])
            ->map(fn ($r) => (object) ['user_id' => $r->user_id, 'value' => (float) $r->value, 'velocity' => (float) $r->value, 'miles_per_hour' => (float) $r->value, 'type_throw' => 'fastball' === $r->protocol ? 'FB' : null, 'created_at' => Carbon::parse($r->assessment_date)]);
    }
}
