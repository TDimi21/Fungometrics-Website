<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('scripted_bp_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('practice_id')
                ->index()
                ->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            // JSON array of [{round_type: 'BARREL', swing_count: 5}, ...]
            // Ordered — index position is the round order during the session
            $table->longText('rounds');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scripted_bp_plans');
    }
};
