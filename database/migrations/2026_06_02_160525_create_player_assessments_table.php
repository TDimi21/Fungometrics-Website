<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->uuid('team_id')->nullable()->index();
            $table->uuid('assessed_by')->nullable()->comment('coach user_id');
            $table->date('assessment_date')->nullable();
            $table->enum('type', ['strength', 'mobility', 'full'])->default('full');

            // Strength: raw values (optional)
            $table->unsignedSmallInteger('squat_lbs')->nullable();
            $table->unsignedSmallInteger('deadlift_lbs')->nullable();
            $table->unsignedSmallInteger('bench_lbs')->nullable();
            $table->unsignedSmallInteger('broad_jump_in')->nullable();
            $table->unsignedSmallInteger('vertical_jump_in')->nullable();
            $table->decimal('sprint_10yd_sec', 4, 2)->nullable();

            // Strength: percentile inputs 0-100 (coach assigns)
            $table->unsignedTinyInteger('squat_percentile')->nullable();
            $table->unsignedTinyInteger('deadlift_percentile')->nullable();
            $table->unsignedTinyInteger('lunge_percentile')->nullable();
            $table->unsignedTinyInteger('bench_press_percentile')->nullable();
            $table->unsignedTinyInteger('pull_up_percentile')->nullable();
            $table->unsignedTinyInteger('push_up_percentile')->nullable();
            $table->unsignedTinyInteger('broad_jump_percentile')->nullable();
            $table->unsignedTinyInteger('vertical_jump_percentile')->nullable();
            $table->unsignedTinyInteger('sprint_10yd_percentile')->nullable();
            $table->unsignedTinyInteger('med_ball_rotational_percentile')->nullable();
            $table->unsignedTinyInteger('exit_velocity_percentile')->nullable();
            $table->unsignedTinyInteger('bat_speed_percentile')->nullable();

            // Mobility: coach-rated 0-10 per area
            $table->unsignedTinyInteger('hip_mobility')->nullable();
            $table->unsignedTinyInteger('shoulder_mobility')->nullable();
            $table->unsignedTinyInteger('ankle_mobility')->nullable();
            $table->unsignedTinyInteger('hip_flexor_mobility')->nullable();
            $table->unsignedTinyInteger('rotational_mobility')->nullable();

            // Computed scores (stored for history)
            $table->unsignedTinyInteger('strength_lower_body_score')->nullable();
            $table->unsignedTinyInteger('strength_upper_body_score')->nullable();
            $table->unsignedTinyInteger('strength_explosive_score')->nullable();
            $table->unsignedTinyInteger('strength_rotational_score')->nullable();
            $table->unsignedTinyInteger('strength_overall_score')->nullable();
            $table->unsignedTinyInteger('mobility_overall_score')->nullable();
            $table->unsignedTinyInteger('overall_score')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_assessments');
    }
};
