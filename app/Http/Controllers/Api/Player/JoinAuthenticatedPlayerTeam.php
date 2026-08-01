<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Services\Security\PlayerProfileClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JoinAuthenticatedPlayerTeam extends Controller
{
    public function __invoke(Request $request, PlayerProfileClaimService $claims): JsonResponse
    {
        $validated = $request->validate(['team_code' => ['required', 'string', 'size:6']]);
        $team = $claims->joinAuthenticatedPlayer($request->user(), $validated['team_code'], $request);

        return response()->json([
            'code' => '018',
            'message' => 'Joined team successfully.',
            'status' => 'success',
            'data' => ['team' => $team],
        ]);
    }
}
