<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
     * Requires: auth:sanctum
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email', 'unique:users,email,' . auth()->id()],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        try {
            $user = auth()->user();

            $user->email    = $request->input('email');
            $user->password = Hash::make($request->input('password'));
            $user->save();

            return response()->json([
                'code'    => '019',
                'message' => 'credentials set successfully',
                'status'  => 'success',
                'data'    => [
                    'email' => $user->email,
                ],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('[SetPlayerCredentials] ' . $e->getMessage());

            return response()->json([
                'code'    => '019-E',
                'message' => 'error setting credentials',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
