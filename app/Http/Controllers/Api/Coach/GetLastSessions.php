<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\LastSession;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\TeamsLiveAB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetLastSessions extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $teamId   = $request->team;
        $cacheKey = "last_sessions_{$teamId}";

        try {
            $data = Cache::remember($cacheKey, 300, function () use ($teamId): array {
                $practices = Practice::with([
                    'lineup.user.profile',
                    'lineup.user.player'
                ])
                    ->withCount([
                        'batting',
                        'scriptedBpSwings',
                        'bullpen',
                        'cage',
                        'live',
                        'longToss',
                        'weightBall',
                        'exitVelocity',
                    ])
                    ->where('team_id', $teamId)
                    ->orderByDesc('updated_at')
                    ->limit(70)->get();

                $practicesliveIds = TeamsLiveAB::where('team_id', '=', $teamId)
                    ->pluck('practice_id')->unique()->all();
                $practiceslive = Practice::with([
                    'live',
                    'lineup.user.profile',
                    'lineup.user.player'
                ])
                    ->withCount([
                        'batting',
                        'scriptedBpSwings',
                        'bullpen',
                        'cage',
                        'live',
                        'longToss',
                        'weightBall',
                        'exitVelocity',
                    ])
                    ->whereIn('id', $practicesliveIds)
                    ->orderByDesc('updated_at')
                    ->limit(10)->get()->all();

                if (0 === $practices->count()) {
                    return [];
                }

                $bullpen = $practices->where('type', PracticeTypes::BULLPEN->value)->take(10)->all();
                $cage    = $practices->where('type', PracticeTypes::CAGE->value)->take(10)->all();
                $batting = $practices->where('type', PracticeTypes::BATTING->value)->take(10)->all();
                $wb      = $practices->where('type', PracticeTypes::TRAINING->value)
                    ->where('modes', PracticeModes::WEIGHT_BALL->value)->take(10)->all();
                $lt      = $practices->where('type', PracticeTypes::TRAINING->value)
                    ->where('modes', PracticeModes::LONG_TOSS->value)->take(10)->all();
                $ev      = $practices->where('type', PracticeTypes::TRAINING->value)
                    ->where('modes', PracticeModes::EXIT_VELOCITY->value)->take(10)->all();

                return [
                    'bullpen'        => LastSession::collection($bullpen)->resolve(),
                    'batting'        => LastSession::collection($batting)->resolve(),
                    'live'           => LastSession::collection($practiceslive)->resolve(),
                    'cage'           => LastSession::collection($cage)->resolve(),
                    'weight_ball'    => LastSession::collection($wb)->resolve(),
                    'long_toss'      => LastSession::collection($lt)->resolve(),
                    'exit_velocity'  => LastSession::collection($ev)->resolve(),
                ];
            });

            if (empty($data)) {
                throw new NotFound();
            }

            return response()->json([
                'code' => '021', 'message' => '', 'status' => 'success', 'data' => $data,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code' => '021-E', 'message' => ' ', 'status' => 'error', 'data' => [],
            ], HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
