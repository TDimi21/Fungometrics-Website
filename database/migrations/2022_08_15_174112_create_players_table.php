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
        Schema::create('players', function (Blueprint $table): void {
            $table->uuid('id')->primary()->unique();
            $table->unsignedInteger('height_in_ft')->nullable();
            $table->unsignedInteger('height_in_inch')->nullable();
            $table->unsignedFloat('weight')->nullable();
            $table->string('hit_side', 1)->default('R'); //left right, switch
            $table->string('throw_side', 1)->default('R'); //left right
            $table->unsignedInteger('number_in_shirt')->nullable();
            $table->date('born_date')->nullable();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
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
        Schema::dropIfExists('players');
    }
};
