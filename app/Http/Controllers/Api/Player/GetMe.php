<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Returns the authenticated user's current profile (incl. the live avatar URL) so the
 * app can refresh "who am I" without re-logging in. This is what lets a profile photo
 * edited on the WEB appear in the app's own player view — the app caches the avatar from
 * login, and only this endpoint gives it a fresh value on focus.
 */
class GetMe extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'code' => '074-AUTH',
                'message' => 'unauthenticated',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_UNAUTHORIZED);
        }

        $user->loadMissing('profile', 'player', 'positions');
        $profile = $user->profile;
        $player = $user->player;
        $picture = $profile?->picture ?? config('services.images.logo');

        return response()->json([
            'code' => '074',
            'message' => 'current player profile',
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                // Both shapes the app's avatar logic reads.
                'avatar' => $picture,
                'name' => [
                    'first' => $profile?->first_name,
                    'last' => $profile?->last_name,
                    'full' => trim(sprintf('%s %s', $profile?->first_name, $profile?->last_name)),
                ],
                'profile' => [
                    'first_name' => $profile?->first_name,
                    'last_name' => $profile?->last_name,
                    'picture' => $picture,
                    'avatar' => $picture,
                ],
                'ft' => $player?->height_in_ft ?? 0,
                'inch' => $player?->height_in_inch ?? 0,
                'hit_side' => $player?->hit_side ?? '',
                'throw_side' => $player?->throw_side ?? '',
                'shirt_number' => $player?->number_in_shirt ?? '',
                'positions' => $user->positions,
            ],
        ], HttpCodes::HTTP_OK);
    }
}
