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
        try {
            $practices = Practice::with([
                'exitVelocity',
                'batting',
                'bullpen',
                'weightBall',
                'longToss',
                'cage',
                'lineup.user.profile',
                'lineup.user.player'
            ])
                ->where(
                    'team_id',
                    $request->team
                )
                ->orderBy('updated_at')
                ->limit(70)->get();


            $practicesliveIds = TeamsLiveAB::where('team_id', '=', $request->team)
                ->pluck('practice_id')
                ->unique()
                ->all();
            $practiceslive = Practice::with([
                'live',
                'lineup.user.profile',
                'lineup.user.player'
            ])
                ->whereIn(
                    'id',
                    $practicesliveIds
                )
                ->orderBy('updated_at')
                ->limit(10)->get()->all();
            if (0 === $practices->count()) {
                throw new NotFound();
            }

            $bullpen = $practices->where('type', PracticeTypes::BULLPEN->value)->take(10)->all();
            $cage = $practices->where('type', PracticeTypes::CAGE->value)->take(10)->all();
            $live = $practiceslive;
            $batting = $practices->where('type', PracticeTypes::BATTING->value)->take(10)->all();
            $wb = $practices->where('type', PracticeTypes::TRAINING->value)
                ->where('modes', PracticeModes::WEIGHT_BALL->value)
                ->take(10)->all();
            $lt = $practices->where('type', PracticeTypes::TRAINING->value)
                ->where('modes', PracticeModes::LONG_TOSS->value)
                ->take(10)->all();
            $ev = $practices->where('type', PracticeTypes::TRAINING->value)
                ->where('modes', PracticeModes::EXIT_VELOCITY->value)
                ->take(10)->all();

            $response = [
                'code' => '021',
                'message' => '',
                'status' => 'success',
                'data' => [
                    'bullpen'=>LastSession::collection($bullpen),
                    'batting'=>LastSession::collection($batting),
                    'live'=>LastSession::collection($live),
                    'cage'=>LastSession::collection($cage),
                    'weight_ball'=>LastSession::collection($wb),
                    'long_toss'=>LastSession::collection($lt),
                    'exit_velocity'=>LastSession::collection($ev)
                ],
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '021-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
