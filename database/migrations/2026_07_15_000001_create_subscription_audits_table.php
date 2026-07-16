<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        if ($this->supportsCheckConstraints()) {
            if ( ! $this->constraintExists('subscriptions', 'subscriptions_one_owner')) {
                DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_one_owner CHECK ((user_id IS NOT NULL AND team_id IS NULL) OR (user_id IS NULL AND team_id IS NOT NULL))');
            }
            if ( ! $this->constraintExists('entitlement_grants', 'entitlement_grants_one_owner')) {
                DB::statement('ALTER TABLE entitlement_grants ADD CONSTRAINT entitlement_grants_one_owner CHECK ((user_id IS NOT NULL AND team_id IS NULL) OR (user_id IS NULL AND team_id IS NOT NULL))');
            }
        }

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

        if ($this->supportsCheckConstraints()) {
            if ($this->constraintExists('entitlement_grants', 'entitlement_grants_one_owner')) {
                DB::statement('ALTER TABLE entitlement_grants DROP CHECK entitlement_grants_one_owner');
            }
            if ($this->constraintExists('subscriptions', 'subscriptions_one_owner')) {
                DB::statement('ALTER TABLE subscriptions DROP CHECK subscriptions_one_owner');
            }
        }
    }

    private function supportsCheckConstraints(): bool
    {
        $driver = DB::getDriverName();
        if ('pgsql' === $driver) {
            return true;
        }
        if ('mysql' !== $driver) {
            return false;
        }

        $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');
        if ( ! str_contains(mb_strtolower($version), 'mariadb')) {
            return true;
        }

        preg_match('/\d+\.\d+\.\d+/', $version, $matches);

        return isset($matches[0]) && version_compare($matches[0], '10.2.1', '>=');
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }
};
