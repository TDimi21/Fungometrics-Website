<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\BattingPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Utils\Helper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

final class TopTenBattingService
{
    public function __construct(private $team)
    {
    }

    public function getExitVelocityResults($range): array | Collection
    {
        try {
            $battingBase = BattingPracticeResult::with('profile')
                ->where('is_in_match', '=', false)
                ->where('team_id', '=', $this->team);

            $battingFilterDates = Helper::range($range, $battingBase);
            $batting = $battingFilterDates->sortByDesc('velocity')
                ->take(10)
                ->map(function ($element) {
                    return [
                        'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                        'velocity'=>$element->velocity,
                        'date'=>Carbon::parse($element->updated_at)->format('Y-m-d'),
                    ];
                });
            $liveBase = BattingPracticeResult::with('profile')
                ->where('is_in_match', '=', true)
                ->where('team_id', '=', $this->team);
            $liveFilterDates = Helper::range($range, $liveBase);
            $live = $liveFilterDates->sortByDesc('velocity')
                ->take(10)
                ->map(function ($element) {
                    return [
                        'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                        'velocity'=>$element->velocity,
                        'date'=>Carbon::parse($element->updated_at)->format('Y-m-d'),
                    ];
                });

            $exitTrainingBase = ExitVelocityPractice::with('profile')
                ->where('team_id', '=', $this->team);
            $exitTrainingFilterDates = Helper::range($range, $exitTrainingBase);
            $exitTraining = $exitTrainingFilterDates->sortByDesc('velocity')
                ->take(10)
                ->map(function ($element) {
                    return [
                        'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                        'velocity'=>$element->velocity,
                        'date'=>Carbon::parse($element->updated_at)->format('Y-m-d'),
                    ];
                });
            $cageBase = CagePracticeResult::with('profile')
                ->where('team_id', '=', $this->team);
            $cageFilterDates = Helper::range($range, $cageBase);
            $cage = $cageFilterDates->sortByDesc('launch_angle_velocity')
                ->take(10)
                ->map(function ($element) {
                    return [
                        'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                        'velocity'=>$element->launch_angle_velocity,
                        'date'=>Carbon::parse($element->updated_at)->format('Y-m-d'),
                    ];
                });
            $all = collect()->merge($batting);
            $all = $all->merge($live);
            $all = $all->merge($exitTraining);
            $all = $all->merge($cage);
            return  [
                'all'=>collect()->merge($all->sortByDesc('velocity')->take(10))->all(),
                'live'=>$live->values(),
                'batting'=>$batting->values(),
                'exit'=>$exitTraining->values(),
                'cage'=>$cage->values()
            ];
        } catch (Exception $exception) {
            return  [];
        }

    }

    public function getExitVelocityAverageResults($range): array | Collection
    {
        try {
            $battingBase = BattingPracticeResult::with('profile')
                ->where('is_in_match', '=', false)
                ->where('team_id', '=', $this->team);
            $battingFilterDates = Helper::range($range, $battingBase);
            $batting = $battingFilterDates->groupBy('batter_id')
                ->take(10);
            $battingR =collect();
            foreach ($batting as $key=>$element) {
                $battingR->push([
                    'avg'=> round($element->avg('velocity'), 2),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }

            $liveBase = BattingPracticeResult::with('profile')
                ->where('is_in_match', '=', true)
                ->where('team_id', '=', $this->team);
            $liveFilterDates = Helper::range($range, $liveBase);
            $live = $liveFilterDates->groupBy('batter_id')
                ->take(10);
            $liveR =collect();
            foreach ($live as $key=>$element) {
                $liveR->push([
                    'avg'=> round($element->avg('velocity'), 2),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }

            $exitTrainingBase = ExitVelocityPractice::with('profile')
                ->where('team_id', '=', $this->team);
            $exitTrainingFilterDates = Helper::range($range, $exitTrainingBase);
            $exitTraining = $exitTrainingFilterDates->groupBy('user_id')
                ->take(10);

            $exitTrainingR =collect();
            foreach ($exitTraining as $key=>$element) {
                $exitTrainingR->push([
                    'avg'=> round($element->avg('velocity'), 2),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }
            $cageBae = CagePracticeResult::with('profile')
                ->where('team_id', '=', $this->team);
            $cageFilterDate = Helper::range($range, $cageBae);
            $cage = $cageFilterDate->groupBy('user_id')
                ->take(10);
            $cageR =collect();
            foreach ($cage as $key=>$element) {
                $cageR->push([
                    'avg'=> round($element->avg('launch_angle_velocity'), 2),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }


            $all = collect()->merge($battingR);
            $all = $all->merge($liveR);
            $all = $all->merge($exitTrainingR);
            $all = $all->merge($cageR);

            return  [
                'all'=>$all->unique('name')->sortByDesc('avg')->take(10)->values(),
                'live'=>$liveR->sortByDesc('avg')->values(),
                'batting'=>$battingR->sortByDesc('avg')->values(),
                'exit'=>$exitTrainingR->sortByDesc('avg')->values(),
                'cage'=>$cageR->sortByDesc('avg')->values(),
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }

    public function getTotalSwingsResults($range): array | Collection
    {

        try {
            $battingBase = BattingPracticeResult::with('profile')
                ->where('is_in_match', '=', false)
                ->where('team_id', '=', $this->team);
            $battingFilterDates = Helper::range($range, $battingBase);
            $batting = $battingFilterDates->groupBy('batter_id')
                ->take(10);
            $battingR =collect();
            foreach ($batting as $key=>$element) {
                $battingR->push([
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }
            $liveBase = BattingPracticeResult::with('profile')
                ->where('is_in_match', '=', true)
                ->where('team_id', '=', $this->team);
            $liveFilterDates = Helper::range($range, $liveBase);
            $live = $liveFilterDates->groupBy('batter_id')
                ->take(10);

            $liveR =collect();
            foreach ($live as $key=>$element) {
                $liveR->push([
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }

            $exitTrainingBase = ExitVelocityPractice::with('profile')
                ->where('team_id', '=', $this->team);
            $exitTrainingFilterDates = Helper::range($range, $exitTrainingBase);
            $exitTraining = $exitTrainingFilterDates->groupBy('batter_id')
                ->take(10);

            $exitTrainingR =collect();
            foreach ($exitTraining as $key=>$element) {
                $exitTrainingR->push([
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }
            $cageBase = CagePracticeResult::with('profile')
                ->where('team_id', '=', $this->team);
            $cageFilterDates = Helper::range($range, $cageBase);
            $cage = $cageFilterDates->groupBy('batter_id')
                ->take(10);
            $cageR =collect();
            foreach ($cage as $key=>$element) {
                $cageR->push([
                    'count'=> $element->count(),
                    'name'=> $element[0]->profile?->first_name.' '.$element[0]->profile?->last_name,
                ]);
            }


            $all = collect()->merge($battingR);
            $all = $all->merge($liveR);
            $all = $all->merge($exitTrainingR);
            $all = $all->merge($cageR);

            return  [
                'all'=>$all->sortByDesc('count')->take(10)->values(),
                'live'=>$liveR->sortByDesc('count')->values(),
                'batting'=>$battingR->sortByDesc('count')->values(),
                'exit'=>$exitTrainingR->sortByDesc('count')->values(),
                'cage'=>$cageR->sortByDesc('count')->values(),
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }


}
