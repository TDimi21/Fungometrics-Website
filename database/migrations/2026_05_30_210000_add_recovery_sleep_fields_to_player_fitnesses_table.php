<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('player_fitnesses', 'sleep_hours')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('sleep_hours', 4, 2)->nullable()->after('body_weight');
            });
        }

        if (!Schema::hasColumn('player_fitnesses', 'sleep_quality_1_to_5')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->unsignedTinyInteger('sleep_quality_1_to_5')->nullable()->after('sleep_hours');
            });
        }

        if (!Schema::hasColumn('player_fitnesses', 'recovery_score')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->unsignedTinyInteger('recovery_score')->nullable()->after('sleep_quality_1_to_5');
            });
        }

        if (!Schema::hasColumn('player_fitnesses', 'mobility_score')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->unsignedTinyInteger('mobility_score')->nullable()->after('recovery_score');
            });
        }
    }

    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $drops = [];

            if (Schema::hasColumn('player_fitnesses', 'mobility_score')) {
                $drops[] = 'mobility_score';
            }
            if (Schema::hasColumn('player_fitnesses', 'recovery_score')) {
                $drops[] = 'recovery_score';
            }
            if (Schema::hasColumn('player_fitnesses', 'sleep_quality_1_to_5')) {
                $drops[] = 'sleep_quality_1_to_5';
            }
            if (Schema::hasColumn('player_fitnesses', 'sleep_hours')) {
                $drops[] = 'sleep_hours';
            }

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
