<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PracticesTeamList;
use App\Models\CoachTeam;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\TeamsLiveAB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTeamsPracticesSessionsByType extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        try {
            if (Auth::user()->type === UserTypes::COACH->value) {
                $teams = CoachTeam::where('coach_id', Auth::id())
                    ->pluck('team_id')
                    ->all();

                $practiceQuery = Practice::select('practices.*')
                    ->with('team', 'lineup.user.profile')
                    ->where('type', '=', $request->type)
                    ->when(request('search'), function ($query) use ($request): void {
                        collect(explode(' ', $request->search))->filter()->each(function ($term) use ($query): void {
                            $query->where(function ($query) use ($term): void {
                                $query->where('note', 'like', "%{$term}%")
                                    ->orWhereHas('lineup', function ($query) use ($term): void {
                                        $query->whereHas('user', function ($query) use ($term): void {
                                            $query->whereHas('profile', function ($query) use ($term): void {
                                                $query->where('first_name', 'like', "%{$term}%")
                                                    ->orWhere('last_name', 'like', "%{$term}%");
                                            });
                                        });
                                    });
                            });
                        });
                    });

                // LiveAB sessions can involve two teams. Show a session when EITHER
                // its practices.team_id is one of the coach's teams (covers legacy
                // sessions and the canonical team) OR it appears in the TeamsLiveAB
                // join (covers sessions where team_id is the opposite side). Using
                // only the join breaks any practice that has no TeamsLiveAB rows yet
                // (e.g. sessions created before that table was populated), which made
                // an empty whereIn() hide every LiveAB session.
                if ((string) $request->type === PracticeTypes::LIVE_AB->value) {
                    $livePracticeIds = TeamsLiveAB::whereIn('team_id', $teams)
                        ->distinct()
                        ->pluck('practice_id')
                        ->all();

                    $practiceQuery->where(function ($query) use ($teams, $livePracticeIds): void {
                        $query->whereIn('team_id', $teams);
                        if (!empty($livePracticeIds)) {
                            $query->orWhereIn('id', $livePracticeIds);
                        }
                    });
                } else {
                    $practiceQuery->whereIn('team_id', $teams);
                }

                $practices = $practiceQuery->paginate();
            } else {
                $practices = Practice::with('team')
                    ->where('user_id', Auth::id())
                    ->where(['type' => $request->type])
                    ->paginate();
            }
            // return $practices;
            if (0 === count($practices)) {
                throw new NotFound();
            }
            $practicesCollection = PracticesTeamList::collection($practices)->response()->getData(true);
            $response = [
                'code' => '017',
                'message' => '',
                'status' => 'success',
                'data' => $practicesCollection['data'],
                'links' => $practicesCollection['links'],
                'meta' => $practicesCollection['meta']
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '017-E',
                'message' => 'Not Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
