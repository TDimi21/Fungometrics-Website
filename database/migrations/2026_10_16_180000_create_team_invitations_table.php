<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->index();
            $table->uuid('invited_by');
            $table->uuid('user_id')->nullable()->index();
            $table->string('email');
            $table->string('role', 20);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->unsignedInteger('generation')->default(1);
            $table->string('delivery_status', 30)->default('queued');
            $table->string('failure_code')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'email', 'role']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
