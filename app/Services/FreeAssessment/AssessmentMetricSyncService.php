<?php

declare(strict_types=1);

namespace App\Services\FreeAssessment;

use App\Models\{FreeAssessment, FreeAssessmentResult, PlayerFitness};
use Illuminate\Support\Facades\Cache;

class AssessmentMetricSyncService
{
    // Caller locks the event enrollment. One canonical snapshot per event/player;
    // fields owned by other stations and unrelated daily workouts are never replaced.
    public function sync(FreeAssessment $event, FreeAssessmentResult $result): void
    {
        $fitness = PlayerFitness::firstOrNew(['free_assessment_id' => $event->id, 'user_id' => $result->player_id]);
        if ( ! $fitness->exists) {
            $fitness->body_weight = PlayerFitness::where('user_id', $result->player_id)->whereNotNull('body_weight')->orderByDesc('fitness_date')->value('body_weight');
        }
        $fitness->fitness_date = $event->assessment_date;
        $station = Stations::all()[$result->station];
        if ('grip_strength' === $result->station) {
            $fitness->grip_strength_left = $result->summary['left']['best'];
            $fitness->grip_strength_right = $result->summary['right']['best'];
            $fitness->hand_strength = ($fitness->grip_strength_left + $fitness->grip_strength_right) / 2;
        } else {
            $fitness->{$station['field']} = $result->summary['best'];
        }
        $metadata = $fitness->strength_test_metadata ?? [];
        $metadata['source_type'] = 'free_assessment';
        $metadata['assessment_id'] = $event->id;
        $metadata['assessment_date'] = $event->assessment_date->toDateString();
        $metadata['stations'][$result->station] = [
            'result_id' => $result->id, 'revision' => $result->revision,
            'entered_by' => $result->entered_by, 'protocol' => $result->protocol,
            'unit' => $station['unit'], 'summary' => $result->summary,
        ];
        $fitness->strength_test_metadata = $metadata;
        $fitness->save();
        // Versioned population cache keys invalidate every context without a global flush.
        $invalidate = function () use ($result): void {
            Cache::forever('free_assessment_population_version', (string) \Illuminate\Support\Str::uuid());
            app(\App\Services\Development\PlayerDevelopmentDashboardCache::class)->forgetPlayer($result->player_id);
        };
        $invalidate();
        // A concurrent reader may have cached the pre-commit state; invalidate again
        // after commit so no such snapshot remains authoritative.
        \Illuminate\Support\Facades\DB::afterCommit($invalidate);
    }
}
