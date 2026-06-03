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
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\WeightBallPractice;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use Carbon\Carbon;
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

            $options = $validated['options'];
            if (!is_array($options)) {
                $options = json_decode((string) $options, true, 512, JSON_THROW_ON_ERROR);
            }
            $options = $this->normalizeOptions($options);

            $params = [
                'team' => '',
                'players' => [$playerId],
                'dates' => $this->normalizeDates($validated['dates']),
                'options' => $options,
            ];

            $data = [
                'batting' => $this->battingFilters($params),
                'bullpen' => $this->bullpenFilters($params),
                'cage' => $this->cageFilters($params),
                'weight_ball' => $this->weightBallFilters($params),
                'exit_velocity' => $this->exitVelocityFilters($params),
                'long_toss' => $this->longTossFilters($params),
                'live' => $this->liveABFilters($params),
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

    private function normalizeOptions(array $options): array
    {
        foreach ($options as $sessionType => $selectedOptions) {
            if (!is_array($selectedOptions)) {
                $options[$sessionType] = [];
                continue;
            }

            $options[$sessionType] = array_values(array_map(
                static fn ($value): int => (int) $value,
                array_filter($selectedOptions, static fn ($value): bool => is_numeric($value))
            ));
        }

        return $options;
    }

    private function resolveTeamId(string $playerId): string
    {
        $teamId = (string) (
            PlayerTeam::query()
                ->where('user_id', $playerId)
                ->orderByDesc('actual')
                ->value('team_id')
        );

        if ($teamId !== '') {
            return $teamId;
        }

        $teamId = (string) Practice::query()
            ->where('user_id', $playerId)
            ->whereNotNull('team_id')
            ->orderByDesc('created_at')
            ->value('team_id');

        if ($teamId !== '') {
            return $teamId;
        }

        $teamId = (string) BattingPracticeResult::query()
            ->where('batter_id', $playerId)
            ->whereNotNull('team_id')
            ->orderByDesc('created_at')
            ->value('team_id');

        if ($teamId !== '') {
            return $teamId;
        }

        $teamId = (string) BullpenPracticeResult::query()
            ->where('pitcher_id', $playerId)
            ->whereNotNull('team_id')
            ->orderByDesc('created_at')
            ->value('team_id');

        if ($teamId !== '') {
            return $teamId;
        }

        $teamId = (string) CagePracticeResult::query()
            ->where('user_id', $playerId)
            ->whereNotNull('team_id')
            ->orderByDesc('created_at')
            ->value('team_id');

        if ($teamId !== '') {
            return $teamId;
        }

        $teamId = (string) LongTossPractice::query()
            ->where('user_id', $playerId)
            ->whereNotNull('team_id')
            ->orderByDesc('created_at')
            ->value('team_id');

        if ($teamId !== '') {
            return $teamId;
        }

        $teamId = (string) WeightBallPractice::query()
            ->where('user_id', $playerId)
            ->whereNotNull('team_id')
            ->orderByDesc('created_at')
            ->value('team_id');

        if ($teamId !== '') {
            return $teamId;
        }

        return (string) ExitVelocityPractice::query()
            ->where('user_id', $playerId)
            ->whereNotNull('team_id')
            ->orderByDesc('created_at')
            ->value('team_id');
    }

    private function normalizeDates(array $dates): array
    {
        $since = $dates[0] ?? null;
        $until = $dates[1] ?? null;

        if (!$since || !$until) {
            return $dates;
        }

        return [
            Carbon::parse((string) $since)->toDateString(),
            Carbon::parse((string) $until)->toDateString(),
        ];
    }

    private function battingFilters(array $params): array
    {
        if (array_key_exists(PracticeTypes::BATTING->value, $params['options'])) {
            return (new BattingFilters())->handle($params);
        }

        return [];
    }

    private function bullpenFilters(array $params): array
    {
        if (array_key_exists(PracticeTypes::BULLPEN->value, $params['options'])) {
            return (new BullpenFilters())->handle($params);
        }

        return [];
    }

    private function cageFilters(array $params): array
    {
        if (array_key_exists(PracticeTypes::CAGE->value, $params['options'])) {
            return (new CageFilters())->handle($params);
        }

        return [];
    }

    private function weightBallFilters(array $params): array
    {
        if (array_key_exists(PracticeModes::WEIGHT_BALL->value, $params['options'])) {
            return (new WeightBallFilters())->handle($params);
        }

        return [];
    }

    private function exitVelocityFilters(array $params): array
    {
        if (array_key_exists(PracticeModes::EXIT_VELOCITY->value, $params['options'])) {
            return (new ExitVelocityFilters())->handle($params);
        }

        return [];
    }

    private function longTossFilters(array $params): array
    {
        if (array_key_exists(PracticeModes::LONG_TOSS->value, $params['options'])) {
            return (new LongTossFilters())->handle($params);
        }

        return [];
    }

    private function liveABFilters(array $params): array
    {
        if (array_key_exists(PracticeTypes::LIVE_AB->value, $params['options'])) {
            return (new LiveABFilters())->handle($params);
        }

        return [];
    }
}
