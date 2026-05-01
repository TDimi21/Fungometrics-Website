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
        Schema::create('live_a_b_practice_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('practice_id')->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedInteger('turn');
            $table->unsignedInteger('turn_strike')->default(0);
            $table->unsignedInteger('turn_ball')->default(0);
            $table->boolean('turn_is_over')->default(false);
            $table->unsignedInteger('sort');
            $table->boolean('is_hit')->default(false);
            $table->boolean('is_strike')->default(false);
            $table->boolean('is_ball')->default(false);
            $table->unsignedInteger('bases')->default(0);
            $table->uuid('batting_result_id')->nullable();
            $table->uuid('pitching_result_id')->nullable();
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
        Schema::dropIfExists('live_a_b_practice_results');
    }
};
