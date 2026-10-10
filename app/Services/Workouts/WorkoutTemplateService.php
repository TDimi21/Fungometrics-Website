<?php

declare(strict_types=1);

namespace App\Services\Workouts;

use App\Models\{WorkoutTemplate, WorkoutTemplateSection, WorkoutTemplateExercise, CoachTeam, PlayerTeam, PlayerGroup};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkoutTemplateService
{
    public function teamIds($user): array
    {
        return CoachTeam::where('coach_id', $user->id)->pluck('team_id')->all();
    }
    public function team($user, string $id): void
    {
        abort_unless(in_array($id, $this->teamIds($user), true), 404);
    }
    public function visible($user)
    {
        return WorkoutTemplate::where('is_active', true)->where(fn ($q) => $q->where('is_public', true)->orWhere('created_by', $user->id)->orWhereIn('organization_id', $this->teamIds($user)));
    }
    public function get($user, string $id): WorkoutTemplate
    {
        return $this->visible($user)->with('sections.exercises')->findOrFail($id);
    }
    public function athletes($user, string $teamId, array $players, array $groups = [], bool $wholeTeam = false): array
    {
        $this->team($user, $teamId);
        foreach ($groups as $groupId) {
            $group = PlayerGroup::where('team_id', $teamId)->findOrFail($groupId);
            $players = array_merge($players, $group->member_ids ?? []);
        }
        $valid = PlayerTeam::where('team_id', $teamId)->pluck('user_id')->map(fn ($id) => (string)$id)->all();
        if($wholeTeam) {
            return array_values(array_unique($valid));
        }
        $players = array_values(array_unique(array_map('strval', $players)));
        if(array_diff($players, $valid)) {
            throw ValidationException::withMessages(['player_ids' => 'Every athlete must belong to the selected team.']);
        }
        return $players;
    }
    public function validate(array $data): array
    {
        $rules = ['name' => 'required|string|max:200','description' => 'nullable|string|max:5000','sport' => 'required|string|max:60','category' => 'required|string|max:100','program_type' => 'nullable|string|max:150','intensity_label' => 'nullable|string|max:40','target_rpe_min' => 'nullable|numeric|min:0|max:100','target_rpe_max' => 'nullable|numeric|min:0|max:100','estimated_duration_minutes' => 'nullable|integer|min:1|max:1440','version' => 'sometimes|integer|min:1','sections' => 'required|array|min:1|max:30','sections.*.name' => 'required|string|max:150','sections.*.section_type' => 'required|distinct|in:movement_prep,throwing,recovery,arm_care,hitting,pitching,strength_primary,strength_secondary,strength_accessory,speed_agility,conditioning,assessments,education,coach_notes','sections.*.instructions' => 'nullable|string|max:5000','sections.*.exercises' => 'present|array|max:200','sections.*.exercises.*.exercise_name' => 'required|string|max:200','sections.*.exercises.*.prescription_text' => 'required|string|max:3000','sections.*.exercises.*.equipment' => 'nullable|string|max:500','sections.*.exercises.*.instructions' => 'nullable|string|max:3000','sections.*.exercises.*.is_optional' => 'boolean','sections.*.exercises.*.ball_weights' => 'nullable|array|max:40','sections.*.exercises.*.ball_weights.*' => 'string|max:50','sections.*.exercises.*.metadata' => 'nullable|array','sections.*.exercises.*.metadata.tracking_type' => 'nullable|in:radar,session,quick_throws','sections.*.exercises.*.metadata.session_type' => 'nullable|in:bullpen,long_toss,weighted_ball,exit_velocity,cage,live_ab,strength,assessment','sections.*.exercises.*.metadata.video_url' => 'nullable|url|max:1000','sections.*.exercises.*.metadata.image_url' => 'nullable|url|max:1000','sections.*.exercises.*.metadata.planned_pitch_count' => 'nullable|integer|min:0|max:1000','sections.*.exercises.*.metadata.pitch_types' => 'nullable|array','sections.*.exercises.*.metadata.pitch_types.*' => 'string|max:80','sections.*.exercises.*.metadata.targets' => 'nullable|array','sections.*.exercises.*.metadata.targets.*' => 'string|max:150','sections.*.exercises.*.metadata.focus' => 'nullable|string|max:1000'];
        foreach(['sets_min','sets_max','reps_min','reps_max','duration_seconds','distance_yards','intensity_min','intensity_max'] as $f) {
            $rules['sections.*.exercises.*.'.$f] = 'nullable|numeric|min:0|max:100000';
        }
        validator($data, $rules)->validate();
        foreach($data['sections'] as $section) {
            foreach($section['exercises'] as $exercise) {
                foreach(['sets','reps','intensity'] as $key) {
                    if(isset($exercise[$key.'_min'],$exercise[$key.'_max']) && $exercise[$key.'_min'] > $exercise[$key.'_max']) {
                        throw ValidationException::withMessages(['sections' => 'Minimum prescriptions cannot exceed maximums.']);
                    }
                }
            }
        }
        if(isset($data['target_rpe_min'],$data['target_rpe_max']) && $data['target_rpe_min'] > $data['target_rpe_max']) {
            throw ValidationException::withMessages(['target_rpe_max' => 'Maximum effort must not be below minimum effort.']);
        }
        return $data;
    }
    public function write(array $data, ?WorkoutTemplate $template = null, ?string $owner = null): WorkoutTemplate
    {
        return DB::transaction(function () use ($data, $template, $owner) {
            $fields = collect($data)->only(['name','slug','description','sport','category','program_type','intensity_label','target_rpe_min','target_rpe_max','estimated_duration_minutes'])->all();
            if($template) {
                $template = WorkoutTemplate::lockForUpdate()->findOrFail($template->id);
                abort_if(isset($data['version']) && (int)$data['version'] !== $template->version, 409, 'Template changed. Reload before saving.');
                $fields['version'] = $template->version + 1;
                $template->update($fields);
            } else {
                $template = WorkoutTemplate::create($fields + ['created_by' => $owner,'slug' => Str::slug($data['name']).'-'.Str::uuid(),'is_public' => false,'is_premade' => false]);
            }
            $ids = $template->sections()->pluck('id');
            WorkoutTemplateExercise::whereIn('workout_template_section_id', $ids)->delete();
            $template->sections()->delete();
            foreach($data['sections'] as $index => $section) {
                $row = $template->sections()->create(collect($section)->only(['name','section_type','instructions'])->all() + ['sort_order' => $index]);
                foreach($section['exercises'] as $i => $exercise) {
                    $row->exercises()->create(collect($exercise)->only(['exercise_id','exercise_name','sets_min','sets_max','reps_min','reps_max','prescription_text','duration_seconds','distance_yards','intensity_min','intensity_max','equipment','ball_weights','instructions','is_optional','metadata'])->all() + ['sort_order' => $i]);
                }
            }
            return $template->fresh('sections.exercises');
        });
    }
    public function buckets(WorkoutTemplate $template): array
    {
        return $template->sections->map(function ($section) use ($template) {
            return ['type' => $section->section_type,'title' => $section->name,'note' => $section->instructions,'template_source' => ['id' => $template->id,'version' => $template->version,'name' => $template->name,'description' => $template->description], 'items' => $section->exercises->map(fn ($e) => [
                'id' => (string)Str::uuid(),'name' => $e->exercise_name,'exerciseId' => $e->exercise_id,'sets' => $e->sets_min,'reps' => $e->reps_min,
                'prescription_text' => $e->prescription_text,'sets_min' => $e->sets_min,'sets_max' => $e->sets_max,'reps_min' => $e->reps_min,'reps_max' => $e->reps_max,
                'equipment' => $e->equipment,'ball_weights' => $e->ball_weights ?? [],'durationSec' => $e->duration_seconds,'distance' => $e->distance_yards,
                'intensity_min' => $e->intensity_min,'intensity_max' => $e->intensity_max,'required' => ! $e->is_optional,'note' => $e->instructions,
                'template_id' => $template->id,'template_version' => $template->version,'template_exercise_id' => $e->id,'metadata' => $e->metadata ?? [],
            ])->all()];
        })->all();
    }
}
