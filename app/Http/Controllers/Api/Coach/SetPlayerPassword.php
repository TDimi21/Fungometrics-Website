<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\PlayerTeam;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SetPlayerPassword extends Controller
{
    /**
     * POST /coach/players/{id}/set-password
     *
     * Allows an authenticated coach to set a new password directly on a player account.
     * No old/current password is required — coach override.
     *
     * Body: { password: "newpass123", password_confirmation: "newpass123" }
     *
     * Requires: auth:sanctum, ability:coach
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $player = User::findOrFail($id);
        $coach = $request->user();
        $sharesTeam = UserTypes::PLAYER->value === (string) $player->type
            && CoachTeam::query()
                ->where('coach_id', $coach->id)
                ->whereIn(
                    'team_id',
                    PlayerTeam::query()->where('user_id', $player->id)->select('team_id')
                )
                ->exists();

        // Possessing a generic coach token must never allow an account
        // takeover of an unrelated player.
        abort_unless($sharesTeam, HttpCodes::HTTP_NOT_FOUND);

        try {
            $player->password = Hash::make($request->input('password'));
            $player->save();
            $player->tokens()->delete();

            return response()->json([
                'code'    => '034',
                'message' => 'player password updated',
                'status'  => 'success',
                'data'    => [],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('[SetPlayerPassword] ' . $e->getMessage());

            return response()->json([
                'code'    => '034-E',
                'message' => 'error updating player password',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
