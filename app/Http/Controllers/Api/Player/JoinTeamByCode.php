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
            'contact' => ['required_with:claim_code', 'string', 'max:255'],
            'claim_code' => ['required_with:contact', 'string', 'size:12'],
            'phone' => ['required_without:claim_code', 'string', 'min:10', 'max:32'],
            'team_code' => ['required_without:claim_code', 'string', 'size:6'],
        ]);

        try {
            $result = isset($validated['claim_code'])
                ? $claims->claimWithCode($validated['contact'], $validated['claim_code'], $request)
                : $claims->claim($validated['phone'], $validated['team_code'], $request);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'The contact detail or claim code is invalid, expired, or already used.',
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
            'message' => 'Player profile found. Create your login to finish claiming it.',
            'status' => 'success',
            'data' => $result,
        ]);
    }
}
