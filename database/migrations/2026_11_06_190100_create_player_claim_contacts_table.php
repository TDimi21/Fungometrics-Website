<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('player_claim_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('team_id');
            $table->uuid('user_id');
            $table->string('contact_hash',64);
            $table->unique(['team_id','user_id','contact_hash'], 'player_claim_contact_unique');
            $table->index(['team_id','contact_hash']);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('player_claim_contacts'); }
};
