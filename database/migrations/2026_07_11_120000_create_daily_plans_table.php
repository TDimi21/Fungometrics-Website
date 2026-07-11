<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FMTRX Daily Planner — the coach-authored per-player plan.
 *
 * Kept deliberately separate from `practice_plans` (team practice sessions) and
 * from `player_fitnesses` (periodic assessment snapshots): this is its own domain
 * so the app and the web can share it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plans', function (Blueprint $table): void {
            // Client-generated string id so the app/web can upsert their own plans.
            $table->string('id')->primary();
            $table->uuid('team_id')->nullable()->index();
            $table->string('created_by')->nullable();
            $table->string('name')->nullable();
            $table->date('date')->nullable();
            $table->string('phase')->nullable();
            $table->string('primary_goal')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->string('workload_level')->nullable();
            // draft | published | template
            $table->string('status')->default('draft')->index();
            // longText (not json) — the prod MariaDB build rejects native json DDL;
            // the model's 'buckets' => 'array' cast stores/reads JSON in text just fine.
            $table->longText('buckets')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
    }
};
