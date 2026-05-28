<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Http\Controllers\Controller;
use App\Models\ScriptedBpPlan;
use App\Models\ScriptedBpSwing;
use App\Services\Statistics\ScriptedBpScoringService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetScriptedBpResults extends Controller
{
    public function __invoke(string $practice): JsonResponse
    {
        try {
            $plan   = ScriptedBpPlan::where('practice_id', $practice)->first();
            $swings = ScriptedBpSwing::where('practice_id', $practice)
                ->with('batter.profile')
                ->orderBy('batter_id')
                ->orderBy('sort')
                ->get();

            $scoring = new ScriptedBpScoringService();

            // Group swings by batter, then by round
            $byBatter = [];
            foreach ($swings as $swing) {
                $batterId = $swing->batter_id;
                if (!isset($byBatter[$batterId])) {
                    $profile     = $swing->batter?->profile;
                    $firstName   = $profile?->first_name ?? '';
                    $lastName    = $profile?->last_name  ?? '';
                    $byBatter[$batterId] = [
                        'batter_name'  => trim("$firstName $lastName") ?: null,
                        'rounds'       => [],
                        'total_swings' => 0,
                        'total_score'  => 0,
                    ];
                }
                $rt = $swing->round_type;
                if (!isset($byBatter[$batterId]['rounds'][$rt])) {
                    $byBatter[$batterId]['rounds'][$rt] = ['swings' => [], 'round_score' => 0, 'swing_count' => 0];
                }
                $byBatter[$batterId]['rounds'][$rt]['swings'][]     = $swing;
                $byBatter[$batterId]['rounds'][$rt]['round_score']  += $swing->raw_score;
                $byBatter[$batterId]['rounds'][$rt]['swing_count']++;
                $byBatter[$batterId]['total_score']                 += $swing->raw_score;
                $byBatter[$batterId]['total_swings']++;
            }

            // Compute averages and grades
            $batterResults = [];
            foreach ($byBatter as $batterId => $data) {
                $avgScore = $data['total_swings'] > 0
                    ? $data['total_score'] / $data['total_swings']
                    : 0;

                $rounds = [];
                foreach ($data['rounds'] as $rt => $rData) {
                    $roundAvg = $rData['swing_count'] > 0
                        ? $rData['round_score'] / $rData['swing_count']
                        : 0;
                    $rounds[$rt] = [
                        'round_type'   => $rt,
                        'swing_count'  => $rData['swing_count'],
                        'round_score'  => $rData['round_score'],
                        'avg_score'    => round($roundAvg, 2),
                        'grade'        => $scoring->grade($roundAvg),
                        'swings'       => $rData['swings'],
                    ];
                }

                $batterResults[] = [
                    'batter_id'    => $batterId,
                    'batter_name'  => $data['batter_name'],
                    'total_swings' => $data['total_swings'],
                    'total_score'  => $data['total_score'],
                    'avg_score'    => round($avgScore, 2),
                    'grade'        => $scoring->grade($avgScore),
                    'rounds'       => array_values($rounds),
                ];
            }

            return response()->json([
                'code'    => '012',
                'message' => 'scripted bp results',
                'status'  => 'success',
                'data'    => [
                    'practice_id' => $practice,
                    'plan'        => $plan,
                    'batters'     => $batterResults,
                ],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'code'    => '012-E',
                'message' => 'error fetching scripted bp results',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
