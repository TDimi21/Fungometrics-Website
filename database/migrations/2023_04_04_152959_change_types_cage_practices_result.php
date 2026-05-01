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
        Schema::table('cage_practice_results', function (Blueprint $table): void {
            $table->unsignedInteger('launch_angle_velocity')->default(0)->change();
            $table->integer('launch_angle')->default(0)->change();
            $table->integer('spray_angle')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('cage_practice_results', function (Blueprint $table): void {
            $table->unsignedFloat('launch_angle_velocity')->default(0.0)->change();
            $table->float('launch_angle')->default(0.0)->change();
            $table->float('spray_angle')->default(0.0)->change();
        });
    }
};
