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
        Schema::create('practice_line_ups', function (Blueprint $table): void {
            $table->uuid('id')->unique();
            $table->foreignUuid('practice_id')
                ->constrained('practices')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('user_id')->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->boolean('is_pitching')->default(false);
            $table->boolean('is_batting')->default(false);
            $table->unsignedInteger('sort');
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
        Schema::dropIfExists('practice_line_ups');
    }
};
