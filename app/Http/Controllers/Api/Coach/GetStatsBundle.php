<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Sessions\Results\GetBattingPracticeResults;
use App\Http\Controllers\Api\Sessions\Results\GetBullpenPracticeResults;
use App\Http\Controllers\Api\Sessions\Results\GetCagePracticeResults;
use App\Http\Controllers\Api\Sessions\Results\GetLiveABPracticeResults;
use App\Http\Controllers\Api\Sessions\GetExitVelocityPracticeResult;
use App\Http\Controllers\Api\Sessions\GetLongTossPracticeResult;
use App\Http\Controllers\Api\Sessions\GetWeightBallPracticeResult;
use App\Models\Practice;
use App\Models\TeamsLiveAB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * One call that returns the recent sessions' detail for a team, bundled by type.
 *
 * The Stats screen used to fetch each session's detail individually (an N+1 that,
 * with the 60 req/min limit, made the screen slow). This collapses those into a
 * single response by invoking the SAME per-session detail controllers server-side
 * — so the payload is byte-for-byte what the app already parses, and all scoring
 * stays on the client (no risk of server/client divergence).
 */
class GetStatsBundle extends Controller
{
    /** type => [detail controller, Practice relation that marks the type] */
    private array $types = [
        'batting'      => [GetBattingPracticeResults::class, 'batting'],
        'bullpen'      => [GetBullpenPracticeResults::class, 'bullpen'],
        'cage'         => [GetCagePracticeResults::class, 'cage'],
        'liveab'       => [GetLiveABPracticeResults::class, 'live'],
        'exitvelocity' => [GetExitVelocityPracticeResult::class, 'exitVelocity'],
        'longtoss'     => [GetLongTossPracticeResult::class, 'longToss'],
        'weightball'   => [GetWeightBallPracticeResult::class, 'weightBall'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teamId = (string) $request->route('team');
            $limit  = (int) ($request->query('limit', 12));
            $limit  = max(1, min(20, $limit));

            $bundle = [];
            foreach ($this->types as $type => [$controllerClass, $relation]) {
                $practiceIds = $this->recentPracticeIds($type, $relation, $teamId, $limit);
                $bundle[$type] = [];
                foreach ($practiceIds as $pid) {
                    $body = $this->invokeDetail($controllerClass, (string) $pid);
                    if ($body !== null) {
                        $bundle[$type][(string) $pid] = $body;
                    }
                }
            }

            return response()->json([
                'code'    => '090',
                'message' => 'stats session bundle',
                'status'  => 'success',
                'data'    => $bundle,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetStatsBundle: ' . $e->getMessage());

            return response()->json([
                'code'    => '090-E',
                'message' => 'failed to build stats bundle',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Recent practice ids of a given type for the team. */
    private function recentPracticeIds(string $type, string $relation, string $teamId, int $limit): array
    {
        $query = Practice::where('team_id', $teamId);

        if ($type === 'batting') {
            // Batting includes scripted BP swings.
            $query->where(function ($q): void {
                $q->whereHas('batting')->orWhereHas('scriptedBpSwings');
            });
        } else {
            $query->whereHas($relation);
        }

        $ids = $query->orderByDesc('updated_at')->limit($limit)->pluck('id')->all();

        // LiveAB sessions can also be keyed via the opposite-side TeamsLiveAB join.
        if ($type === 'liveab') {
            $joinIds = TeamsLiveAB::where('team_id', $teamId)->pluck('practice_id')->all();
            if (! empty($joinIds)) {
                $extra = Practice::whereIn('id', $joinIds)
                    ->orderByDesc('updated_at')
                    ->limit($limit)
                    ->pluck('id')
                    ->all();
                $ids = array_values(array_unique(array_merge($ids, $extra)));
            }
        }

        return array_slice($ids, 0, $limit);
    }

    /** Run a per-session detail controller and return its decoded response body. */
    private function invokeDetail(string $controllerClass, string $practiceId): ?array
    {
        try {
            $controller = app($controllerClass);
            $req = Request::create('/', 'GET', ['practice' => $practiceId]);
            $req->setUserResolver(fn () => auth()->user());

            $response = $controller->__invoke($req);
            $body = json_decode($response->getContent(), true);

            if (! is_array($body)) {
                return null;
            }

            // Only keep successful payloads (skip "record not found" etc.).
            $ok = ($body['status'] ?? null) === 'success' || ! empty($body['data']);

            return $ok ? $body : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
