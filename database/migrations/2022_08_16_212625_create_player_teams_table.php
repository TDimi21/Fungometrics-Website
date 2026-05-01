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
        Schema::create('player_teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('actual')->default(true);
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
        Schema::dropIfExists('player_teams');
    }
};
