<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which players a Daily Planner plan is assigned to. A pivot (rather than a JSON
 * column on the plan) so the player "My Workouts" query is a clean join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plan_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('plan_id')->index();
            $table->string('user_id')->index(); // the player (users.id)
            $table->timestamps();

            $table->unique(['plan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_assignments');
    }
};
