<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\{CoachTeam, PlayerTeam, User};
use Illuminate\Http\{JsonResponse, Request};

class SetPlayerPassword extends Controller
{
    /** Compatibility endpoint for older apps. Coaches never choose player credentials. */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $actor = $request->user();
        abort_unless('coach' === (string) $actor->type, 403);
        $player = User::where('type', 'player')->findOrFail($id);
        $sharesTeam = CoachTeam::where('coach_id', $actor->id)->whereHas('team')
            ->whereIn('team_id', PlayerTeam::where('user_id', $id)->where('actual', true)->select('team_id'))->exists();
        abort_unless($sharesTeam, 404);

        $claimed = filled($player->password);
        return response()->json([
            'status' => 'error',
            'message' => $claimed
                ? 'The player must use Password Recovery from their own login screen. Coaches cannot change player passwords.'
                : 'Invite this player from the roster. The player creates their own password when claiming the profile.',
            'next_action' => $claimed ? 'player_password_recovery' : 'invite_player',
        ], 403);
    }
}
