<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Find all duplicate (user_id, team_id) pairs and keep only the first row for each
        // MySQL requires a derived table workaround for DELETE + subquery on same table
        DB::statement("
            DELETE pt FROM player_teams pt
            INNER JOIN player_teams pt2
                ON pt.user_id = pt2.user_id
                AND pt.team_id = pt2.team_id
                AND pt.id > pt2.id
            WHERE pt.deleted_at IS NULL
              AND pt2.deleted_at IS NULL
        ");

        // Hard-delete soft-deleted rows where an active row already exists
        DB::statement("
            DELETE pt FROM player_teams pt
            INNER JOIN player_teams pt2
                ON pt.user_id = pt2.user_id
                AND pt.team_id = pt2.team_id
            WHERE pt.deleted_at IS NOT NULL
              AND pt2.deleted_at IS NULL
        ");

        Schema::table('player_teams', function (Blueprint $table): void {
            $table->unique(['user_id', 'team_id'], 'player_teams_user_team_unique');
        });
    }

    public function down(): void
    {
        Schema::table('player_teams', function (Blueprint $table): void {
            $table->dropUnique('player_teams_user_team_unique');
        });
    }
};
