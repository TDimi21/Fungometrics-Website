<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\CoachesResource;
use App\Models\CoachTeam;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetCoachesList extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $coachTeams = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();
            $coachCoaches = CoachTeam::with('profile')->whereIn('team_id', $coachTeams)->get();
            $coachesData = $coachCoaches->filter(fn ($item) => $item->coach_id !== Auth::id());
            if (0 === $coachesData->count()) {
                throw new NotFound();
            }
            $response = [
                'code' => '031',
                'message' => '',
                'status' => 'success',
                'data' => CoachesResource::collection($coachesData),
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '031-E',
                'message' => 'Not Data Found other coaches',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
