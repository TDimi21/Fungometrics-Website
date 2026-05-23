<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Remove duplicate active coach_teams rows (keep oldest per coach+team pair)
        DB::statement("
            DELETE FROM coach_teams
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) as id
                    FROM coach_teams
                    WHERE deleted_at IS NULL
                    GROUP BY coach_id, team_id
                ) AS keep
            )
            AND deleted_at IS NULL
        ");

        // Hard-delete soft-deleted rows where an active row already exists
        DB::statement("
            DELETE FROM coach_teams
            WHERE deleted_at IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM (
                    SELECT id, coach_id, team_id FROM coach_teams WHERE deleted_at IS NULL
                ) AS active
                WHERE active.coach_id = coach_teams.coach_id
                AND active.team_id = coach_teams.team_id
            )
        ");

        Schema::table('coach_teams', function (Blueprint $table): void {
            $table->unique(['coach_id', 'team_id'], 'coach_teams_coach_team_unique');
        });
    }

    public function down(): void
    {
        Schema::table('coach_teams', function (Blueprint $table): void {
            $table->dropUnique('coach_teams_coach_team_unique');
        });
    }
};
