<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'practices_user_created_activity_index');
        });
    }

    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table): void {
            $table->dropIndex('practices_user_created_activity_index');
        });
    }
};
