<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('external_player_mappings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams');
            $table->foreignUuid('platform_definition_id')->constrained('platform_definitions');
            $table->string('source_player_id', 96)->nullable();
            $table->string('source_player_name');
            $table->string('normalized_source_player_name');
            $table->foreignUuid('fmtrx_player_id')->constrained('users');
            $table->longText('source_roles')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('status', 32)->default('active');
            $table->foreignUuid('approved_by')->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['team_id', 'platform_definition_id', 'source_player_id'],
                'external_player_mapping_source_id_unique'
            );
            $table->index('normalized_source_player_name', 'external_player_mapping_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_player_mappings');
    }
};
