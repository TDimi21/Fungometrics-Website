<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\{WorkoutTemplate,PlannerCustomDrill};
use App\Services\Workouts\WorkoutTemplateService;

class FlameBangersWorkoutTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach(json_decode(file_get_contents(database_path('data/flamebangers-workouts.json')), true, 512, JSON_THROW_ON_ERROR) as $data) {
                // Seed once: deployments must not overwrite customized or historical master versions.
                $existing=WorkoutTemplate::where('slug',$data['slug'])->lockForUpdate()->first();
                if($existing) {
                    if($existing->is_premade && $existing->version<2){
                        $metadata=collect($data['sections'])->flatMap(fn($section)=>$section['exercises'])->keyBy('exercise_name');
                        foreach($existing->sections as $section)foreach($section->exercises as $exercise)if(isset($metadata[$exercise->exercise_name]))$exercise->update(['metadata'=>$metadata[$exercise->exercise_name]['metadata']]);
                        $existing->update(['version'=>2]);
                    }
                    continue;
                }
                foreach($data['sections'] as &$section) {
                    foreach($section['exercises'] as &$exercise) {
                        $id = 'flamebangers-'.Str::slug($exercise['exercise_name']);
                        $drill = PlannerCustomDrill::firstOrCreate(['id' => $id], ['created_by' => null,'team_id' => null,'name' => $exercise['exercise_name'],'bucket' => $section['section_type'],'category_group' => 'FlameBangers','equipment' => $exercise['equipment'],'visibility' => 'public','source' => 'flamebangers','data' => ['description' => $exercise['prescription_text'],'trackingType' => $exercise['metadata']['tracking_type'] ?? 'completion','demonstration' => null]]);
                        $exercise['exercise_id'] = $drill->id;
                    }
                }
                unset($section,$exercise);
                $template = app(WorkoutTemplateService::class)->write($data);
                $template->update(['is_premade' => true,'is_public' => true,'version'=>2]);
            }
        });
    }
}
