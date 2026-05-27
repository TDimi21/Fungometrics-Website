<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\ScriptedBpSwingRequest;
use App\Models\ScriptedBpSwing;
use App\Services\Statistics\ScriptedBpScoringService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveScriptedBpSwing extends Controller
{
    public function __invoke(ScriptedBpSwingRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Compute score
            $scoring = new ScriptedBpScoringService();
            $result  = $scoring->score(
                roundType:    $data['round_type'],
                contactType:  $data['contact_type'],
                trajectory:   $data['trajectory']    ?? null,
                direction:    $data['direction']      ?? null,
                exitVelocity: isset($data['exit_velocity']) ? (int) $data['exit_velocity'] : null,
            );

            // Sort = total swings for this batter in this practice so far
            $sort = ScriptedBpSwing::where('practice_id', $data['practice_id'])
                ->where('batter_id', $data['batter_id'])
                ->count();

            $swing = ScriptedBpSwing::create([
                ...$data,
                'raw_score'       => $result['score'],
                'score_modifiers' => $result['modifiers'],
                'sort'            => $sort,
            ]);

            DB::commit();

            return response()->json([
                'code'    => '011',
                'message' => 'scripted bp swing saved',
                'status'  => 'success',
                'data'    => [
                    'swing'     => $swing,
                    'score'     => $result['score'],
                    'modifiers' => $result['modifiers'],
                ],
            ], HttpCodes::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'code'    => '011-E',
                'message' => 'error saving scripted bp swing',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
