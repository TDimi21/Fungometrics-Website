<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('baseball_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('unit_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('display_name');
            $table->string('symbol', 24);
            $table->string('measurement_family', 64);
            $table->string('system', 32);
            $table->timestamps();
        });
        Schema::create('baseball_concepts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('baseball_domains');
            $table->string('canonical_key', 128)->unique();
            $table->string('display_name');
            $table->text('definition');
            $table->string('data_type', 32);
            $table->string('canonical_unit_key', 64)->nullable();
            $table->decimal('valid_min', 16, 6)->nullable();
            $table->decimal('valid_max', 16, 6)->nullable();
            $table->string('validation_severity', 24)->default('warning');
            $table->boolean('research_eligible')->default(false);
            $table->boolean('profile_visible')->default(true);
            $table->string('status', 32)->default('active');
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('platform_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('baseball_concept_aliases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('baseball_concept_id')->constrained('baseball_concepts');
            $table->foreignUuid('platform_definition_id')->nullable()->constrained('platform_definitions');
            $table->string('alias');
            $table->string('normalized_alias')->index();
            $table->string('relationship_type', 40);
            $table->string('source_unit_key', 64)->nullable();
            $table->string('transformation_key', 128)->nullable();
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->boolean('is_official')->default(false);
            $table->string('status', 32)->default('active');
            $table->longText('metadata')->nullable();
            $table->timestamps();
            $table->unique(['platform_definition_id', 'normalized_alias'], 'concept_alias_platform_unique');
        });
        Schema::create('unit_conversions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_unit_id')->constrained('unit_definitions');
            $table->foreignUuid('target_unit_id')->constrained('unit_definitions');
            $table->string('transformation_key', 128);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['source_unit_id', 'target_unit_id'], 'unit_conversion_unique');
        });
        Schema::create('mapping_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams');
            $table->foreignUuid('platform_definition_id')->constrained('platform_definitions');
            $table->string('template_fingerprint', 64);
            $table->string('name')->nullable();
            $table->uuid('current_version_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['team_id', 'platform_definition_id', 'template_fingerprint'], 'mapping_template_scope_unique');
        });
        Schema::create('mapping_template_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mapping_template_id')->constrained('mapping_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('header_fingerprint', 64);
            $table->longText('headers');
            $table->string('status', 32)->default('draft');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('supersedes_version_id')->nullable();
            $table->timestamps();
            $table->unique(['mapping_template_id', 'version'], 'mapping_template_version_unique');
        });
        Schema::create('mapping_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mapping_template_version_id')->constrained('mapping_template_versions')->cascadeOnDelete();
            $table->string('source_column_name');
            $table->string('normalized_source_column');
            $table->foreignUuid('baseball_concept_id')->nullable()->constrained('baseball_concepts');
            $table->foreignUuid('source_unit_id')->nullable()->constrained('unit_definitions');
            $table->foreignUuid('canonical_unit_id')->nullable()->constrained('unit_definitions');
            $table->string('transformation_key', 128)->nullable();
            $table->string('resolution_source', 40);
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('required_type', 40)->nullable();
            $table->string('action', 32);
            $table->longText('metadata')->nullable();
            $table->timestamps();
            $table->unique(['mapping_template_version_id', 'normalized_source_column'], 'mapping_entry_source_unique');
        });
        Schema::create('unknown_source_columns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams');
            $table->foreignUuid('platform_definition_id')->constrained('platform_definitions');
            $table->string('template_fingerprint', 64);
            $table->string('source_column_name');
            $table->string('normalized_source_column');
            $table->longText('sample_values')->nullable();
            $table->string('inferred_data_type', 32)->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('status', 32)->default('unresolved');
            $table->foreignUuid('resolved_concept_id')->nullable()->constrained('baseball_concepts');
            $table->timestamps();
            $table->unique(['team_id', 'platform_definition_id', 'template_fingerprint', 'normalized_source_column'], 'unknown_column_scope_unique');
        });
        Schema::create('concept_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams');
            $table->foreignUuid('submitted_by')->constrained('users');
            $table->foreignUuid('platform_definition_id')->nullable()->constrained('platform_definitions');
            $table->string('source_column_name');
            $table->string('proposed_display_name');
            $table->foreignUuid('proposed_domain_id')->nullable()->constrained('baseball_domains');
            $table->string('proposed_unit_key', 64)->nullable();
            $table->text('description')->nullable();
            $table->longText('sample_values')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['concept_submissions', 'unknown_source_columns', 'mapping_entries', 'mapping_template_versions', 'mapping_templates', 'unit_conversions', 'baseball_concept_aliases', 'platform_definitions', 'baseball_concepts', 'unit_definitions', 'baseball_domains'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
