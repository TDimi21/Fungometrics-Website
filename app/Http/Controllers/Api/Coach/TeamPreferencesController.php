<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use Illuminate\Http\Request;

class TeamPreferencesController extends Controller
{
    private function memberships(Request $request)
    {
        abort_unless($request->user()->type === 'coach', 403);
        return CoachTeam::where('coach_id', $request->user()->id)->whereHas('team')
            ->orderByDesc('is_main')->orderBy('created_at')->orderBy('team_id')->get();
    }

    public function show(Request $request)
    {
        $memberships = $this->memberships($request);
        $ids = $memberships->pluck('team_id');
        $user = $request->user();
        // Revoked/deleted teams never remain selectable through a saved preference.
        $default = $ids->contains($user->default_team_id) ? $user->default_team_id : null;
        $active = $ids->contains($user->active_team_id) ? $user->active_team_id : null;
        return response()->json(['data' => [
            'default_team_id' => $default,
            'active_team_id' => $active,
            'initial_team_id' => $default ?? $memberships->first()?->team_id,
        ]]);
    }

    public function update(Request $request)
    {
        $ids = $this->memberships($request)->pluck('team_id');
        $data = $request->validate([
            'default_team_id' => ['sometimes', 'nullable', 'uuid'],
            'active_team_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        foreach ($data as $id) abort_unless($id === null || $ids->contains($id), 403);
        $user = $request->user();
        foreach ($data as $key => $id) $user->{$key} = $id;
        $user->save();
        return $this->show($request);
    }
}
