<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athletic_performance_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary()->unique();
            $table->uuid('player_id')->index();
            $table->uuid('team_id')->nullable()->index();
            $table->uuid('assessment_id')->index();
            $table->string('role', 30)->default('hitter');

            $table->decimal('overall_api_score', 5, 2)->nullable();
            $table->decimal('strength_score', 5, 2)->nullable();
            $table->decimal('power_score', 5, 2)->nullable();
            $table->decimal('speed_score', 5, 2)->nullable();
            $table->decimal('baseball_score', 5, 2)->nullable();
            $table->decimal('recovery_mobility_score', 5, 2)->nullable();

            $table->decimal('lower_body_strength_score', 5, 2)->nullable();
            $table->decimal('upper_body_strength_score', 5, 2)->nullable();
            $table->decimal('relative_strength_score', 5, 2)->nullable();

            $table->string('projection_label', 80)->nullable();
            $table->string('grade_label', 80)->nullable();

            $table->unsignedTinyInteger('team_percentile')->nullable();
            $table->unsignedInteger('team_rank')->nullable();
            $table->unsignedInteger('team_count')->nullable();

            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->json('development_plan')->nullable();

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique('assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletic_performance_scores');
    }
};
