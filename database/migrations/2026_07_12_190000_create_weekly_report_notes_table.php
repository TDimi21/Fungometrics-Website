<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('weekly_report_notes')) {
            return;
        }

        Schema::create('weekly_report_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->index();
            $table->uuid('created_by_user_id')->nullable()->index();
            $table->date('week_start_date')->index();
            $table->date('week_end_date')->index();
            $table->string('audience')->default('coach');
            $table->string('note_type')->index();
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('visibility')->default('staff');
            $table->uuid('player_id')->nullable()->index();
            // Production MariaDB rejects native JSON DDL; model casts handle JSON in text.
            $table->longText('payload')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'week_start_date', 'week_end_date'], 'weekly_report_notes_team_week_idx');
            $table->index(['team_id', 'note_type', 'visibility'], 'weekly_report_notes_team_type_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_report_notes');
    }
};
