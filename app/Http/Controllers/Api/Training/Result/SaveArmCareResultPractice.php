<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\ArmCareRequest;
use App\Models\ArmCareSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveArmCareResultPractice extends Controller
{
    /**
     * Persist a completed Arm Care session.
     *
     * Idempotent on (user_id, client_id) so the offline-first app can safely
     * retry a POST whose response was lost without creating a duplicate.
     *
     * @param ArmCareRequest $request
     * @return JsonResponse
     */
    public function __invoke(ArmCareRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['performed_at'] = $data['performed_at'] ?? now();

            $clientId = $data['client_id'] ?? null;

            if ($clientId) {
                $session = ArmCareSession::updateOrCreate(
                    ['user_id' => $data['user_id'], 'client_id' => $clientId],
                    $data,
                );
            } else {
                $session = ArmCareSession::create($data);
            }

            DB::commit();

            $response = [
                'code' => '012',
                'message' => 'save arm care session result',
                'status' => 'success',
                'data' => ['result' => $session],
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '012-E',
                'message' => 'error to save arm care session result',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
