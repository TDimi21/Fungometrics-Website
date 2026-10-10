<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PlannerCustomDrill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DugoutEdgeDrillSeeder extends Seeder
{
    public function run(): void
    {
        $library = json_decode(file_get_contents(database_path('data/dugout-edge-drills.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($library): void {
            foreach ($library['drills'] as $drill) {
                foreach ($this->placements($drill) as $bucket => $categories) {
                    // One stable record per source drill and workout section, even on repeat imports.
                    $id = 'dugout-'.substr(hash('sha256', $drill['url'].'|'.$bucket), 0, 24);
                    PlannerCustomDrill::firstOrCreate(['id' => $id], [
                        'created_by' => null, 'team_id' => null, 'visibility' => 'public',
                        'source' => 'dugout_edge', 'name' => $drill['name'], 'bucket' => $bucket,
                        'category_group' => implode(' / ', array_unique($categories)),
                        'data' => [
                            'description' => $drill['description'],
                            'defaultDurationSec' => $drill['duration_minutes'] * 60,
                            'workloadType' => 'time',
                            'level' => $drill['level'], 'sport' => $drill['sport_label'],
                            'sourceUrl' => $drill['url'], 'sourceName' => $drill['source'],
                            'retrieved' => $drill['retrieved'],
                            'tags' => array_merge($drill['categories'], [$drill['level'], $drill['sport_label']]),
                        ],
                    ]);
                }
            }
        });
    }

    private function placements(array $drill): array
    {
        $mapping = [
            'Hitting Drills' => ['hitting'], 'Throwing' => ['throwing'],
            'Pitching Drills' => ['pitching'], 'Warmup' => ['movement_prep'],
            'Infield Drills' => ['defense'], 'Outfield Drills' => ['defense'],
            'Catching Drills' => ['defense'], 'Team Defense Drills' => ['defense'],
            'Baserunning Drills' => ['speed_agility'], 'Mental Skills' => ['education'],
        ];
        $placements = [];
        foreach ($drill['categories'] as $category) {
            if ($category === 'Fun & Games') continue; // Use its skill category, not an unrelated training section.
            $buckets = $category === 'Strength & Conditioning' ? $this->strengthBuckets($drill['name']) : ($mapping[$category] ?? []);
            foreach ($buckets as $bucket) $placements[$bucket][] = $category;
        }
        if (!$placements) {
            // The three games without a skill category are batting games / scrimmages.
            $placements['hitting'] = ['Fun & Games'];
            if ($drill['name'] !== 'Water Balloon Derby') $placements['defense'] = ['Fun & Games'];
        }
        return $placements;
    }

    private function strengthBuckets(string $name): array
    {
        if (in_array($name, ['Chaos External Rotation', 'Drop External Rotation', 'Dumbbell External Rotation', 'Forearm Roller', 'Incline Y Raises', 'Meadows Swings', 'Zottman Curls'], true)) {
            return ['strength_accessory', 'arm_care'];
        }
        if (in_array($name, ['Ab Wheel Rollout', 'GHD Rotational Back Extension', 'V Up Throw'], true)) return ['strength_accessory'];
        if (in_array($name, ['Power Snatch', 'Zombie Squats'], true)) return ['strength_primary'];
        if ($name === 'Single Leg Squat') return ['strength_secondary'];
        if (str_contains($name, 'Medicine Ball')) return ['strength_accessory'];
        if (in_array($name, ['Crouch Walks', 'Base Running Circuit', 'Base Running Relay Race', 'Pole Sprints', 'Rock Paper Scissors Base Running'], true)) return ['conditioning'];
        if (str_contains($name, 'Speed')) return ['speed_agility', 'conditioning', 'strength_secondary'];
        // Multi-exercise strength workouts can be used in either main or secondary strength.
        return ['strength_primary', 'strength_secondary'];
    }
}
