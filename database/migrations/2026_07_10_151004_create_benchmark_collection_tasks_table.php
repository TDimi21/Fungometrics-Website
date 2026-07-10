<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_collection_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->index();
            $table->uuid('assigned_to_player_id')->nullable()->index();
            $table->uuid('created_by_user_id')->nullable()->index();
            $table->string('source')->default('benchmark_collection_plan');
            $table->string('temporary_key')->nullable()->index();
            $table->string('task_type')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('draft')->index();
            $table->string('due_window')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->json('metrics')->nullable();
            $table->json('missing_fields')->nullable();
            $table->json('instructions')->nullable();
            $table->text('coach_notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'assigned_to_player_id', 'task_type', 'status'], 'bench_tasks_team_player_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_collection_tasks');
    }
};
