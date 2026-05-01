<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Utils\Helper;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

final class TopTenFitnessService
{
    public function __construct(private $team)
    {
    }

    public function getFitnessWeightResults($range): array | Collection
    {

        try {
            $playersIds = PlayerTeam::where('team_id', '=', $this->team)
                ->pluck('user_id')->all();

            $fitnessBase = PlayerFitness::with('profile')
                ->whereIn('user_id', $playersIds);
            $fitness = Helper::range($range, $fitnessBase);

            $powerClean = $fitness->sortByDesc('power_clean')->map(function ($element) {
                return [
                    'value'=>$element->power_clean,
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->user->profile->first_name.' '.$element->user->profile->last_name,
                ];
            })->take(10);
            $benchPress = $fitness->sortByDesc('bench_press')->map(function ($element) {
                return [
                    'value'=>$element->bench_press,
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->user->profile->first_name.' '.$element->user->profile->last_name,
                ];
            })->take(10);
            $frontSquat = $fitness->sortByDesc('front_squat')->map(function ($element) {
                return [
                    'value'=>$element->front_squat,
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->user->profile->first_name.' '.$element->user->profile->last_name,
                ];
            })->take(10);

            $backSquat = $fitness->sortByDesc('back_squat')->map(function ($element) {
                return [
                    'value'=>$element->back_squat,
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->user->profile->first_name.' '.$element->user->profile->last_name,
                ];
            })->take(10);
            $deadLift = $fitness->sortByDesc('dead_lift')->map(function ($element) {
                return [
                    'value'=>$element->dead_lift,
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->user->profile->first_name.' '.$element->user->profile->last_name,
                ];
            })->take(10);


            return  [
                'power_clean'=>$powerClean->values(),
                'bench_press'=>$benchPress->values(),
                'front_squat'=>$frontSquat->values(),
                'back_squat'=>$backSquat->values(),
                'dead_lift'=>$deadLift->values()
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }

    public function getPowerBodyWeightResults($range): array | Collection
    {

        try {
            $playersIds = PlayerTeam::where('team_id', '=', $this->team)
                ->pluck('user_id')->all();

            $fitnessBase = PlayerFitness::with('profile', 'player')
                ->whereIn('user_id', $playersIds);
            $fitness = Helper::range($range, $fitnessBase);

            $powerClean = $fitness->sortByDesc('power_clean')->map(function ($element) {
                Log::info($element);
                return [
                    'value'=>round(Helper::caseDivide($element->power_clean, $element->body_weight??0), 2),
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                ];
            })->take(10);
            $benchPress = $fitness->sortByDesc('bench_press')->map(function ($element) {
                return [
                    'value'=>round(Helper::caseDivide($element->bench_press, $element->body_weight??0), 2),
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                ];
            })->take(10);
            $frontSquat = $fitness->sortByDesc('front_squat')->map(function ($element) {
                return [
                    'value'=>round(Helper::caseDivide($element->front_squat, $element->body_weight??0), 2),
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                ];
            })->take(10);
            $backSquat = $fitness->sortByDesc('back_squat')->map(function ($element) {
                return [
                    'value'=>round(Helper::caseDivide($element->back_squat, $element->body_weight??0), 2),
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                ];
            })->take(10);
            $deadLift = $fitness->sortByDesc('dead_lift')->map(function ($element) {
                return [
                    'value'=>round(Helper::caseDivide($element->dead_lift, $element->body_weight??0), 2),
                    'dated'=>Carbon::parse($element->fitness_date)->format('Y-m-d'),
                    'name'=>$element->profile?->first_name.' '.$element->profile?->last_name,
                ];
            })->take(10);


            return  [
                'power_clean'=>$powerClean->sortByDesc('value')->values(),
                'bench_press'=>$benchPress->sortByDesc('value')->values(),
                'front_squat'=>$frontSquat->sortByDesc('value')->values(),
                'back_squat'=>$backSquat->sortByDesc('value')->values(),
                'dead_lift'=>$deadLift->sortByDesc('value')->values()
            ];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return  [];
        }

    }



}
