<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $table->float('yd_40_dash')->nullable()->change();
            $table->float('yd_60_dash')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('player_fitnesses', function (Blueprint $table): void {
            $table->time('yd_40_dash')->nullable()->change();
            $table->time('yd_60_dash')->nullable()->change();
        });
    }
};
