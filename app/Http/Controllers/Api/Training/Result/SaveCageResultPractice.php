<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\CageRequest;
use App\Models\CagePracticeResult;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveCageResultPractice extends Controller
{
    /**
     * @param CageRequest $request
     * @return JsonResponse
     */
    public function __invoke(CageRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $count = CagePracticeResult::where('practice_id', '=', $request->practice_id)
                ->count();
            $data = $request->validated();
            $data['sort']= $count++;
            $result = (new CreateServiceData(new CagePracticeResult()))->handle($data);
            DB::commit();

            $teamId = $result->team_id ?? null;
            if ($teamId) {
                Cache::forget("last_sessions_{$teamId}");
                Cache::forget("performance_overview_{$teamId}");
                Cache::forget("dashboard_graphics_{$teamId}");
            }

            $response = [
                'code' => '014',
                'message' => 'save cage practice',
                'status' => 'success',
                'data' => $result,
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '014-E',
                'message' => 'error to save cage practice',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
