<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class () extends Migration {
    /** @var array<string, array<int, string>> */
    private array $additions = [
        'free' => ['record_assessments'],
        'coach_basic' => ['record_assessments'],
        'coach_pro' => [
            'scripted_bp', 'scripted_bullpen', 'planner_create', 'plan_builder',
            'assign_workouts', 'view_workout_progress', 'manage_player_groups',
            'record_assessments', 'view_assessment_reports',
            'view_assessment_comparisons', 'view_assessment_recommendations',
        ],
        'player_pro' => [
            'view_assessment_reports', 'view_assessment_comparisons',
            'view_assessment_recommendations',
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->additions as $planKey => $keys) {
            $planId = DB::table('subscription_plans')->where('key', $planKey)->value('id');
            if ( ! $planId) {
                continue;
            }
            foreach ($keys as $key) {
                if (DB::table('plan_entitlements')->where('subscription_plan_id', $planId)->where('entitlement_key', $key)->exists()) {
                    continue;
                }
                DB::table('plan_entitlements')->insert([
                    'id' => (string) Str::uuid(),
                    'subscription_plan_id' => $planId,
                    'entitlement_key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('subscription_plans')->where('id', $planId)->update(['updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach ($this->additions as $planKey => $keys) {
            $planId = DB::table('subscription_plans')->where('key', $planKey)->value('id');
            if ($planId) {
                DB::table('plan_entitlements')->where('subscription_plan_id', $planId)->whereIn('entitlement_key', $keys)->delete();
            }
        }
    }
};
