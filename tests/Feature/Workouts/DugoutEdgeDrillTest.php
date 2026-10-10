<?php

namespace Tests\Feature\Workouts;

use Tests\TestCase;
use App\Models\{User, PlannerCustomDrill};
use Database\Seeders\DugoutEdgeDrillSeeder;
use Laravel\Sanctum\Sanctum;

class DugoutEdgeDrillTest extends TestCase
{
    public function test_import_is_complete_repeatable_and_visible_in_workout_picker(): void
    {
        config(['access.temporary_full_access.enabled' => true]);
        Sanctum::actingAs(User::factory()->create(['type' => 'coach']), ['coach']);
        $this->seed(DugoutEdgeDrillSeeder::class);
        $rows = PlannerCustomDrill::where('source', 'dugout_edge')->get();
        $this->assertCount(271, $rows->pluck('data.sourceUrl')->unique());
        $source = json_decode(file_get_contents(database_path('data/dugout-edge-drills.json')), true);
        foreach ($source['drills'] as $drill) {
            $saved = $rows->first(fn ($r) => $r->data['sourceUrl'] === $drill['url']);
            $this->assertNotNull($saved, $drill['name']);
            $this->assertSame($drill['description'], $saved->data['description']);
            $this->assertSame($drill['duration_minutes'] * 60, $saved->data['defaultDurationSec']);
            $this->assertSame($drill['sport_label'], $saved->data['sport']);
        }
        $edited = $rows->first();
        $edited->update(['data' => array_merge($edited->data, ['description' => 'Retain correction'])]);
        $this->seed(DugoutEdgeDrillSeeder::class);
        $this->assertSame($rows->count(), PlannerCustomDrill::where('source', 'dugout_edge')->count());
        $this->assertSame('Retain correction', $edited->fresh()->data['description']);
        $response = $this->getJson('/api/coach/drills')->assertOk()->json('data');
        $this->assertCount($rows->count(), array_filter($response, fn ($d) => $d['source'] === 'dugout_edge'));
        foreach (['throwing', 'pitching'] as $bucket) {
            $this->assertTrue($rows->contains(fn ($r) => $r->name === 'Long Toss' && $r->bucket === $bucket));
        }
        foreach (['strength_accessory', 'arm_care'] as $bucket) {
            $this->assertTrue($rows->contains(fn ($r) => $r->name === 'Dumbbell External Rotation' && $r->bucket === $bucket));
        }
    }
}
