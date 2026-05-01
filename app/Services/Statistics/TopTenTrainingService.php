<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\LongTossPractice;
use App\Models\WeightBallPractice;
use App\Utils\Helper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use stdClass;
use Exception;

final class TopTenTrainingService
{
    public function __construct(private $team)
    {
    }

    public function getWeightVelocityResults($range): array | Collection
    {

        try {
            $weightBallBase = WeightBallPractice::with('profile')
                ->where('team_id', '=', $this->team);
            $weightBallFilterDates = Helper::range($range, $weightBallBase);
            $weightBall=$weightBallFilterDates->groupBy('weight');

            $weightBallR =$weightBall
                ->map(function ($item) {
                    return $item->sortByDesc('velocity')
                        ->take(10)->map(function ($result) {
                            $data = new stdClass();
                            $data->velocity = $result->velocity;
                            $data->name = $result->profile->first_name.' '.$result->profile->last_name;
                            $data->date = Carbon::parse($result->updated_at)->format('Y-m-d');
                            return $data;
                        })->values();
                });
            return  [
                'result'=>$weightBallR
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }

    public function getLongTossDistanceResults($range): array | Collection
    {

        try {
            $longTossBase= LongTossPractice::with('profile')
                ->where('team_id', '=', $this->team);
            $longTossFilterDates = Helper::range($range, $longTossBase);
            $longToss = $longTossFilterDates->groupBy('hop');

            $longTossR =$longToss
                ->map(function ($item) {
                    return $item->sortByDesc('distance')
                        ->take(10)->map(function ($result) {
                            $data = new stdClass();
                            $data->distance = $result->distance;
                            $data->name = $result->profile->first_name.' '.$result->profile->last_name;
                            $data->date = Carbon::parse($result->updated_at)->format('Y-m-d');
                            return $data;
                        })->values();
                });
            return  [
                'result'=>$longTossR
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }

    public function getThrowTrainingsResults($range): array | Collection
    {

        try {
            $longTossBase = LongTossPractice::with('profile')
                ->where('team_id', '=', $this->team);
            $longTossFilterDates = Helper::range($range, $longTossBase);
            $longToss = $longTossFilterDates->groupBy('user_id')
                ->take(10);

            $longTossR =collect();
            foreach ($longToss as $key=>$element) {
                $longTossR->push([
                    'player'=>$key,
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name
                ]);
            }

            $weightBallBase = WeightBallPractice::with('profile')
                ->where('team_id', '=', $this->team);
            $weightBallFilterDates = Helper::range($range, $weightBallBase);
            $weightBall = $weightBallFilterDates->groupBy('user_id')
                ->take(10);
            $weightBallR =collect();
            foreach ($weightBall as $key=>$element) {
                $weightBallR->push([
                    'player'=>$key,
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name
                ]);
            }

            $prepareAll = collect()->merge(collect($weightBallR)->values())->merge(($longTossR)->values());
            $all = $prepareAll->groupBy('player')->map(function ($element) {
                return [
                    'player'=>$element->map(fn ($item) => $item['player'])[0],
                    'count'=> $element->sum('count'),
                    'name'=> $element->map(fn ($item) => $item['name'])[0]
                ];
            });
            return  [
                'all'=> $all->sortByDesc('count')->take(10)->values(),
                'weight_ball'=>$weightBallR->sortByDesc('count')->values(),
                'long_toss'=>$longTossR->sortByDesc('count')->values()
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }
    }


}
