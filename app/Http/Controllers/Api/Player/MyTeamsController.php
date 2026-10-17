<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\{PlayerTeam, Team};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB};

class MyTeamsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->type === 'player', 403);
        return response()->json(['data' => Team::whereHas('team_players', fn ($q) => $q
            ->where('user_id', $request->user()->id)->where('actual', true))
            ->orderBy('name')->get(['id', 'name'])])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, string $teamId)
    {
        abort_unless($request->user()->type === 'player', 403);
        DB::transaction(function () use ($request, $teamId): void {
            // Same team mutex as invitation acceptance; never remove another player's membership.
            Team::whereKey($teamId)->lockForUpdate()->firstOrFail();
            $memberships = PlayerTeam::where('user_id', $request->user()->id)
                ->where('team_id', $teamId)->where('actual', true)->lockForUpdate()->get();
            abort_if($memberships->isEmpty(), 404);
            foreach ($memberships as $membership) {
                $membership->update(['actual' => false]);
                $membership->delete(); // Soft delete the connection, not the player or history.
            }
        });
        Cache::forget('roster_team_'.$teamId);
        return response()->json(['message' => 'You have left the team. Your player profile and training history are preserved.']);
    }
}
