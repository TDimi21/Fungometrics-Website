<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('player_fitnesses', 'overall_api_score')) {
                // Canonical 0–100 athletic index, mirrored from the latest
                // AthleticPerformanceScore so it travels with the metrics row.
                $table->decimal('overall_api_score', 5, 2)->nullable()->after('strength_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            if (Schema::hasColumn('player_fitnesses', 'overall_api_score')) {
                $table->dropColumn('overall_api_score');
            }
        });
    }
};
