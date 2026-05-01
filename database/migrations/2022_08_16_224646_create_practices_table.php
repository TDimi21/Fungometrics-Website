<?php

declare(strict_types=1);

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
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
        Schema::create('practices', function (Blueprint $table): void {
            $table->uuid('id')->unique();
            $table->boolean('is_completed')->default(false);
            $table->foreignUuid('team_id')->nullable()->constrained('teams')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('user_id')->nullable()->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->dateTime('started');
            $table->dateTime('finished')->nullable();
            $table->text('note')->nullable();
            $table->text('end_note')->nullable();
            $table->string('type')->default(PracticeTypes::TRAINING->value); //cage,bullpen,batting,liveab,
            //training
            $table->string('modes', 2)->default(PracticeModes::HIT_OR_PITCH->value); //HB=hit or pitch,
            //WB=weight ball,LT=long toss,EV=exit velocity
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
        Schema::dropIfExists('practices');
    }
};
