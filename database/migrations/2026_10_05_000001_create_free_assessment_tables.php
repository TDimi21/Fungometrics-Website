<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('free_assessments', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('team_id')->constrained('teams');
            $t->foreignUuid('created_by')->constrained('users');
            $t->string('name');
            $t->string('location')->default('The Yard');
            $t->date('assessment_date');
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->index(['team_id', 'assessment_date']);
        });
        Schema::create('free_assessment_players', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('assessment_id')->constrained('free_assessments');
            $t->foreignUuid('player_id')->constrained('users');
            $t->timestamps();
            $t->unique(['assessment_id', 'player_id'], 'fa_enrollment_unique');
        });
        Schema::create('free_assessment_results', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('assessment_id')->constrained('free_assessments');
            $t->foreignUuid('player_id')->constrained('users');
            $t->string('station', 40);
            $t->unsignedInteger('revision')->default(0);
            $t->json('summary');
            $t->text('notes')->nullable();
            $t->string('protocol')->nullable();
            $t->foreignUuid('entered_by')->constrained('users');
            $t->timestamps();
            $t->unique(['assessment_id', 'player_id', 'station'], 'fa_result_unique');
            $t->index(['assessment_id', 'updated_at']);
        });
        Schema::create('free_assessment_attempts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('result_id')->constrained('free_assessment_results');
            $t->unsignedInteger('revision');
            $t->unsignedTinyInteger('attempt_number');
            $t->string('side', 8)->default('none');
            $t->decimal('value', 10, 3);
            $t->string('unit', 12);
            $t->foreignUuid('entered_by')->constrained('users');
            $t->timestamps();
            $t->unique(['result_id', 'revision', 'side', 'attempt_number'], 'fa_attempt_unique');
        });
        Schema::table('player_fitnesses', function (Blueprint $t): void {
            $t->foreignUuid('free_assessment_id')->nullable()->constrained('free_assessments');
            $t->unique(['free_assessment_id', 'user_id'], 'fa_fitness_unique');
            $t->decimal('shuttle_5_10_5', 8, 3)->nullable();
            $t->decimal('pull_strength', 10, 3)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $t): void {
            $t->dropForeign(['free_assessment_id']);
            $t->dropUnique('fa_fitness_unique');
            $t->dropColumn(['free_assessment_id', 'shuttle_5_10_5', 'pull_strength']);
        });
        Schema::dropIfExists('free_assessment_attempts');
        Schema::dropIfExists('free_assessment_results');
        Schema::dropIfExists('free_assessment_players');
        Schema::dropIfExists('free_assessments');
    }
};
