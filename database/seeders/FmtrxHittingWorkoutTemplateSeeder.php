<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\{PlannerCustomDrill, WorkoutTemplate};
use App\Services\Workouts\WorkoutTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FmtrxHittingWorkoutTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $library = json_decode(file_get_contents(database_path('data/fmtrx-hitting-workouts.json')), true, 512, JSON_THROW_ON_ERROR);
        $service = app(WorkoutTemplateService::class);

        DB::transaction(function () use ($library, $service): void {
            foreach ($library['templates'] as $source) {
                // Stable slugs make repeat deployments safe; preserve existing masters and copies.
                if (WorkoutTemplate::where('slug', $source['slug'])->lockForUpdate()->exists()) {
                    continue;
                }
                $data = collect($source)->only(['slug', 'name', 'sport', 'category', 'program_type', 'intensity_label'])->all();
                $data['description'] = $source['focus']."\n\n".$source['instructions'];
                $data['sections'] = [];
                foreach (collect($source['sections'])->sortBy('sort_order') as $section) {
                    $type = ['Warm-Up' => 'movement_prep', 'Hitting' => 'hitting', 'Recovery' => 'recovery'][$section['name']];
                    $exercises = [];
                    foreach ($section['exercises'] as $exercise) {
                        // Link only an unambiguous public library match, never another coach's private drill.
                        $matches = PlannerCustomDrill::where('visibility', 'public')
                            ->whereNull('team_id')->where('bucket', $type)
                            ->where('name', $exercise['exercise_name'])->get();
                        $row = [
                            'exercise_name' => $exercise['exercise_name'],
                            'prescription_text' => $exercise['prescription_text'],
                            'sets_min' => $exercise['sets'],
                            'sets_max' => $exercise['sets'],
                            'exercise_id' => $matches->count() === 1 ? $matches->first()->id : null,
                            'metadata' => isset($exercise['track_metric']) ? ['track_metric' => $exercise['track_metric']] : [],
                        ];
                        // Preserve ambiguous swing/pitch/per-side quantities as supplied text.
                        if (str_starts_with($exercise['prescription_text'], '5 minutes;')) {
                            $row['duration_seconds'] = 300;
                        }
                        $exercises[] = $row;
                    }
                    $data['sections'][] = [
                        'name' => $section['name'], 'section_type' => $type,
                        'instructions' => $type === 'hitting' ? $source['instructions'] : null,
                        'exercises' => $exercises,
                    ];
                }
                $template = $service->write($service->validate($data));
                $template->update(['is_premade' => true, 'is_public' => true]);
            }
        });
    }
}
