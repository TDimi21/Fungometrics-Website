<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams');
            $table->foreignUuid('platform_definition_id')->constrained('platform_definitions');
            $table->foreignUuid('mapping_template_version_id')->constrained('mapping_template_versions');
            $table->foreignUuid('approved_by')->constrained('users');
            $table->foreignUuid('player_id')->constrained('users');
            $table->string('destination', 40);
            $table->string('source_file_name');
            $table->string('source_file_checksum', 64);
            $table->string('source_storage_key');
            $table->unsignedBigInteger('source_file_size');
            $table->longText('snapshot');
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['team_id', 'platform_definition_id', 'source_file_checksum'], 'translation_snapshot_source_unique');
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('translation_snapshot_id')->constrained('translation_snapshots');
            $table->foreignUuid('initiated_by')->constrained('users');
            $table->string('status', 32);
            $table->unsignedInteger('session_count')->default(0);
            $table->unsignedInteger('event_count')->default(0);
            $table->unsignedInteger('metric_count')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        Schema::create('external_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_batch_id')->constrained('import_batches');
            $table->foreignUuid('team_id')->constrained('teams');
            $table->foreignUuid('player_id')->constrained('users');
            $table->foreignUuid('platform_definition_id')->constrained('platform_definitions');
            $table->string('destination', 40);
            $table->string('label');
            $table->timestamp('occurred_at')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('canonical_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('external_session_id')->constrained('external_sessions');
            $table->foreignUuid('player_id')->constrained('users');
            $table->string('event_type', 64);
            $table->unsignedInteger('event_order');
            $table->timestamp('occurred_at')->nullable();
            $table->unsignedInteger('source_row');
            $table->string('source_record_key', 64)->unique();
            $table->longText('source_context')->nullable();
            $table->timestamps();
        });

        Schema::create('canonical_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('canonical_event_id')->constrained('canonical_events');
            $table->foreignUuid('baseball_concept_id')->constrained('baseball_concepts');
            $table->text('value')->nullable();
            $table->decimal('numeric_value', 18, 6)->nullable();
            $table->string('canonical_unit_key', 64)->nullable();
            $table->text('original_value')->nullable();
            $table->string('original_unit_key', 64)->nullable();
            $table->string('original_header');
            $table->string('measurement_classification', 40)->default('source_measurement');
            $table->longText('provenance');
            $table->timestamps();
            $table->unique(['canonical_event_id', 'original_header'], 'canonical_metric_event_header_unique');
            $table->index(['baseball_concept_id', 'numeric_value'], 'canonical_metric_concept_value_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_metrics');
        Schema::dropIfExists('canonical_events');
        Schema::dropIfExists('external_sessions');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('translation_snapshots');
    }
};
