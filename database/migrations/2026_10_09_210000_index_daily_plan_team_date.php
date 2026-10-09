<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table): void {
            $table->index(['team_id', 'date'], 'daily_plans_team_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table): void {
            $table->dropIndex('daily_plans_team_date_index');
        });
    }
};
