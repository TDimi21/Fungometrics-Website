<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('account_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('intended_type', 32);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'intended_type', 'used_at']);
        });

        Schema::create('team_join_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->char('phone_hash', 64)->index();
            $table->string('code_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('security_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('action')->index();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audits');
        Schema::dropIfExists('team_join_challenges');
        Schema::dropIfExists('account_claims');
    }
};
