<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\PlayerTeam;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SetPlayerPassword extends Controller
{
    /**
     * POST /coach/players/{id}/set-password
     *
     * Allows a shared-team head coach to initialize a passwordless player account.
     * Existing credentials require recipient-controlled recovery and cannot be overridden.
     *
     * Body: { password: "newpass123", password_confirmation: "newpass123" }
     *
     * Requires: auth:sanctum, ability:coach
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:20', 'confirmed'],
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

        abort_if(filled($player->password), 403, 'Players with an existing password must use Password Recovery themselves.');
        abort_unless(CoachTeam::where('coach_id', $coach->id)->where('is_main', true)
            ->whereIn('team_id', PlayerTeam::where('user_id', $player->id)->select('team_id'))->exists(), 403, 'Only a head coach can initialize an unclaimed player password.');

        try {
            $initialized = DB::transaction(function () use ($player, $request) {
                $locked = User::whereKey($player->id)->lockForUpdate()->firstOrFail();
                if (filled($locked->password)) return false;
                $locked->password = Hash::make($request->input('password'));
                $locked->save();
                $locked->tokens()->delete();
                return true;
            });
            if (! $initialized) return response()->json(['status' => 'error', 'message' => 'This player must use Password Recovery.'], 403);

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
