<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Exceptions\NotFound;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\WeightBallPractice;
use App\Utils\Helper;
use Illuminate\Support\Carbon;

final class ChartsDataService
{
    public function getAverageLiveExitVelocity(array $params, int $range = 0)
    {
        $data = $this->playersResults($params);

        if (0 === $data->count()) {
            throw new NotFound();
        }
        $data->get()->map(function ($model): void {
            $model->created_at = $model->created_at->format('Y-m-d');
        });
        $filterByDate = Helper::range($range, $data);
        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->updated_at->format('Y-m-d'),
                    'date' => $element->updated_at,
                    'practice_id' => $element->practice_id,
                    'batter_id' => $element->batter_id,
                    'velocity' => $element->velocity
                ]);
        })->values();

        return $grouping->groupBy('updated_at')
            ->map(fn ($element) => $element->groupBy('batter_id')
                ->map(fn ($ele) => $ele->groupBy('practice_id')->sortBy('date')
                    ->map(function ($el) {
                        return [
                            'x' => Carbon::parse($el->max('date'))->format("D M d Y H:i:s \G\M\TO (T)"),
                            'y' => round($el->average('velocity'), 2)
                        ];
                    })))->map(fn ($el) => $el->map(fn ($el) => array_values($el->toArray())));


    }

    /**
     * @param  array  $params
     * @return mixed
     */
    public function playersResults(array $params)
    {
        if ( ! isset($params['team'])) {
            $data = BattingPracticeResult::whereIn('batter_id', $params['players'])
                ->where('is_in_match', '=', false)
                ->where('velocity', '>', 0);
        } else {
            $data = BattingPracticeResult::where('team_id', '=', $params['team'])
                ->where('is_in_match', '=', false)
                ->where('velocity', '>', 0)
                ->whereIn('batter_id', $params['players']);
        }
        return $data;
    }

    public function getMaxExitVelocity(array $params, int $range = 0)
    {
        $data = $this->playersResults($params);
        if (0 === $data->count()) {
            throw new NotFound();
        }
        $filterByDate = Helper::range($range, $data);
        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->created_at->format('Y-m-d'),
                    'practice_id' => $element->practice_id,
                    'batter_id' => $element->batter_id,
                    'velocity' => $element->velocity,
                    'date' => $element->updated_at,
                ]);
        })->values();

        return $grouping->groupBy('updated_at')
            ->map(fn ($element, $key) => $element->groupBy('batter_id')
                ->map(fn ($ele) => $ele->groupBy('practice_id')
                    ->map(fn ($el) => [
                        'x' => Carbon::parse($el->max('date'))->format("D M d Y H:i:s \G\M\TO (T)"),
                        'y' => $el->max('velocity')
                    ])))->map(fn ($el) => $el->map(fn ($el) => array_values($el->toArray())));
    }

    public function getMaxCageDistance(array $params, int $range = 0)
    {
        if ( ! isset($params['team'])) {
            $data = CagePracticeResult::whereIn('user_id', $params['players']);
        } else {
            $data = CagePracticeResult::where('team_id', '=', $params['team'])
                ->whereIn('user_id', $params['players']);
        }

        if (0 === $data->count()) {
            throw new NotFound();
        }
        $filterByDate = Helper::range($range, $data);

        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->created_at->format('Y-m-d'),
                    'practice_id' => $element->practice_id,
                    'user_id' => $element->user_id,
                    'distance_travel' => $element->distance_travel,
                    'date' => $element->updated_at,
                ]);
        })->values();

        return $grouping->groupBy('updated_at')
            ->map(fn ($element, $key) => $element->groupBy('user_id')
                ->map(fn ($ele) => $ele->groupBy('practice_id')
                    ->map(fn ($el) => [
                        'x' => Carbon::parse($el->max('date'))->format("D M d Y H:i:s \G\M\TO (T)"),
                        'y' => $el->max('distance_travel')
                    ])))
            ->map(fn ($el) => $el->map(fn ($el) => array_values($el->toArray())));

    }

    public function getStrikePercent(array $params, int $range = 0)
    {
        if ( ! isset($params['team'])) {
            $data = BullpenPracticeResult::whereIn('pitcher_id', $params['players']);
        } else {
            $data = BullpenPracticeResult::where('team_id', '=', $params['team'])
                ->whereIn('pitcher_id', $params['players']);
        }

        if (0 === $data->count()) {
            throw new NotFound();
        }

        $filterByDate = Helper::range($range, $data);

        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->created_at->format('Y-m-d'),
                    'practice_id' => $element->practice_id,
                    'pitcher_id' => $element->pitcher_id,
                    'is_strike' => $element->is_strike,
                    'zone' => $element->zone,
                    'date' => $element->updated_at,
                ]);
        })->values();
        return $grouping->groupBy('updated_at')
            ->map(function ($element) {
                return $element->groupBy('pitcher_id')
                    ->map(function ($ele) {
                        return $ele->groupBy('practice_id')
                            ->map(
                                fn ($el) => [
                                    'x' => Carbon::parse($el->max('date'))
                                        ->format("D M d Y H:i:s \G\M\TO (T)"),
                                    'y' => round(Helper::caseDivide(
                                        $el->where('zone', '=', 'S')->count(),
                                        $el->count()
                                    ) * 100)
                                ]
                            )->filter(fn ($value) => $value > 0);
                    });
            })->map(fn ($el) => $el->map(fn ($el) => array_values($el->toArray())));


    }

    public function getMaxFbVelocity(array $params, int $range = 0)
    {
        if ( ! isset($params['team'])) {
            $data = BullpenPracticeResult::whereIn('pitcher_id', $params['players'])->where(
                'type_throw',
                '=',
                PitchThrowTypes::FAST_BALL->value
            );
        } else {
            $data = BullpenPracticeResult::where('team_id', '=', $params['team'])
                ->whereIn('pitcher_id', $params['players'])->where(
                    'type_throw',
                    '=',
                    PitchThrowTypes::FAST_BALL->value
                );
        }

        if (0 === $data->count()) {
            throw new NotFound();
        }

        $filterByDate = Helper::range($range, $data);


        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->created_at->format('Y-m-d'),
                    'practice_id' => $element->practice_id,
                    'pitcher_id' => $element->pitcher_id,
                    'miles_per_hour' => $element->miles_per_hour,
                    'date' => $element->updated_at,
                ]);
        })->values();

        return $grouping->groupBy('updated_at')
            ->map(fn ($element, $key) => $element->groupBy('pitcher_id')
                ->map(fn ($ele) => $ele->groupBy('practice_id')
                    ->map(fn ($el) => [
                        'x' => Carbon::parse($el->max('date'))->format("D M d Y H:i:s \G\M\TO (T)"),
                        'y' => $el->max('miles_per_hour')
                    ])))
            ->map(fn ($el) => $el->map(fn ($el) => array_values($el->toArray())));

    }

    public function getAvgThrowVelocity(array $params, int $range = 0)
    {
        if ( ! isset($params['team'])) {
            $data = WeightBallPractice::whereIn('user_id', $params['players']);
        } else {
            $data = WeightBallPractice::where('team_id', '=', $params['team'])
                ->whereIn('user_id', $params['players']);
        }

        if (0 === $data->count()) {
            throw new NotFound();
        }

        $filterByDate = Helper::range($range, $data);

        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->created_at->format('Y-m-d'),
                    'practice_id' => $element->practice_id,
                    'user_id' => $element->user_id,
                    'velocity' => $element->velocity,
                    'weight' => $element->weight,
                    'date' => $element->updated_at,
                ]);
        })->values();

        return $grouping->groupBy('updated_at')
            ->map(fn ($element, $key) => $element->groupBy('user_id')
                ->map(fn ($ele) => $ele->groupBy('practice_id')
                    ->map(
                        fn ($el) => $el->groupBy('weight')
                            ->map(fn ($el, $key) => [
                                'x' => Carbon::parse($el->max('date'))->format("D M d Y H:i:s \G\M\TO (T)"),
                                'y' => round($el->average('velocity'), 2),
                                'z'=> $key,
                            ])
                    )))->map(fn ($el) => $el->map(fn ($el) => $el->flatten(1)->values()));

    }

    public function getMaxDistanceThrows(array $params, int $range = 0)
    {
        if ( ! isset($params['team'])) {
            $data = LongTossPractice::whereIn('user_id', $params['players'])
                ->where('hop', '=', 0);
        } else {
            $data = LongTossPractice::where('team_id', '=', $params['team'])
                ->whereIn('user_id', $params['players'])
                ->where('hop', '=', 0);
        }

        if (0 === $data->count()) {
            throw new NotFound();
        }
        $filterByDate = Helper::range($range, $data);

        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->created_at->format('Y-m-d'),
                    'practice_id' => $element->practice_id,
                    'user_id' => $element->user_id,
                    'distance' => $element->distance,
                    'date' => $element->updated_at,
                ]);
        })->values();

        return $grouping->groupBy('updated_at')
            ->map(fn ($element, $key) => $element->groupBy('user_id')
                ->map(fn ($ele) => $ele->groupBy('practice_id')
                    ->map(fn ($el) => [
                        'x' => Carbon::parse($el->max('date'))->format("D M d Y H:i:s \G\M\TO (T)"),
                        'y' => $el->max('distance')
                    ])))->map(fn ($el) => $el->map(fn ($el) => array_values($el->toArray())));

    }

    public function getAvgExitVelocity(array $params, int $range = 0)
    {
        if ( ! isset($params['team'])) {
            $data = ExitVelocityPractice::whereIn('user_id', $params['players']);
        } else {
            $data = ExitVelocityPractice::where('team_id', '=', $params['team'])
                ->whereIn('user_id', $params['players']);
        }
        if (0 === $data->count()) {
            throw new NotFound();
        }
        $filterByDate = Helper::range($range, $data);
        $grouping = $filterByDate->map(function ($element) {
            return
                collect([
                    'updated_at' => $element->created_at->format('Y-m-d'),
                    'practice_id' => $element->practice_id,
                    'user_id' => $element->user_id,
                    'velocity' => $element->velocity,
                    'date' => $element->updated_at,
                ]);
        })->values();

        return $grouping->groupBy('updated_at')
            ->map(fn ($element, $key) => $element->groupBy('user_id')
                ->map(fn ($ele) => $ele->groupBy('practice_id')
                    ->map(fn ($el) => [
                        'x' => Carbon::parse($el->max('date'))->format("D M d Y H:i:s \G\M\TO (T)"),
                        'y' => round($el->average('velocity'), 2)
                    ])))
            ->map(fn ($el) => $el->map(fn ($el) => array_values($el->toArray())));
    }
}
