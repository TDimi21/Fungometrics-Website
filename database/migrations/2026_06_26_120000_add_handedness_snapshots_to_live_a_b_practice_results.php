<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the handedness of the batter and pitcher at the moment each LiveAB
 * pitch is recorded, so platoon splits (hitter vs LHP/RHP, pitcher vs LHB/RHB)
 * are computed from immutable per-pitch data instead of the current roster.
 *
 * The mobile app already sends these snapshots with every pitch; they were
 * being dropped on save. Stored raw (L / R / S). A switch hitter is recorded as
 * 'S'; the effective bat side for a given pitch is derived at read time as the
 * opposite of pitcher_throws_snapshot, so no resolution is frozen in incorrectly
 * if a pitching change happens.
 *
 * Existing rows default to NULL so the migration is non-breaking.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('live_a_b_practice_results', function (Blueprint $table): void {
            // Batter handedness as listed at pitch time: 'L', 'R', or 'S' (switch)
            $table->string('batter_bats_snapshot', 1)->nullable()->after('pitching_result_id');

            // Pitcher throwing hand at pitch time: 'L' or 'R'
            $table->string('pitcher_throws_snapshot', 1)->nullable()->after('batter_bats_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('live_a_b_practice_results', function (Blueprint $table): void {
            $table->dropColumn([
                'batter_bats_snapshot',
                'pitcher_throws_snapshot',
            ]);
        });
    }
};
