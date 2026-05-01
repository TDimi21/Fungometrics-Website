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
        Schema::create('teams_live_a_b_s', function (Blueprint $table): void {
            $table->uuid('id')->unique();
            $table->foreignUuid('team_id')
                ->constrained('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('practice_id')
                ->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
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
        Schema::dropIfExists('teams_live_a_b_s');
    }
};
