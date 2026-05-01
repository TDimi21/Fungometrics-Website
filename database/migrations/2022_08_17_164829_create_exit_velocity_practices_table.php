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
        Schema::create('exit_velocity_practices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('practice_id')
                ->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('team_id')
                ->nullable()
                ->constrained('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedInteger('set')->default(1);
            $table->unsignedInteger('sort')->default(1);
            $table->string('trajectory');
            $table->unsignedInteger('velocity');
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
        Schema::dropIfExists('exit_velocity_practices');
    }
};
