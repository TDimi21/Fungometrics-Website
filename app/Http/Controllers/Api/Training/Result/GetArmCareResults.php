<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Models\ArmCareSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetArmCareResults extends Controller
{
    /**
     * List recent Arm Care sessions.
     *
     * Defaults to the authenticated user. A coach may pass ?team_id= to see
     * the team's compliance, or ?user_id= for a single player. Optional
     * ?limit= (default 30, max 200).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->query('limit', 30) ?: 30, 200);

            $query = ArmCareSession::query()
                ->with('profile')
                ->orderByDesc('performed_at');

            if ($request->filled('team_id')) {
                $query->where('team_id', $request->query('team_id'));
            } elseif ($request->filled('user_id')) {
                $query->where('user_id', $request->query('user_id'));
            } else {
                $query->where('user_id', $request->user()?->id);
            }

            $sessions = $query->limit($limit)->get();

            $response = [
                'code' => '013',
                'message' => 'arm care sessions',
                'status' => 'success',
                'data' => $sessions,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '013-E',
                'message' => 'error fetching arm care sessions',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
