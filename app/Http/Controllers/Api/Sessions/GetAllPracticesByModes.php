<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PracticesTeamList;
use App\Models\Practice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetAllPracticesByModes extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            // Only training-mode sessions (EV / WB / LT). Without this filter the query
            // returned the 10 most-recent practices of ALL types, and the client filtered
            // for training modes — so training sessions vanished once newer batting/cage
            // sessions pushed them off page 1. Filtering server-side keeps pagination correct.
            $trainingModes = ['EV', 'WB', 'LT'];
            if ($request->team) {
                $practices = Practice::where('team_id', $request->team)->whereIn('modes', $trainingModes)->orderBy('updated_at', 'desc')->paginate(10);
            } else {
                $practices = Practice::where('user_id', Auth::id())->whereIn('modes', $trainingModes)->orderBy('updated_at', 'desc')->paginate(10);
            }

            if (0 === count($practices)) {
                throw new NotFound();
            }
            $practicesCollection = PracticesTeamList::collection($practices)->response()->getData(true);

            $response = [
                'code' => '025',
                'message' => 'all trainings',
                'status' => 'success',
                'data' => $practicesCollection['data'],
                'links' => $practicesCollection['links'],
                'meta' => $practicesCollection['meta']
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '025-E',
                'message' => 'Not Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
