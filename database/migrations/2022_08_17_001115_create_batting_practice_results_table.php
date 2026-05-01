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
        Schema::create('batting_practice_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('practice_id')->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->uuid('team_id')->nullable();
            $table->foreignUuid('batter_id')->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->boolean('is_contact')->default(false);
            $table->string('pitch_location')->nullable();
            $table->string('quality_of_contact');
            $table->string('type_of_hit');
            $table->unsignedInteger('field_mark')->default(0);
            $table->unsignedInteger('pitch_mark')->nullable();
            $table->string('field_direction')->nullable(); //left,right.middle
            $table->unsignedInteger('velocity')->default(0);
            $table->unsignedInteger('sort');
            $table->boolean('is_in_match')->default(false);
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
        Schema::dropIfExists('batting_practice_results');
    }
};
