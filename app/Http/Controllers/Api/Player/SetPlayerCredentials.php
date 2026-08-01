<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Api\Auth\AuthUtils;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\SecurityAuditLogger;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SetPlayerCredentials extends Controller
{
    /**
     * POST /player/set-credentials
     *
     * Called immediately after a player claims their profile for the first time.
     * Sets (or updates) the email and password on the authenticated user account.
     *
     * Body: { email: "player@example.com", password: "secret123", password_confirmation: "secret123" }
     *
     * Requires: a short-lived profile-claim token.
     */
    public function __invoke(Request $request, SecurityAuditLogger $audit): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email', 'unique:users,email,'.auth()->id()],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        try {
            /** @var User $authenticatedUser */
            $authenticatedUser = auth()->user();
            $claimTokenName = (string) $authenticatedUser->currentAccessToken()?->name;
            $claimTeamId = Str::after($claimTokenName, 'profile_claim:');
            if ('' === $claimTeamId || $claimTeamId === $claimTokenName) {
                throw ValidationException::withMessages(['claim' => 'This profile claim session is invalid.']);
            }

            $result = DB::transaction(function () use ($authenticatedUser, $claimTeamId, $request, $audit): array {
                /** @var User $user */
                $user = User::query()->lockForUpdate()->findOrFail($authenticatedUser->id);
                if (filled($user->email) || filled($user->password)) {
                    throw ValidationException::withMessages([
                        'claim' => 'This player profile has already been claimed.',
                    ]);
                }

                $user->email = $request->input('email');
                $user->password = Hash::make($request->input('password'));
                $user->save();

                // A claim token can only create credentials. Replace it with a
                // normal player token after credentials are saved successfully.
                $team = $user->team_players()
                    ->with('team')
                    ->where('team_id', $claimTeamId)
                    ->where('actual', true)
                    ->first()?->team;
                if ( ! $team) {
                    throw ValidationException::withMessages(['claim' => 'This profile is no longer on that team.']);
                }

                $user->tokens()->where('name', 'like', 'profile_claim%')->delete();
                $token = AuthUtils::createTokenFromUser($user)['token'];
                $audit->record('player_profile.credentials_set', $user->id, $team?->id, $request);

                return [
                    'token' => $token,
                    'user' => $user->load('profile', 'player', 'positions', 'fitness'),
                    'team' => $team,
                ];
            });

            return response()->json([
                'code'    => '019',
                'message' => 'credentials set successfully',
                'status'  => 'success',
                'data'    => $result,
            ], HttpCodes::HTTP_OK);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Exception $e) {
            Log::error('Player credential setup failed.', ['exception' => $e::class]);

            return response()->json([
                'code'    => '019-E',
                'message' => 'error setting credentials',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
