<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('player_account_connections', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('source_id')->index();
            $t->uuid('target_id')->index();
            $t->uuid('coach_id');
            $t->uuid('team_id');
            $t->string('status')->default('pending');
            $t->timestamp('expires_at');
            $t->timestamp('completed_at')->nullable();
            $t->longText('transfer_summary')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('player_account_connections'); }
};
