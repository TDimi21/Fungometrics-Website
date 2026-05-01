<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\BullpenPracticeResult;
use App\Utils\Helper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

final class TopTenBullpenService
{
    public function __construct(private $team)
    {
    }

    public function getExitVelocityResults($range): array | Collection
    {

        try {
            $bullpenBase = BullpenPracticeResult::with('profile')
                ->where('is_in_match', '=', false)
                ->where('team_id', '=', $this->team);
            $bullpenFilterDates = Helper::range($range, $bullpenBase);
            $bullpen = $bullpenFilterDates->sortByDesc('miles_per_hour')
                ->take(10)
                ->map(function ($element) {
                    return [
                        'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                        'velocity'=>$element->miles_per_hour,
                        'date'=>Carbon::parse($element->updated_at)->format('Y-m-d')
                    ];
                });

            $liveBase = BullpenPracticeResult::with('profile')
                ->where('is_in_match', '=', true)
                ->where('team_id', '=', $this->team);
            $liveFilterDates = Helper::range($range, $liveBase);
            $live = $liveFilterDates->sortByDesc('miles_per_hour')
                ->take(10)
                ->map(function ($element) {
                    return [
                        'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                        'velocity'=>$element->miles_per_hour,
                        'date'=>Carbon::parse($element->updated_at)->format('Y-m-d')
                    ];
                });


            $all = collect()->merge($bullpen);
            $all = $all->merge($live);


            return  [
                'all'=>$all->sortByDesc('miles_per_hour')->take(10)->values(),
                'live'=>$live->values(),
                'bullpen'=>$bullpen->values(),
            ];
        } catch (Exception $exception) {
            return  [];
        }

    }

    public function getExitVelocityAverageResults($range): array | Collection
    {

        try {
            $battingBase = BullpenPracticeResult::with('profile')
                ->where('is_in_match', '=', false)
                ->where('team_id', '=', $this->team);
            $battingFilterDates = Helper::range($range, $battingBase);
            $batting = $battingFilterDates->groupBy('pitcher_id')
                ->take(10);
            $battingR =[];
            foreach ($batting as $key=>$element) {
                $battingR[$key]['avg']= round($element->avg('miles_per_hour'), 2);
                $battingR[$key]['name']= $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name;
            }


            $liveBase = BullpenPracticeResult::with('profile')
                ->where('is_in_match', '=', true)
                ->where('team_id', '=', $this->team);
            $liveFilterDates = Helper::range($range, $liveBase);
            $live = $liveFilterDates->groupBy('pitcher_id')
                ->take(10);

            $liveR =[];
            foreach ($live as $key=>$element) {
                $liveR[$key]['avg']= round($element->avg('miles_per_hour'), 2);
                $liveR[$key]['name']= $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name;
            }


            $all = collect()->merge($battingR);
            $all = $all->merge($liveR);


            return  [
                'all'=>collect()->merge($all->unique('name')->sortByDesc('avg')->take(10))->values(),
                'live'=>collect()->merge($liveR)->sortByDesc('avg')->values(),
                'bullpen'=>collect()->merge($battingR)->sortByDesc('avg')->values(),

            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }

    public function getTotalThrowsResults($range): array | Collection
    {

        try {
            $bullpenBase = BullpenPracticeResult::with('profile')
                ->where('is_in_match', '=', false)
                ->where('team_id', '=', $this->team);
            $bullpenFilterDates = Helper::range($range, $bullpenBase);
            $bullpen = $bullpenFilterDates->groupBy('pitcher_id')
                ->take(10);
            $bullpenR =collect();
            foreach ($bullpen as $key=>$element) {
                $bullpenR->push([
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name
                ]);
            }


            $liveBase = BullpenPracticeResult::with('profile')
                ->where('is_in_match', '=', true)
                ->where('team_id', '=', $this->team);
            $liveFilterDates = Helper::range($range, $liveBase);
            $live = $liveFilterDates ->groupBy('pitcher_id')
                ->take(10);

            $liveR =collect();
            foreach ($live as $key=>$element) {
                $liveR->push([
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name
                ]);
            }


            $all = collect()->merge($bullpenR);
            $all = $all->merge($liveR);


            return  [
                'all'=>collect()->merge($all->sortByDesc('count')->take(10))->values(),
                'live'=>collect()->merge($liveR)->sortByDesc('count')->values(),
                'bullpen'=>collect()->merge($bullpenR)->sortByDesc('count')->values(),
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }


}
