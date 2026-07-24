<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROPOSED, NOT YET RUN — see the Cage Distance Validation Lab final report.
 *
 * Development-only table for recording measured-distance calibration
 * observations from the Validation Lab (Part 7: optional device-measured
 * distance input). Entirely separate from cage_practice_results — never
 * touches production cage scoring/statistics data.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cage_distance_validation_observations', function (Blueprint $table): void {
            $table->id();
            $table->decimal('exit_velocity_mph', 6, 2);
            $table->decimal('launch_angle_deg', 6, 2);
            $table->decimal('spray_angle_deg', 6, 2);
            $table->decimal('v1_distance_ft', 6, 1)->nullable();
            $table->decimal('v2_distance_ft', 6, 1)->nullable();
            $table->decimal('v2_low_ft', 6, 1)->nullable();
            $table->decimal('v2_high_ft', 6, 1)->nullable();
            $table->decimal('measured_distance_ft', 6, 1)->nullable();
            $table->string('measurement_source')->nullable();
            $table->string('ball_type')->nullable();
            $table->decimal('temperature_f', 5, 1)->nullable();
            $table->decimal('elevation_ft', 7, 1)->nullable();
            $table->unsignedBigInteger('facility_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cage_distance_validation_observations');
    }
};
