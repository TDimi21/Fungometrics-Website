<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_plan_revisions')) {
            return;
        }

        Schema::create('daily_plan_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('daily_plan_id')->index();
            $table->uuid('team_id')->nullable()->index();
            $table->uuid('created_by_user_id')->nullable()->index();
            $table->integer('revision_number')->index();
            $table->string('source')->nullable()->index();
            $table->string('change_type')->nullable()->index();
            $table->string('title_before')->nullable();
            $table->string('title_after')->nullable();
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            // Production MariaDB rejects native JSON DDL; model casts handle JSON in text.
            $table->longText('plan_before')->nullable();
            $table->longText('plan_after')->nullable();
            $table->longText('diff_summary')->nullable();
            $table->longText('applied_suggestions')->nullable();
            $table->text('reason')->nullable();
            $table->text('coach_notes')->nullable();
            $table->timestamps();

            $table->unique(['daily_plan_id', 'revision_number']);
            $table->index(['daily_plan_id', 'created_at']);
            $table->index(['team_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_revisions');
    }
};
