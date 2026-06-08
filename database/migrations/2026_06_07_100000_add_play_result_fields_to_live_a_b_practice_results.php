<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add play result, outs, runs, RBI, and runner state columns to the
 * live_a_b_practice_results table.
 *
 * These fields are populated by the Ball-In-Play Resolution Screen (LiveAB_2)
 * when the coach selects a specific play outcome (Single, Ground Out, Double
 * Play, Sac Fly, etc.).  They allow the backend to derive box score stats,
 * run RBI attribution, and reconstruct the full play-by-play log without
 * needing the mobile game-engine state.
 *
 * Existing rows default to NULL / 0 / false so the migration is non-breaking.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('live_a_b_practice_results', function (Blueprint $table): void {
            // Specific play result string from the game engine
            // e.g. 'SINGLE', 'DOUBLE', 'TRIPLE', 'HR', 'GROUND_OUT', 'FLY_OUT',
            //      'DOUBLE_PLAY', 'SAC_FLY', 'WALK', 'HBP', 'ERROR', 'FC' …
            $table->string('play_result')->nullable()->after('bases');

            // How many outs this single play recorded (0, 1, 2, or 3)
            $table->unsignedTinyInteger('outs_recorded')->default(0)->after('play_result');

            // Runs that scored on this play
            $table->unsignedTinyInteger('runs_scored')->default(0)->after('outs_recorded');

            // RBI credited to the batter on this play
            $table->unsignedTinyInteger('rbi')->default(0)->after('runs_scored');

            // Whether the batter reached base safely
            $table->boolean('is_safe')->default(false)->after('rbi');

            // Sacrifice flags
            $table->boolean('sac_fly')->default(false)->after('is_safe');
            $table->boolean('sac_bunt')->default(false)->after('sac_fly');

            // JSON arrays representing base occupancy before and after the play
            // e.g.  "[true, false, true]"  = runners on 1st and 3rd
            $table->string('runners_before')->nullable()->after('sac_bunt');
            $table->string('runners_after')->nullable()->after('runners_before');
        });
    }

    public function down(): void
    {
        Schema::table('live_a_b_practice_results', function (Blueprint $table): void {
            $table->dropColumn([
                'play_result',
                'outs_recorded',
                'runs_scored',
                'rbi',
                'is_safe',
                'sac_fly',
                'sac_bunt',
                'runners_before',
                'runners_after',
            ]);
        });
    }
};
