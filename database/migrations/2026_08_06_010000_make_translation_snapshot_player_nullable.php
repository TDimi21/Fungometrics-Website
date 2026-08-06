<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('translation_snapshots', function (Blueprint $table): void {
            // Generic spreadsheet imports cover many players in one file, so the
            // snapshot itself cannot be pinned to a single player.
            $table->uuid('player_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('translation_snapshots', function (Blueprint $table): void {
            $table->uuid('player_id')->nullable(false)->change();
        });
    }
};
