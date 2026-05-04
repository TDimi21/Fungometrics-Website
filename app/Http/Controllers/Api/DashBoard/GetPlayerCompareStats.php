<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Models\BattingPracticeResult;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\Profile;
use App\Utils\Helper;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerCompareStats extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $playerId = $request->player;

            $results = BattingPracticeResult::where('batter_id', $playerId)->get();

            $total = $results->count();

            $gb   = $results->where('type_of_hit', BattingTrajectory::GROUND_BALL->value)->count();
            $ld   = $results->where('type_of_hit', BattingTrajectory::LINE_DRIVE->value)->count();
            $fly  = $results->where('type_of_hit', BattingTrajectory::FLY_BALL->value)->count() +
                    $results->where('type_of_hit', BattingTrajectory::POP_FLY->value)->count();
            $sm   = $results->where('type_of_hit', BattingTrajectory::SWING_MISS->value)->count();
            $foul = $results->where('type_of_hit', BattingTrajectory::FOUL->value)->count();
            $smf  = $sm + $foul;
            $take = $results->where('type_of_hit', BattingTrajectory::TAKE->value)
                ->whereNotIn('quality_of_contact', [ContactQuality::HARD->value, ContactQuality::AVERAGE->value, ContactQuality::WEAK->value])
                ->count();

            $effective = $gb + $ld + $fly + $smf + $take;

            // Average exit velocity per trajectory
            $avgVelo = function ($rows) {
                $velos = $rows->filter(fn($r) => $r->velocity > 0)->pluck('velocity');
                return $velos->count() ? round($velos->avg(), 1) : null;
            };

            $gbRows  = $results->where('type_of_hit', BattingTrajectory::GROUND_BALL->value);
            $ldRows  = $results->where('type_of_hit', BattingTrajectory::LINE_DRIVE->value);
            $flyRows = $results->filter(fn($r) => in_array($r->type_of_hit, [
                BattingTrajectory::FLY_BALL->value, BattingTrajectory::POP_FLY->value
            ]));

            // Quality of contact counts + avg velo
            $hardRows = $results->where('quality_of_contact', ContactQuality::HARD->value);
            $avgRows  = $results->where('quality_of_contact', ContactQuality::AVERAGE->value);
            $weakRows = $results->where('quality_of_contact', ContactQuality::WEAK->value);
            $hardCount = $hardRows->count();
            $avgCount  = $avgRows->count();
            $weakCount = $weakRows->count();
            $qualityTotal = $hardCount + $avgCount + $weakCount;

            $profile = Profile::where('user_id', $playerId)->first();

            $data = [
                'id'     => $playerId,
                'name'   => $profile ? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')) : 'Unknown',
                'avatar' => $profile->avatar ?? null,
                'total'  => $total,
                'stats'  => [
                    'GB'   => ['count' => $gb,   'percent' => round(Helper::caseDivide($gb,   $effective) * 100), 'avg_velo' => $avgVelo($gbRows)],
                    'LD'   => ['count' => $ld,   'percent' => round(Helper::caseDivide($ld,   $effective) * 100), 'avg_velo' => $avgVelo($ldRows)],
                    'FLY'  => ['count' => $fly,  'percent' => round(Helper::caseDivide($fly,  $effective) * 100), 'avg_velo' => $avgVelo($flyRows)],
                    'SM/F' => ['count' => $smf,  'percent' => round(Helper::caseDivide($smf,  $effective) * 100), 'avg_velo' => null],
                    'TAKE' => ['count' => $take, 'percent' => round(Helper::caseDivide($take, $effective) * 100), 'avg_velo' => null],
                ],
                'quality_velo' => [
                    'H' => ['count' => $hardCount, 'percent' => round(Helper::caseDivide($hardCount, $qualityTotal) * 100), 'avg_velo' => $avgVelo($hardRows)],
                    'A' => ['count' => $avgCount,  'percent' => round(Helper::caseDivide($avgCount,  $qualityTotal) * 100), 'avg_velo' => $avgVelo($avgRows)],
                    'W' => ['count' => $weakCount, 'percent' => round(Helper::caseDivide($weakCount, $qualityTotal) * 100), 'avg_velo' => $avgVelo($weakRows)],
                ],
            ];

            return response()->json([
                'code'    => '048',
                'message' => '',
                'status'  => 'success',
                'data'    => $data,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code'   => '048-E',
                'status' => 'error',
                'data'   => [],
            ], HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
