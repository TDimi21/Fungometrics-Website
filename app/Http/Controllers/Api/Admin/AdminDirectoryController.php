<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamPlayerAdminController extends Controller
{
    /**
     * All teams, org-wide (not coach-scoped), with player/coach counts.
     * Team's own "state" is a direct column; teams have no "level" — level
     * lives on the person's profile (see players() below).
     */
    public function teams(Request $request): JsonResponse
    {
        $query = DB::table('teams')
            ->select('id', 'name', 'state', 'zip')
            ->whereNull('deleted_at')
            ->where('is_dummy', false);

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        $teams = $query->orderBy('state')->orderBy('name')->get();
        $teamIds = $teams->pluck('id')->all();

        $playerCounts = DB::table('player_teams')
            ->select('team_id', DB::raw('count(distinct user_id) as count'))
            ->whereIn('team_id', $teamIds)
            ->whereNull('deleted_at')
            ->where('actual', true)
            ->groupBy('team_id')
            ->pluck('count', 'team_id');

        $coachCounts = DB::table('coach_teams')
            ->select('team_id', DB::raw('count(distinct coach_id) as count'))
            ->whereIn('team_id', $teamIds)
            ->whereNull('deleted_at')
            ->groupBy('team_id')
            ->pluck('count', 'team_id');

        $data = $teams->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'state' => $team->state,
            'players_count' => (int) ($playerCounts[$team->id] ?? 0),
            'coaches_count' => (int) ($coachCounts[$team->id] ?? 0),
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * All players, org-wide, with their profile state/level and current team.
     * "level" defaults to LevelTypes::PLAYER when never set during registration.
     */
    public function players(Request $request): JsonResponse
    {
        $query = DB::table('users as u')
            ->select(
                'u.id',
                'pr.first_name',
                'pr.last_name',
                'pr.state',
                'pr.level',
                't.id as team_id',
                't.name as team_name',
                't.state as team_state'
            )
            ->join('profiles as pr', 'u.id', '=', 'pr.user_id')
            ->leftJoin('player_teams as pt', function ($join) {
                $join->on('u.id', '=', 'pt.user_id')
                    ->where('pt.actual', '=', true)
                    ->whereNull('pt.deleted_at');
            })
            ->leftJoin('teams as t', function ($join) {
                $join->on('pt.team_id', '=', 't.id')
                    ->where('t.is_dummy', '=', false);
            })
            ->where('u.type', '=', 'player')
            ->where('u.is_dummy', '=', false)
            ->whereNull('u.deleted_at');

        if ($state = $request->query('state')) {
            $query->where('pr.state', $state);
        }

        if ($level = $request->query('level')) {
            $query->where('pr.level', $level);
        }

        if ($teamId = $request->query('team_id')) {
            $query->where('t.id', $teamId);
        }

        $rows = $query->orderBy('pr.state')->orderBy('pr.last_name')->get();

        $data = $rows->map(fn ($row) => [
            'id' => $row->id,
            'name' => trim("{$row->first_name} {$row->last_name}"),
            'state' => $row->state,
            'level' => $row->level,
            'team' => $row->team_id ? [
                'id' => $row->team_id,
                'name' => $row->team_name,
                'state' => $row->team_state,
            ] : null,
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
