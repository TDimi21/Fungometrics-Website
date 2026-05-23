<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Remove any existing duplicate rows (keep the oldest active one per user+team pair)
        DB::statement("
            DELETE FROM player_teams
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) as id
                    FROM player_teams
                    WHERE deleted_at IS NULL
                    GROUP BY user_id, team_id
                ) AS keep
            )
            AND deleted_at IS NULL
        ");

        // Also hard-delete soft-deleted duplicates where an active row already exists
        DB::statement("
            DELETE FROM player_teams
            WHERE deleted_at IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM (
                    SELECT id, user_id, team_id FROM player_teams WHERE deleted_at IS NULL
                ) AS active
                WHERE active.user_id = player_teams.user_id
                AND active.team_id = player_teams.team_id
            )
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
