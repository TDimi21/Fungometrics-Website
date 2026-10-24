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

            $practice = \App\Models\Practice::whereKey($data['practice_id'])->lockForUpdate()->firstOrFail();
            if ($practice->is_completed || $practice->type !== 'B') {
                DB::rollBack();
                return response()->json(['message' => 'This batting session cannot accept swings.'], 422);
            }
            $webResult = $data['web_result'] ?? null;
            unset($data['web_result']);
            if ($webResult !== null) {
                $plan = \App\Models\ScriptedBpPlan::where('practice_id', $practice->id)->first();
                $round = collect($plan?->rounds ?? [])->firstWhere('round_type', $data['round_type']);
                $taken = ScriptedBpSwing::where('practice_id', $practice->id)->where('batter_id', $data['batter_id'])->where('round_type', $data['round_type'])->count();
                if (!$round || $taken >= $round['swing_count'] || $data['round_swing_index'] !== $taken + 1) {
                    DB::rollBack();
                    return response()->json(['message' => 'Round progress changed. Reload saved results.'], 409);
                }
            }
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

            $battingResult = null;
            if ($webResult !== null) {
                $miss = $data['contact_type'] === 'Miss';
                $battingResult = \App\Models\BattingPracticeResult::create([
                    ...$webResult,
                    'practice_id' => $practice->id, 'team_id' => $practice->team_id, 'batter_id' => $data['batter_id'],
                    'quality_of_contact' => ['Barrel'=>'H', 'Hard'=>'H', 'Average'=>'A', 'Weak'=>'W', 'Miss'=>'M'][$data['contact_type']],
                    'type_of_hit' => $miss ? 'SM' : (['LineDrive'=>'LD','FlyBall'=>'FB','GroundBall'=>'GB','PopUp'=>'PF','Foul'=>'F'][$data['trajectory'] ?? ''] ?? 'N'),
                    'is_contact' => !$miss, 'velocity' => $miss ? 0 : ($data['exit_velocity'] ?? 0),
                    'field_mark' => $miss ? null : ($webResult['field_mark'] ?? null),
                    'field_direction' => $miss ? null : ($webResult['field_direction'] ?? null),
                    'sort' => \App\Models\BattingPracticeResult::where('practice_id', $practice->id)->count(),
                    'is_in_match' => false,
                ]);
            }
            DB::commit();
            if ($battingResult && $practice->team_id) {
                foreach (['last_sessions_', 'performance_overview_', 'dashboard_graphics_'] as $prefix) {
                    \Illuminate\Support\Facades\Cache::forget($prefix.$practice->team_id);
                }
            }

            return response()->json([
                'code'    => '011',
                'message' => 'scripted bp swing saved',
                'status'  => 'success',
                'data'    => [
                    'swing'     => $swing,
                    'batting_result' => $battingResult,
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
