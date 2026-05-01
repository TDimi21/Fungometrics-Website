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
        Schema::create('player_fitnesses', function (Blueprint $table): void {
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->date('fitness_date')->nullable();
            $table->unsignedInteger('bench_press')->nullable()->default(0);
            $table->unsignedInteger('front_squat')->nullable()->default(0);
            $table->unsignedInteger('back_squat')->nullable()->default(0);
            $table->unsignedInteger('power_clean')->nullable()->default(0);
            $table->unsignedFloat('dead_lift')->nullable()->default(0.0);
            $table->time('yd_40_dash')->nullable();
            $table->time('yd_60_dash')->nullable();
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
        Schema::dropIfExists('player_fitnesses');
    }
};
