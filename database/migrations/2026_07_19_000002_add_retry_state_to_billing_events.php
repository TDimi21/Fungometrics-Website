<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('billing_events', function (Blueprint $table): void {
            $table->string('processing_status', 32)->default('pending')->index();
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('billing_events', function (Blueprint $table): void {
            $table->dropIndex(['processing_status']);
            $table->dropIndex(['next_retry_at']);
            $table->dropColumn(['processing_status', 'processing_attempts', 'last_attempted_at', 'next_retry_at']);
        });
    }
};
