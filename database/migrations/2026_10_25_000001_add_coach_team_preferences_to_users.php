<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('default_team_id')->nullable();
            $table->uuid('active_team_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['default_team_id', 'active_team_id']));
    }
};
