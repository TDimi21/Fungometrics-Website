<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\PlannerCustomDrill;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: the shared community drill library — browse drills other coaches have
 * made public. Powers the future "look through other coaches' drills" area.
 * Filterable by ?q= (name), ?bucket=, ?category_group=.
 */
class GetDrillLibrary extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $q       = trim((string) $request->query('q', ''));
            $bucket  = trim((string) $request->query('bucket', ''));
            $group   = trim((string) $request->query('category_group', ''));
            $limit   = min((int) $request->query('limit', 100), 200);

            $drills = PlannerCustomDrill::query()
                ->where('visibility', 'public')
                ->when($bucket !== '', fn ($query) => $query->where('bucket', $bucket))
                ->when($group !== '', fn ($query) => $query->where('category_group', $group))
                ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'code'    => '099',
                'message' => 'drill library',
                'status'  => 'success',
                'data'    => $drills->map->toDrillArray()->values(),
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetDrillLibrary: ' . $e->getMessage());

            return response()->json([
                'code'    => '099-E',
                'message' => 'failed to fetch drill library',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
