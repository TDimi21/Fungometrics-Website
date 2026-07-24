<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ball_flight_reference_observations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_type')->index('bfro_source_type_idx');
            $table->string('source_name');
            $table->string('source_file');
            $table->string('source_row_identifier')->nullable();
            $table->string('source_event_identifier')->nullable();
            $table->string('source_session_identifier')->nullable();
            $table->string('player_name')->nullable();
            $table->string('player_external_identifier')->nullable();
            $table->string('player_level')->nullable();
            $table->string('age_group')->nullable();
            $table->date('event_date')->nullable()->index('bfro_event_date_idx');
            $table->string('facility_name')->nullable();
            $table->string('facility_id')->nullable();
            $table->decimal('exit_velocity_mph', 7, 3)->nullable()->index('bfro_exit_velocity_idx');
            $table->decimal('launch_angle_deg', 7, 3)->nullable()->index('bfro_launch_angle_idx');
            $table->decimal('spray_angle_deg', 7, 3)->nullable();
            $table->decimal('measured_distance_ft', 8, 3)->nullable();
            $table->decimal('last_tracked_distance_ft', 8, 3)->nullable();
            $table->decimal('measured_hang_time_seconds', 8, 4)->nullable();
            $table->decimal('measured_max_height_ft', 8, 3)->nullable();
            $table->decimal('measured_spin_rpm', 9, 3)->nullable();
            $table->decimal('measured_spin_axis_deg', 8, 3)->nullable();
            $table->decimal('contact_height_ft', 7, 3)->nullable();
            $table->string('tagged_hit_type')->nullable();
            $table->string('automatic_hit_type')->nullable();
            $table->string('launch_confidence')->nullable();
            $table->string('landing_confidence')->nullable();
            $table->string('ball_type')->nullable();
            $table->decimal('temperature_f', 7, 3)->nullable();
            $table->decimal('humidity_percent', 7, 3)->nullable();
            $table->decimal('pressure_inhg', 7, 3)->nullable();
            $table->decimal('elevation_ft', 9, 3)->nullable();
            $table->boolean('eligible_for_primary_calibration')->default(false)->index('bfro_primary_eligible_idx');
            $table->boolean('eligible_for_external_validation')->default(false)->index('bfro_external_eligible_idx');
            $table->string('partition')->nullable()->index('bfro_partition_idx');
            $table->text('exclusion_reason')->nullable();
            // MariaDB 10.1 has no native JSON column type. Eloquent's array
            // cast still serializes this payload as JSON when stored as text.
            $table->longText('raw_metadata')->nullable();
            $table->string('import_hash', 64)->unique('bfro_import_hash_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ball_flight_reference_observations');
    }
};
