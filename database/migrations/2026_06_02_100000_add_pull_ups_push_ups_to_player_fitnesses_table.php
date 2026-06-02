<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pull_ups')->nullable()->default(null)->after('dead_lift');
            $table->unsignedSmallInteger('push_ups')->nullable()->default(null)->after('pull_ups');
        });
    }

    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $table->dropColumn(['pull_ups', 'push_ups']);
        });
    }
};
