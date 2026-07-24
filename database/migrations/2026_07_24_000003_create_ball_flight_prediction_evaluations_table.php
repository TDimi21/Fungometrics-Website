<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ball_flight_prediction_evaluations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('reference_observation_id');
            $table->index('reference_observation_id', 'bfpe_observation_idx');
            $table->foreign('reference_observation_id', 'bfpe_observation_fk')
                ->references('id')->on('ball_flight_reference_observations')->cascadeOnDelete();
            $table->string('engine_version');
            $table->string('physics_version');
            $table->string('calibration_version')->nullable();
            $table->string('prediction_mode');
            $table->string('spin_source');
            $table->decimal('predicted_distance_ft', 8, 3)->nullable();
            $table->decimal('predicted_low_ft', 8, 3)->nullable();
            $table->decimal('predicted_high_ft', 8, 3)->nullable();
            $table->decimal('predicted_hang_time_seconds', 8, 4)->nullable();
            $table->decimal('predicted_max_height_ft', 8, 3)->nullable();
            $table->decimal('distance_error_ft', 9, 3)->nullable();
            $table->decimal('absolute_distance_error_ft', 9, 3)->nullable();
            $table->decimal('hang_time_error_seconds', 9, 4)->nullable();
            $table->decimal('max_height_error_ft', 9, 3)->nullable();
            $table->unsignedTinyInteger('confidence_percent')->nullable();
            // MariaDB 10.1 has no native JSON column type. Eloquent's array
            // cast still serializes this payload as JSON when stored as text.
            $table->longText('assumptions')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['reference_observation_id', 'engine_version', 'prediction_mode', 'spin_source'],
                'bfpe_observation_engine_mode_spin_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ball_flight_prediction_evaluations');
    }
};
