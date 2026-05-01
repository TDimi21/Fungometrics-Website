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
        Schema::create('player_positions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('player_id')->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('position'); //'P,C,1B,2B,3B,SS,LF,RF,CF';
            $table->boolean('is_preferred')->default(false);
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
        Schema::dropIfExists('player_positions');
    }
};
