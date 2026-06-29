<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coach edits to a report's AI insights (synced, replaces localStorage).
        Schema::table('player_assessments', function (Blueprint $table): void {
            $table->longText('coach_insights')->nullable();
        });

        // Team-level AI practice-recommendation override.
        Schema::table('teams', function (Blueprint $table): void {
            $table->longText('practice_insight')->nullable();
        });

        // In-progress assessment drafts — one per player, shared with team staff.
        Schema::create('assessment_drafts', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->uuid('team_id')->nullable()->index();
            $table->string('updated_by')->nullable();
            $table->longText('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('player_assessments', function (Blueprint $table): void {
            $table->dropColumn('coach_insights');
        });
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('practice_insight');
        });
        Schema::dropIfExists('assessment_drafts');
    }
};
