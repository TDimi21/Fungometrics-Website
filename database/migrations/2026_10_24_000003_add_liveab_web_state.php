<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table): void {
            $table->longText('liveab_config')->nullable();
            $table->longText('liveab_state')->nullable();
        });
        Schema::table('live_a_b_practice_results', function (Blueprint $table): void {
            $table->unsignedInteger('inning')->nullable();
            $table->string('half_inning', 6)->nullable();
        });
        Schema::create('liveab_web_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('practice_id')->index();
            $table->uuid('client_id');
            $table->uuid('result_id')->nullable();
            $table->string('action', 50)->default('pitch');
            $table->longText('before_state')->nullable();
            $table->longText('after_state');
            $table->unsignedInteger('revision');
            $table->boolean('undone')->default(false);
            $table->timestamps();
            $table->unique(['practice_id', 'client_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('liveab_web_events');
        Schema::table('live_a_b_practice_results', fn (Blueprint $table) => $table->dropColumn(['inning','half_inning']));
        Schema::table('practices', fn (Blueprint $table) => $table->dropColumn(['liveab_config','liveab_state']));
    }
};
