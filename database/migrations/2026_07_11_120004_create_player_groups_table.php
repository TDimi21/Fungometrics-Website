<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach-defined player sub-groups (e.g. "Pitchers", "Freshmen"). Reusable presets
 * a coach can grab quickly when assigning a daily plan or a practice plan.
 * Team-scoped and synced app↔web.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_groups', function (Blueprint $table): void {
            // Client-generated string id so the app/web can upsert their own groups.
            $table->string('id')->primary();
            $table->uuid('team_id')->nullable()->index();
            $table->string('created_by')->nullable();
            $table->string('name');
            // longText (not json) — see the daily_plans migration note. Array of user ids.
            $table->longText('member_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_groups');
    }
};
