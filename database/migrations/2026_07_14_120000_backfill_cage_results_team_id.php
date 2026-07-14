<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill cage_practice_results.team_id from the parent practice.
 *
 * Cage swings can be saved with a null team_id (the CageRequest allows it and the
 * app only sends team_id when a team is loaded). Team-level cage stats filter on
 * team_id, so those null-team rows were silently dropped from dev boards, team
 * charts, and leaderboards while still showing in per-session and per-player views
 * — producing different cage totals on different screens. Backfill so every row
 * that belongs to a team practice carries that team_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cage_practice_results')
            ->join('practices', 'practices.id', '=', 'cage_practice_results.practice_id')
            ->where(function ($q) {
                $q->whereNull('cage_practice_results.team_id')
                    ->orWhere('cage_practice_results.team_id', '');
            })
            ->whereNotNull('practices.team_id')
            ->where('practices.team_id', '<>', '')
            ->update(['cage_practice_results.team_id' => DB::raw('practices.team_id')]);
    }

    public function down(): void
    {
        // Irreversible data backfill — no rollback.
    }
};
