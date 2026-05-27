<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('scripted_bp_swings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('practice_id')
                ->index()
                ->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('batter_id')
                ->index()
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            // Which round type this swing belongs to
            $table->string('round_type');
            // 1-based position within this round for this batter
            $table->unsignedSmallInteger('round_swing_index');
            // Raw input data
            $table->string('contact_type');          // Barrel | Hard | Average | Weak | Miss
            $table->string('trajectory')->nullable(); // LineDrive | FlyBall | GroundBall | PopUp | Foul
            $table->string('direction')->nullable();  // Pull | Middle | Oppo
            $table->unsignedSmallInteger('exit_velocity')->nullable();
            // Computed scoring
            $table->smallInteger('raw_score');        // may be 0 (clamped from negative)
            $table->longText('score_modifiers')->nullable(); // [{label, delta}] breakdown
            // Ordering
            $table->unsignedSmallInteger('sort');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scripted_bp_swings');
    }
};
