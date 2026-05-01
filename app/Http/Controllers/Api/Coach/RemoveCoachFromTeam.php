<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class RemoveCoachFromTeam extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $remove = CoachTeam::findOrFail($request->id)->forceDelete();
            $response = [
                'code' => '056',
                'message' => 'coach remove from team',
                'status' => 'success',
                'data' => $remove,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {

            $response = [
                'code' => '056-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
