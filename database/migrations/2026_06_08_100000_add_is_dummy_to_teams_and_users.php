<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->boolean('is_dummy')->default(false)->after('status');
            $table->uuid('owner_team_id')->nullable()->after('is_dummy');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_dummy')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn(['is_dummy', 'owner_team_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_dummy');
        });
    }
};
