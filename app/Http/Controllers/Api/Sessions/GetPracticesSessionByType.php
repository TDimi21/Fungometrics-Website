<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PracticesTeamList;
use App\Models\Practice;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPracticesSessionByType extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            if ($request->team_id) {
                $practices = Practice::where(['type' => $request->type, 'team_id' => $request->team_id])->paginate();
            } else {
                $practices = Practice::where(['type' => $request->type, 'user_id' => Auth::id()])->paginate();
            }

            if (0 === count($practices)) {
                throw new NotFound();
            }
            $practicesCollection = PracticesTeamList::collection($practices)->response()->getData(true);
            $response = [
                'code' => '030',
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
                'code' => '030-E',
                'message' => 'Not Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
