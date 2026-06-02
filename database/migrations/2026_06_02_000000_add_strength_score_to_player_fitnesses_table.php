<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('player_fitnesses', 'strength_score')) {
                $table->unsignedTinyInteger('strength_score')->nullable()->after('mobility_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            if (Schema::hasColumn('player_fitnesses', 'strength_score')) {
                $table->dropColumn('strength_score');
            }
        });
    }
};
