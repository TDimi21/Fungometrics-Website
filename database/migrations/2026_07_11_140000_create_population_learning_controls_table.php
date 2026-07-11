<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('population_learning_controls')) {
            return;
        }

        Schema::create('population_learning_controls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('metric_key')->index();
            $table->string('category')->nullable()->index();
            $table->string('status')->default('auto')->index();
            $table->boolean('population_enabled')->default(false);
            $table->boolean('research_enabled')->default(true);
            $table->boolean('composite_enabled')->default(true);
            $table->integer('minimum_sample_size')->default(30);
            $table->string('minimum_confidence')->nullable();
            $table->boolean('allow_global_bucket')->default(true);
            $table->boolean('allow_exact_peer_bucket')->default(true);
            $table->boolean('allow_age_bucket')->default(true);
            $table->decimal('max_exclusion_rate', 5, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('last_audit_summary')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->uuid('reviewed_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique('metric_key');
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('population_learning_controls');
    }
};
