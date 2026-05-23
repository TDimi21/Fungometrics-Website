<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Remove duplicate active coach_teams rows using self-join (MySQL-compatible)
        DB::statement("
            DELETE ct FROM coach_teams ct
            INNER JOIN coach_teams ct2
                ON ct.coach_id = ct2.coach_id
                AND ct.team_id = ct2.team_id
                AND ct.id > ct2.id
            WHERE ct.deleted_at IS NULL
              AND ct2.deleted_at IS NULL
        ");

        // Hard-delete soft-deleted rows where an active row already exists
        DB::statement("
            DELETE ct FROM coach_teams ct
            INNER JOIN coach_teams ct2
                ON ct.coach_id = ct2.coach_id
                AND ct.team_id = ct2.team_id
            WHERE ct.deleted_at IS NOT NULL
              AND ct2.deleted_at IS NULL
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
