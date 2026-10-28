<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('long_toss_plans', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('player_id')->index(); $t->uuid('coach_id'); $t->uuid('team_id');
            $t->json('settings'); $t->timestamps(); $t->unique(['player_id','team_id']);
        });
        Schema::create('long_toss_guides', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('player_id')->index(); $t->uuid('practice_id')->index();
            $t->uuid('team_id')->nullable(); $t->json('snapshot'); $t->unsignedTinyInteger('readiness'); $t->boolean('pain');
            $t->string('status'); $t->unsignedInteger('stage')->default(0); $t->unsignedInteger('starting_count')->default(0);
            $t->unsignedTinyInteger('readiness_after')->nullable(); $t->boolean('pain_after')->nullable();
            $t->json('review')->nullable(); $t->timestamp('finished_at')->nullable(); $t->timestamps();
            $t->unique(['practice_id','player_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('long_toss_guides'); Schema::dropIfExists('long_toss_plans'); }
};
