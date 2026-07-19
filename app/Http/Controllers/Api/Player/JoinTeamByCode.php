<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Services\Security\TeamJoinChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JoinTeamByCode extends Controller
{
    public function __invoke(Request $request, TeamJoinChallengeService $challenges): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:32'],
            'team_code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $challenge = $challenges->request($validated['phone'], $validated['team_code'], $request);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'The team join request could not be started.',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'code' => '018-VERIFY',
            'message' => 'Enter the verification code sent to the supplied phone number.',
            'status' => 'verification_required',
            'data' => [
                'challenge_id' => $challenge->id,
                'expires_at' => $challenge->expires_at->toIso8601String(),
            ],
        ], 202);
    }
}
