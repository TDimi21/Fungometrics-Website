<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('player_fitnesses', 'hand_strength')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->decimal('hand_strength', 6, 2)->nullable()->after('power_clean');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('player_fitnesses', 'hand_strength')) {
            Schema::table('player_fitnesses', function (Blueprint $table): void {
                $table->dropColumn('hand_strength');
            });
        }
    }
};
