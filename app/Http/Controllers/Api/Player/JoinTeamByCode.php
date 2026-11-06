<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Services\Security\PlayerProfileClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class JoinTeamByCode extends Controller
{
    public function __invoke(Request $request, PlayerProfileClaimService $claims): JsonResponse
    {
        $validated = $request->validate([
            'contact' => ['required_without_all:phone,email', 'string', 'max:255'],
            'claim_code' => ['required_without:team_code', 'string', 'size:12'],
            'phone' => ['sometimes', 'string', 'min:10', 'max:32'],
            'email' => ['sometimes', 'email', 'max:255'],
            'team_code' => ['required_without:claim_code', 'string', 'size:6'],
        ]);

        try {
            $result = isset($validated['claim_code'])
                ? $claims->claimWithCode($validated['contact'] ?? $validated['email'] ?? $validated['phone'], $validated['claim_code'], $request)
                : $claims->claim($validated['contact'] ?? $validated['email'] ?? $validated['phone'], $validated['team_code'], $request);
        } catch (ValidationException $exception) {
            // Keep specific validation messages when available. Birth-date setup
            // now happens after the restricted claim session is issued.
            $eligibilityMessage = $exception->errors()['invitation'][0] ?? null;
            return response()->json([
                'status' => 'error',
                'message' => $eligibilityMessage ?? ($exception->errors()['claim'][0] ?? 'Use the contact your coach entered, or ask your coach to update it.'),
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Player profile claim failed unexpectedly.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Profile claiming is temporarily unavailable. Please try again.',
            ], 503);
        }

        return response()->json([
            'code' => '018',
            'message' => isset($result['challenge_id']) ? 'Enter the verification code sent to your contact.' : 'Complete your profile setup to continue.',
            'status' => 'success',
            'data' => $result,
        ])->header('Cache-Control', 'no-store');
    }
}
