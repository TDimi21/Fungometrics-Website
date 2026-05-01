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
        Schema::table('bullpen_practice_results', function (Blueprint $table): void {
            $table->unsignedInteger('pitch_mark')->nullable()->default(null)->change();
            $table->string('pitch_side')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('bullpen_practice_results', function (Blueprint $table): void {
            $table->unsignedInteger('pitch_mark')->change();
            $table->string('pitch_side')->change();
        });
    }
};
