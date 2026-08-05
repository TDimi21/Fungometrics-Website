<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $table->unsignedFloat('trap_bar_deadlift')->nullable()->after('dead_lift');
            $table->unsignedFloat('grip_strength_left')->nullable()->after('hand_strength');
            $table->unsignedFloat('grip_strength_right')->nullable()->after('grip_strength_left');
            $table->unsignedFloat('plank_hold')->nullable()->after('push_ups');
            $table->json('strength_test_metadata')->nullable()->after('plank_hold');
        });
    }

    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $table->dropColumn([
                'trap_bar_deadlift',
                'grip_strength_left',
                'grip_strength_right',
                'plank_hold',
                'strength_test_metadata',
            ]);
        });
    }
};
