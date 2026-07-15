<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('subscription_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->foreignUuid('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('target_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('grant_id')->nullable()->constrained('entitlement_grants')->nullOnDelete();
            $table->longText('before_state')->nullable();
            $table->longText('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_user_id', 'created_at']);
            $table->index(['target_team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audits');
    }
};
