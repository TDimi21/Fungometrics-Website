<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Org-wide (not coach-scoped) team/coach/player directory for the admin panel.
 *
 * These endpoints exist because the admin Vue/RN screens used to reuse the
 * `coach/search/coaches` and `coach/search/players` endpoints, which are
 * throttled to 30 req/min and only return 15 records/page. Admin pages were
 * walking every page of both on every mount with no caching, which reliably
 * exhausted the throttle and made the admin panel unusably slow. These
 * endpoints return the full list in one query instead.
 */
class AdminDirectoryController extends Controller
{
    /**
     * All teams, with player/coach counts. Team's own "state" is a direct
     * column; teams have no "level" — level lives on the person's profile.
     */
    public function teams(Request $request): JsonResponse
    {
        $query = DB::table('teams')
            ->select('id', 'name', 'state', 'zip', 'join_code')
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
            'join_code' => $team->join_code,
            'players_count' => (int) ($playerCounts[$team->id] ?? 0),
            'coaches_count' => (int) ($coachCounts[$team->id] ?? 0),
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * All coaches, with profile + subscription info. Pass ?team_id= to load
     * just one team's coach roster (used for the Teams page's lazy detail view).
     */
    public function coaches(Request $request): JsonResponse
    {
        $query = DB::table('users as u')
            ->select(
                'u.id',
                'u.email',
                'u.phone',
                'u.subscription_plan',
                'u.created_at',
                'pr.first_name',
                'pr.last_name',
                'pr.picture',
                'pr.state',
                'pr.level'
            )
            ->join('profiles as pr', 'u.id', '=', 'pr.user_id')
            ->where('u.type', '=', 'coach')
            ->where('u.is_dummy', '=', false)
            ->whereNull('u.deleted_at');

        if ($state = $request->query('state')) {
            $query->where('pr.state', $state);
        }

        if ($teamId = $request->query('team_id')) {
            $query->whereIn('u.id', function ($sub) use ($teamId) {
                $sub->select('coach_id')
                    ->from('coach_teams')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at');
            });
        }

        $rows = $query->orderBy('pr.first_name')->orderBy('pr.last_name')->get();

        $data = $rows->map(fn ($row) => [
            'id' => $row->id,
            'type' => 'coach',
            'email' => $row->email,
            'phone' => $row->phone,
            'subscription_plan' => $row->subscription_plan,
            'created_at' => $row->created_at,
            'profile' => [
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'picture' => $row->picture,
                'state' => $row->state,
                'level' => $row->level,
            ],
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * All players, with their profile state/level and current team.
     * "level" defaults to LevelTypes::PLAYER when never set during registration.
     */
    public function players(Request $request): JsonResponse
    {
        $query = DB::table('users as u')
            ->select(
                'u.id',
                'u.email',
                'u.phone',
                'u.created_at',
                'pr.first_name',
                'pr.last_name',
                'pr.picture',
                'pr.state',
                'pr.level',
                'pl.born_date',
                't.id as team_id',
                't.name as team_name',
                't.state as team_state'
            )
            ->join('profiles as pr', 'u.id', '=', 'pr.user_id')
            ->leftJoin('players as pl', 'u.id', '=', 'pl.user_id')
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

        $rows = $query->orderBy('pr.first_name')->orderBy('pr.last_name')->get();

        $data = $rows->map(function ($row) {
            $team = $row->team_id ? [
                'id' => $row->team_id,
                'name' => $row->team_name,
                'state' => $row->team_state,
            ] : null;

            return [
                'id' => $row->id,
                'type' => 'player',
                'email' => $row->email,
                'phone' => $row->phone,
                'created_at' => $row->created_at,
                'name' => [
                    'first' => $row->first_name,
                    'last' => $row->last_name,
                    'full' => trim("{$row->first_name} {$row->last_name}"),
                ],
                'avatar' => $row->picture,
                'born_date' => $row->born_date,
                'state' => $row->state,
                'level' => $row->level,
                'team' => $team,
                'actual_team' => $team ? [$team] : [],
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
