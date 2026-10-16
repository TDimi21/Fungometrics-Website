<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('communication_preferences', function (Blueprint $t): void {
            $t->uuid('user_id')->primary();
            foreach (['workouts','assessments','development_plans','team_announcements'] as $key) {
                $t->boolean($key)->default(true);
            }
            $t->boolean('weekly_reports')->default(false);
            $t->timestamps();
        });
        Schema::create('communication_events', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('dedupe_key', 64)->unique();
            $t->string('event_type', 64);
            $t->string('resource_type', 40);
            $t->string('resource_id', 64);
            $t->string('occurrence', 100);
            $t->uuid('team_id')->nullable();
            $t->uuid('recipient_id')->nullable();
            $t->longText('payload')->nullable();
            $t->timestamp('processed_at')->nullable()->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('failure_code')->nullable();
            $t->timestamps();
        });
        Schema::create('communication_deliveries', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('event_id')->index();
            $t->string('dedupe_key', 64)->unique();
            $t->uuid('recipient_id')->index();
            $t->uuid('player_id')->nullable();
            $t->uuid('team_id')->nullable();
            $t->string('event_type', 64);
            $t->string('resource_type', 40);
            $t->string('resource_id', 64);
            $t->string('channel', 20)->default('email');
            $t->string('status', 30)->default('pending')->index();
            $t->longText('payload');
            $t->string('failure_code')->nullable();
            $t->timestamp('attempted_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('communication_deliveries');
        Schema::dropIfExists('communication_events');
        Schema::dropIfExists('communication_preferences');
    }
};
