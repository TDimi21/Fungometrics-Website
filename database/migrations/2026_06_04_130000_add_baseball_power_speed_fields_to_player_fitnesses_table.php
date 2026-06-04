<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('player_fitnesses', 'vertical_jump')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('vertical_jump', 6, 2)->nullable()->after('push_ups');
            });
        }

        if (! Schema::hasColumn('player_fitnesses', 'broad_jump')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('broad_jump', 6, 2)->nullable()->after('vertical_jump');
            });
        }

        if (! Schema::hasColumn('player_fitnesses', 'med_ball_rotational_throw')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('med_ball_rotational_throw', 6, 2)->nullable()->after('broad_jump');
            });
        }

        if (! Schema::hasColumn('player_fitnesses', 'sprint_10yd')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('sprint_10yd', 5, 2)->nullable()->after('med_ball_rotational_throw');
            });
        }

        if (! Schema::hasColumn('player_fitnesses', 'exit_velo')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('exit_velo', 6, 2)->nullable()->after('sprint_10yd');
            });
        }

        if (! Schema::hasColumn('player_fitnesses', 'bat_speed')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('bat_speed', 6, 2)->nullable()->after('exit_velo');
            });
        }

        if (! Schema::hasColumn('player_fitnesses', 'throwing_velo')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('throwing_velo', 6, 2)->nullable()->after('bat_speed');
            });
        }

        if (! Schema::hasColumn('player_fitnesses', 'pitch_velo')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('pitch_velo', 6, 2)->nullable()->after('throwing_velo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $drops = [];

            if (Schema::hasColumn('player_fitnesses', 'pitch_velo')) {
                $drops[] = 'pitch_velo';
            }
            if (Schema::hasColumn('player_fitnesses', 'throwing_velo')) {
                $drops[] = 'throwing_velo';
            }
            if (Schema::hasColumn('player_fitnesses', 'bat_speed')) {
                $drops[] = 'bat_speed';
            }
            if (Schema::hasColumn('player_fitnesses', 'exit_velo')) {
                $drops[] = 'exit_velo';
            }
            if (Schema::hasColumn('player_fitnesses', 'sprint_10yd')) {
                $drops[] = 'sprint_10yd';
            }
            if (Schema::hasColumn('player_fitnesses', 'med_ball_rotational_throw')) {
                $drops[] = 'med_ball_rotational_throw';
            }
            if (Schema::hasColumn('player_fitnesses', 'broad_jump')) {
                $drops[] = 'broad_jump';
            }
            if (Schema::hasColumn('player_fitnesses', 'vertical_jump')) {
                $drops[] = 'vertical_jump';
            }

            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
