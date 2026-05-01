<?php

declare(strict_types=1);

use App\Models\Concerns\BattingTrajectory;
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
        Schema::create('bullpen_practice_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('practice_id')
                ->index()
                ->constrained('practices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->uuid('team_id')
                ->nullable()
                ->index();
            $table->foreignUuid('pitcher_id')
                ->index()
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedInteger('pitch_mark')->nullable();
            $table->string('pitch_side')->nullable(); // TOP,LEFT,BOTTOM,RIGHT
            $table->boolean('is_strike')
                ->default(false);
            $table->unsignedInteger('miles_per_hour');
            $table->string('type_throw');
            $table->string('trajectory')
                ->default(BattingTrajectory::TAKE->value);
            $table->unsignedInteger('sort');
            $table->boolean('is_in_match')
                ->default(false);
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
        Schema::dropIfExists('bullpen_practice_results');
    }
};
