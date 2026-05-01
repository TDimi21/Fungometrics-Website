<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListLiveABPracticesResource;
use App\Models\CoachTeam;
use App\Models\Practice;
use App\Models\TeamsLiveAB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetListLiveABSessions extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teams = CoachTeam::where('coach_id', Auth::id())
                ->pluck('team_id')
                ->all();
            if (0 ===count($teams)) {
                throw new NotFound();
            }
            $teamsPractices = TeamsLiveAB::whereIn('team_id', $teams)
                ->distinct()
                ->get(['practice_id'])->pluck('practice_id')->all();
            if (0 ===count($teamsPractices)) {
                throw new NotFound();
            }
            $practices = Practice::with('lineup', 'liveABTeams.team')->whereIn('id', $teamsPractices)
                ->paginate();

            $practicesCollection = ListLiveABPracticesResource::collection($practices)->response()->getData(true);
            if (0 ===$practices->count()) {
                throw new NotFound();
            }
            $response = [
                'code' => '042',
                'message' => 'list of practices session liveab',
                'status' => 'success',
                'data' =>$practicesCollection['data'],
                'links' => $practicesCollection['links'],
                'meta' => $practicesCollection['meta']
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '042-E',
                'message' => 'Not Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
