<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Services\Security\TeamJoinChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        } catch (Throwable $exception) {
            Log::error('Team join challenge creation failed.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Phone verification is temporarily unavailable. Please try again.',
            ], 503);
        }

        $testMode = 'allowlisted_test_phone' === $challenge->getAttribute('verification_mode');

        return response()->json([
            'code' => '018-VERIFY',
            'message' => $testMode
                ? 'Testing number recognized. Enter the test verification code configured by your administrator.'
                : 'Enter the verification code sent to the supplied phone number.',
            'status' => 'verification_required',
            'data' => [
                'challenge_id' => $challenge->id,
                'expires_at' => $challenge->expires_at->toIso8601String(),
                'verification_mode' => $testMode ? 'test' : 'sms',
            ],
        ], 202);
    }
}
