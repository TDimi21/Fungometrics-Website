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
        Schema::table('live_a_b_practice_results', function (Blueprint $table): void {
            $table->integer('turn_pitches')->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('live_a_b_practice_results', function (Blueprint $table): void {
            $table->dropColumn('turn_pitches');
        });
    }
};
