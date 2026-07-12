<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach review of a player's completed workout ({ reviewed, reviewed_at,
 * reviewed_by, feedback }). Stored on the player's progress row so both the coach
 * review screen and the player's completed-workout view read one record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plan_progress', function (Blueprint $table): void {
            $table->longText('coach_review')->nullable()->after('reflection');
        });
    }

    public function down(): void
    {
        Schema::table('daily_plan_progress', function (Blueprint $table): void {
            $table->dropColumn('coach_review');
        });
    }
};
