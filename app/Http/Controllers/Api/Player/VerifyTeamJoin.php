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

class VerifyTeamJoin extends Controller
{
    public function __invoke(Request $request, TeamJoinChallengeService $challenges): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'verification_code' => ['required', 'digits:6'],
        ]);

        try {
            $result = $challenges->verify($validated['challenge_id'], $validated['verification_code'], $request);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verification failed.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Team join verification failed unexpectedly.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Phone verification is temporarily unavailable. Please try again.',
            ], 503);
        }

        return response()->json([
            'code' => '018',
            'message' => 'success' === $result['status'] ? 'Joined team successfully.' : 'Create a player account to finish joining.',
            'status' => $result['status'],
            'data' => $result,
        ]);
    }
}
