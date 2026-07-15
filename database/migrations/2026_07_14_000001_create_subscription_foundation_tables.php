<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('audience')->index();
            $table->boolean('active')->default(true)->index();
            // LONGTEXT keeps this compatible with older MariaDB versions that
            // do not support the native JSON column type. Model casts still
            // provide normal array/JSON serialization at the application layer.
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('entitlement_key')->index();
            $table->longText('metadata')->nullable();
            $table->timestamps();
            $table->unique(['subscription_plan_id', 'entitlement_key'], 'plan_entitlement_unique');
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('subscription_plans');
            $table->string('provider')->index();
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('provider_subscription_id')->nullable();
            $table->string('provider_product_id')->nullable();
            $table->string('status')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable()->index();
            $table->timestamp('grace_period_ends_at')->nullable()->index();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable()->index();
            $table->longText('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['team_id', 'status']);
            $table->unique(['provider', 'provider_subscription_id'], 'provider_subscription_unique');
        });

        Schema::create('entitlement_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->string('entitlement_key')->index();
            $table->string('source_type')->index();
            $table->uuid('source_id')->nullable()->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->longText('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'entitlement_key']);
            $table->index(['team_id', 'entitlement_key']);
        });

        Schema::create('billing_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('event_type')->index();
            $table->longText('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id'], 'billing_provider_event_unique');
        });

        // Older MariaDB releases also lack reliable CHECK support. Eloquent's
        // saving hooks enforce exactly one owner on every application write.
        if ('pgsql' === DB::getDriverName()) {
            DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_one_owner CHECK ((user_id IS NOT NULL AND team_id IS NULL) OR (user_id IS NULL AND team_id IS NOT NULL))');
            DB::statement('ALTER TABLE entitlement_grants ADD CONSTRAINT entitlement_grants_one_owner CHECK ((user_id IS NOT NULL AND team_id IS NULL) OR (user_id IS NULL AND team_id IS NOT NULL))');
        }

        $now = now();
        foreach (config('access.plans') as $key => $definition) {
            $planId = (string) Str::uuid();
            DB::table('subscription_plans')->insert([
                'id' => $planId, 'key' => $key, 'name' => $definition['name'],
                'audience' => $definition['audience'], 'active' => true,
                'metadata' => json_encode(['limits' => $definition['limits']]),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($definition['entitlements'] as $entitlement) {
                DB::table('plan_entitlements')->insert([
                    'id' => (string) Str::uuid(), 'subscription_plan_id' => $planId,
                    'entitlement_key' => $entitlement, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('entitlement_grants');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('subscription_plans');
    }
};
