<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PracticesTeamList;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTeamsPracticesSessionByMode extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            if (Auth::user()->type === UserTypes::COACH->value) {
                $teams = CoachTeam::where('coach_id', Auth::id())
                    ->pluck('team_id')
                    ->all();
                $practices = Practice::with('team')
                    ->where(['modes' => $request->modes])
                    ->whereIn('team_id', $teams)->paginate();
            } else {
                $practices = Practice::with('team')
                    ->where('user_id', Auth::id())
                    ->where(['modes' => $request->modes])
                    ->paginate();
            }
            if (0 === count($practices)) {
                throw new NotFound();
            }
            $practicesCollection = PracticesTeamList::collection($practices)->response()->getData(true);
            $response = [
                'code' => '024',
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
                'code' => '024-E',
                'message' => 'Not Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
