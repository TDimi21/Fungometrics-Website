<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A player's progress against an assigned Daily Planner plan: the daily readiness
 * survey, per-item completion/actuals, and the end-of-session reflection.
 * One row per (plan, player).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plan_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('plan_id')->index();
            $table->string('user_id')->index(); // the player (users.id)
            // longText (not json) — see the daily_plans migration note.
            $table->longText('readiness')->nullable();   // survey answers
            $table->longText('items')->nullable();       // itemId → { done, actualSets, actualValue, pain, note }
            $table->longText('reflection')->nullable();  // survey answers
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_progress');
    }
};
