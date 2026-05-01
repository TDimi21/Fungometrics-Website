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
        Schema::create('cage_practice_metas', function (Blueprint $table): void {
            $table->uuid('id')->unique();
            $table->foreignUuid('practice_id')
                ->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedInteger('height_ft')->default(15);
            $table->unsignedInteger('width_inch')->default(0);
            $table->unsignedInteger('width_ft')->default(15);
            $table->unsignedInteger('height_inch')->default(0);
            $table->unsignedInteger('length_ft')->default(65);
            $table->unsignedInteger('length_inch')->default(0);
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
        Schema::dropIfExists('cage_practice_metas');
    }
};
