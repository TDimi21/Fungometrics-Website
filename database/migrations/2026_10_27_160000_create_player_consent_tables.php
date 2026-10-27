<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_claim_setups', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('born_date');
            $table->boolean('guardian_required')->default(false);
            $table->string('terms_version')->nullable();
            $table->string('privacy_version')->nullable();
            $table->string('acknowledgment_version')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('player_policy_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('player_id')->index();
            $table->foreign('player_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('actor_id')->index();
            $table->uuid('team_id')->nullable();
            $table->string('context');
            $table->string('terms_version');
            $table->string('privacy_version');
            $table->string('acknowledgment_version');
            $table->text('acknowledgment_text');
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('player_policy_acceptances');
        Schema::dropIfExists('player_claim_setups');
    }
};
