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
        Schema::create('cage_practice_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('practice_id')->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()
                ->constrained('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedFloat('launch_angle_velocity')->default(0.0);
            $table->float('launch_angle')->default(0.0);
            $table->float('spray_angle')->default(0.0);
            $table->unsignedInteger('distance_travel')->default(0);
            $table->boolean('ground_ball')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('cage_practice_results');
    }
};
