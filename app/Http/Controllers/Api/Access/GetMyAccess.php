<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Access;

use App\Http\Controllers\Controller;
use App\Services\Access\EntitlementResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

class GetMyAccess extends Controller
{
    public function __invoke(Request $request, EntitlementResolver $resolver): JsonResponse
    {
        $request->validate(['team_id' => ['nullable', 'uuid']]);
        $teamId = $request->query('team_id');

        try {
            return response()->json([
                'success' => true,
                'data' => $resolver->getAccessSummary($request->user(), $teamId),
            ]);
        } catch (UnauthorizedException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'team_id' => $teamId,
            ], 403);
        }
    }
}
