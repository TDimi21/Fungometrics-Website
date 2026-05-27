<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\ScriptedBpPlanRequest;
use App\Models\ScriptedBpPlan;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveScriptedBpPlan extends Controller
{
    public function __invoke(ScriptedBpPlanRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Upsert — one plan per practice, replace if re-submitted
            $plan = ScriptedBpPlan::withTrashed()
                ->where('practice_id', $data['practice_id'])
                ->first();

            if ($plan) {
                $plan->restore();
                $plan->update(['rounds' => $data['rounds']]);
            } else {
                $plan = (new CreateServiceData(new ScriptedBpPlan()))->handle($data);
            }

            return response()->json([
                'code'    => '010',
                'message' => 'scripted bp plan saved',
                'status'  => 'success',
                'data'    => $plan,
            ], HttpCodes::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'code'    => '010-E',
                'message' => 'error saving scripted bp plan',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
