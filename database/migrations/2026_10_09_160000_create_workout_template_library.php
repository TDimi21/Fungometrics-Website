<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if ( ! Schema::hasTable('workout_templates')) {
            Schema::create('workout_templates', function (Blueprint $t): void {
                $t->uuid('id')->primary();
                $t->string('organization_id')->nullable()->index();
                $t->string('created_by')->nullable()->index();
                $t->string('name');
                $t->string('slug', 191)->unique();
                $t->text('description')->nullable();
                $t->string('sport')->default('baseball');
                $t->string('category');
                $t->string('program_type')->nullable();
                $t->string('intensity_label')->nullable();
                $t->decimal('target_rpe_min', 5, 2)->nullable();
                $t->decimal('target_rpe_max', 5, 2)->nullable();
                $t->unsignedInteger('estimated_duration_minutes')->nullable();
                $t->boolean('is_premade')->default(false);
                $t->boolean('is_public')->default(false);
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
            });
        }
        if ( ! Schema::hasTable('workout_template_sections')) {
            Schema::create('workout_template_sections', function (Blueprint $t): void {
                $t->uuid('id')->primary();
                $t->uuid('workout_template_id')->index();
                $t->string('name');
                $t->string('section_type');
                $t->unsignedInteger('sort_order');
                $t->text('instructions')->nullable();
            });
        }
        if ( ! Schema::hasTable('workout_template_exercises')) {
            Schema::create('workout_template_exercises', function (Blueprint $t): void {
                $t->uuid('id')->primary();
                $t->uuid('workout_template_section_id')->index();
                $t->string('exercise_id')->nullable();
                $t->string('exercise_name');
                $t->unsignedInteger('sort_order');
                foreach (['sets_min','sets_max','reps_min','reps_max','duration_seconds','distance_yards','intensity_min','intensity_max'] as $field) {
                    $t->decimal($field, 9, 2)->nullable();
                }
                $t->text('prescription_text');
                $t->text('equipment')->nullable();
                $t->longText('ball_weights')->nullable();
                $t->text('instructions')->nullable();
                $t->boolean('is_optional')->default(false);
                $t->longText('metadata')->nullable();
            });
        }
        if ( ! Schema::hasTable('workout_programs')) {
            Schema::create('workout_programs', function (Blueprint $t): void {
                $t->uuid('id')->primary();
                $t->string('team_id')->index();
                $t->string('created_by');
                $t->string('name');
                $t->date('start_date');
                $t->unsignedInteger('weeks');
                $t->string('status')->default('draft');
                $t->unsignedInteger('version')->default(1);
                $t->longText('schedule');
                $t->timestamps();
            });
        }
        if ( ! Schema::hasTable('workout_session_links')) {
            Schema::create('workout_session_links', function (Blueprint $t): void {
                $t->uuid('id')->primary();
                $t->string('plan_id', 64);
                $t->string('user_id', 36);
                $t->string('item_id', 36);
                $t->uuid('practice_id')->index();
                $t->string('type', 20);
                $t->boolean('created_for_workout')->default(false);
                $t->timestamps();
                $t->unique(['plan_id','user_id','item_id'], 'workout_session_item_unique');
            });
        }
    }
    public function down(): void
    {
        foreach (['workout_session_links','workout_programs','workout_template_exercises','workout_template_sections','workout_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
