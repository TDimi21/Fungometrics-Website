<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Access;

use App\Http\Controllers\Controller;
use App\Services\Access\AdministrativeAccess;
use App\Services\Access\EntitlementResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

class GetMyAccess extends Controller
{
    public function __invoke(
        Request $request,
        EntitlementResolver $resolver,
        AdministrativeAccess $administrativeAccess
    ): JsonResponse
    {
        $request->validate(['team_id' => ['nullable', 'uuid']]);
        $teamId = $request->query('team_id');

        try {
            $summary = $resolver->getAccessSummary($request->user(), $teamId);
            $summary['capabilities'] = $administrativeAccess->capabilities($request->user());

            return response()->json([
                'success' => true,
                'data' => $summary,
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
