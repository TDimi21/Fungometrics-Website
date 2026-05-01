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
        Schema::table('batting_practice_results', function (Blueprint $table): void {
            $table->string('pitch_location')->nullable()->default(null)->change();
            $table->unsignedInteger('field_mark')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('batting_practice_results', function (Blueprint $table): void {
            $table->string('pitch_location')->change();
            $table->unsignedInteger('field_mark')->default(0)->change();
        });
    }
};
