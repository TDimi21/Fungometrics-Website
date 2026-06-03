<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Utils\Filters\BattingFilters;
use App\Utils\Filters\BullpenFilters;
use App\Utils\Filters\CageFilters;
use App\Utils\Filters\ExitVelocityFilters;
use App\Utils\Filters\LiveABFilters;
use App\Utils\Filters\LongTossFilters;
use App\Utils\Filters\WeightBallFilters;
use App\Models\PlayerTeam;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerFilteredStatistics extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $playerId = (string) $request->route('player');
            $authId = (string) optional($request->user())->id;

            if (!$authId || $authId !== $playerId) {
                return response()->json([
                    'code' => '046-E',
                    'message' => 'unauthorized user',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'dates' => ['required', 'array'],
                'options' => ['required'],
                'players' => ['nullable', 'array'],
            ]);

            $teamId = (string) (
                PlayerTeam::query()
                    ->where('user_id', $playerId)
                    ->orderByDesc('actual')
                    ->value('team_id')
            );

            if (!$teamId) {
                return response()->json([
                    'code' => '046',
                    'message' => '',
                    'status' => 'success',
                    'data' => [
                        'batting' => [],
                        'bullpen' => [],
                        'cage' => [],
                        'weight_ball' => [],
                        'exit_velocity' => [],
                        'long_toss' => [],
                        'live' => [],
                    ],
                ], HttpCodes::HTTP_OK);
            }

            $options = $validated['options'];
            if (!is_array($options)) {
                $options = json_decode((string) $options, true, 512, JSON_THROW_ON_ERROR);
            }

            $params = [
                'team' => $teamId,
                'players' => [$playerId],
                'dates' => $validated['dates'],
                'options' => $options,
            ];

            $data = [
                'batting' => (new BattingFilters())->handle($params),
                'bullpen' => (new BullpenFilters())->handle($params),
                'cage' => (new CageFilters())->handle($params),
                'weight_ball' => (new WeightBallFilters())->handle($params),
                'exit_velocity' => (new ExitVelocityFilters())->handle($params),
                'long_toss' => (new LongTossFilters())->handle($params),
                'live' => (new LiveABFilters())->handle($params),
            ];

            return response()->json([
                'code' => '046',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return response()->json([
                'code' => '046-E',
                'message' => '',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
