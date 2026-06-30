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
        Schema::create('arm_care_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('team_id')
                ->nullable()
                ->constrained('teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('routine_key');
            $table->string('routine_label')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('grade')->nullable();
            $table->unsignedSmallInteger('assigned')->default(0);
            $table->unsignedSmallInteger('completed')->default(0);
            $table->unsignedSmallInteger('completed_total')->default(0);
            $table->unsignedSmallInteger('skipped')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->json('breakdown')->nullable();
            // Client-generated id for idempotent retries from the offline-first app.
            $table->string('client_id')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'performed_at']);
            $table->index(['team_id', 'performed_at']);
            $table->unique(['user_id', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('arm_care_sessions');
    }
};
