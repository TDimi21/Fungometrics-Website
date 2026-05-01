<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\PitchThrowTypes;
use App\Utils\Helper;

final class BullpenStatisticsService
{
    public function totals($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->getTeamTotals($data);
        $result['players'] = $this->getPlayersTotals($data);
        return $result;
    }

    public function percents($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->getTeamPercents($data);
        $result['players'] = $this->getPlayersPercents($data);
        return $result;
    }

    public function averageVelocityBreakDown($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->getAverageForTeam($data);
        $result['players'] = $this->getAverageForPlayers($data);
        return $result;
    }

    public function topVelocityBreakDown($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->topVelocitiesTeam($data);
        $result['players'] = $this->topVelocitiesPlayer($data);
        return $result;
    }

    public function typeThrowPercents($data, $type)
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->typeThrowPercentsTeam($data, $type);
        $result['players'] = $this->typeThrowPercentsPlayer($data, $type);
        return $result;
    }

    public function typeTrajectoryPercent($data, $type)
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->typeTrajectoryPercentTeam($data, $type);
        $result['players'] = $this->typeTrajectoryPercentPlayer($data, $type);
        return $result;
    }

    public function strikeThrowPercents($data, $type)
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->strikeThrowPercentsTeam($data, $type);
        $result['players'] = $this->strikeThrowPercentsPlayer($data, $type);
        return $result;
    }



    private function getTeamTotals($data)
    {
        return [
            'pitches'=>$data->count(),
            'ball'=>$data->where('zone', '=', 'B')->count(),
            'strike'=>$data->where('zone', '=', 'S')->count(),
            'FB'=>$data->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count(),
            'CH'=>$data->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count(),
            'CV'=>$data->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count(),
            'SL'=>$data->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count(),
            'OTHER'=>$data->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count(),
            'GB'=>$data->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)->count(),
            'LD'=>$data->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)->count(),
            'FLY'=>$data->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)->count(),
            'FOUL'=>$data->where('trajectory', '=', BattingTrajectory::FOUL->value)->count(),
            'S/M'=>$data->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count(),
            'TAKE'=>$data->where('trajectory', '=', BattingTrajectory::TAKE->value)->count(),
        ];
    }

    private function getPlayersTotals($data)
    {
        $dataGroup = $data->groupBy('pitcher_id');

        $playerTotals = [];
        foreach ($dataGroup as $key => $item) {
            $playerTotals[$key] = [
                'pitches'=>$item->count(),
                'ball'=>$item->where('zone', '=', 'B')->count(),
                'strike'=>$item->where('zone', '=', 'S')->count(),
                'FB'=>$item->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count(),
                'CH'=>$item->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count(),
                'CV'=>$item->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count(),
                'SL'=>$item->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count(),
                'OTHER'=>$item->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count(),
                'GB'=>$item->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)->count(),
                'LD'=>$item->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)->count(),
                'FLY'=>$item->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)->count(),
                'FOUL'=>$item->where('trajectory', '=', BattingTrajectory::FOUL->value)->count(),
                'S/M'=>$item->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count(),
                'TAKE'=>$item->where('trajectory', '=', BattingTrajectory::TAKE->value)->count(),
            ];

        }
        return $playerTotals;
    }

    private function getPlayersPercents($data)
    {

        $dataGroup = $data->groupBy('pitcher_id');

        $playerTotals = [];
        foreach ($dataGroup as $key => $item) {
            $totals = $item->count();
            $playerTotals[$key] = [
                'pitches'=>$totals,
                'ball'=>round(Helper::caseDivide($item->where('zone', '=', 'B')->count(), $totals)*100, 2),
                'strike'=>round(Helper::caseDivide($item->where('zone', '=', 'S')->count(), $totals)*100, 2),
                'FB'=>round(Helper::caseDivide($item->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                    ->count(), $totals)*100, 2),
                'CH'=>round(Helper::caseDivide($item->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                    ->count(), $totals)*100, 2),
                'CV'=>round(Helper::caseDivide($item->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                    ->count(), $totals)*100, 2),
                'SL'=>round(Helper::caseDivide($item->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count(), $totals)*100, 2),
                'OTHER'=>round(Helper::caseDivide($item->where('type_throw', '=', PitchThrowTypes::OTHER->value)
                    ->count(), $totals)*100, 2),
                'GB'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                    ->count(), $totals)*100, 2),
                'LD'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                    ->count(), $totals)*100, 2),
                'FLY'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                    ->count(), $totals)*100, 2),
                'FOUL'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::FOUL->value)
                    ->count(), $totals)*100, 2),
                'S/M'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                    ->count(), $totals)*100, 2),
                'TAKE'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::TAKE->value)
                    ->count(), $totals)*100, 2),
            ];

        }
        return $playerTotals;
    }

    private function getTeamPercents($data)
    {
        $totals = $data->count();
        return [
            'pitches'=>$totals,
            'ball'=>round(Helper::caseDivide($data->where('zone', '=', 'B')->count(), $totals)*100, 2),
            'strike'=>round(Helper::caseDivide($data->where('zone', '=', 'S')->count(), $totals)*100, 2),
            'FB'=>round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count(), $totals)*100, 2),
            'CH'=>round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count(), $totals)*100, 2),
            'CV'=>round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count(), $totals)*100, 2),
            'SL'=>round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count(), $totals)*100, 2),
            'OTHER'=>round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count(), $totals)*100, 2),
            'GB'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                ->count(), $totals)*100, 2),
            'LD'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                ->count(), $totals)*100, 2),
            'FLY'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                ->count(), $totals)*100, 2),
            'FOUL'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::FOUL->value)->count(), $totals)*100, 2),
            'S/M'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                ->count(), $totals)*100, 2),
            'TAKE'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::TAKE->value)
                ->count(), $totals)*100, 2),
        ];
    }

    private function getAverageForPlayers($data): array
    {

        $dataGroup = $data->groupBy('pitcher_id');
        $averageVelocity = [];
        foreach ($dataGroup as $key => $item) {
            $averageVelocity[$key] = [
                'pitches'=>$item->count(),
                'FB' => round(collect($item)->where('type_throw', PitchThrowTypes::FAST_BALL->value)
                    ->avg('miles_per_hour')??0, 2),
                'CH' => round(collect($item)->where('type_throw', PitchThrowTypes::CHANGE_UP->value)
                    ->avg('miles_per_hour')??0, 2),
                'CB' => round(collect($item)->where('type_throw', PitchThrowTypes::CURVE_BALL->value)
                    ->avg('miles_per_hour')??0, 2),
                'SL' => round(collect($item)->where('type_throw', PitchThrowTypes::SLIDER->value)
                    ->avg('miles_per_hour')??0, 2),
                'OTHER' => round(collect($item)->where('type_throw', PitchThrowTypes::OTHER->value)
                    ->avg('miles_per_hour')??0, 2),
            ];
        }

        return $averageVelocity;
    }

    private function getAverageForTeam($data)
    {

        return [
            'pitches'=>$data->count(),
            'FB' => round($data->where('type_throw', PitchThrowTypes::FAST_BALL->value)
                ->avg('miles_per_hour')??0, 2),
            'CH' => round($data->where('type_throw', PitchThrowTypes::CHANGE_UP->value)
                ->avg('miles_per_hour')??0, 2),
            'CB' => round($data->where('type_throw', PitchThrowTypes::CURVE_BALL->value)
                ->avg('miles_per_hour')??0, 2),
            'SL' => round($data->where('type_throw', PitchThrowTypes::SLIDER->value)
                ->avg('miles_per_hour')??0, 2),
            'OTHER' => round($data->where('type_throw', PitchThrowTypes::OTHER->value)
                ->avg('miles_per_hour')??0, 2),
        ];
    }

    /**
     * @param $data
     * @return array
     */
    private function topVelocitiesPlayer($data): array
    {
        $maxVelocities = [];
        $dataGroup = $data->groupBy('pitcher_id');
        foreach ($dataGroup as $key => $item) {
            $maxVelocities[$key] = [
                'pitches'=>$item->count(),
                'FB' => collect($item)->where('type_throw', PitchThrowTypes::FAST_BALL->value)
                    ->max('miles_per_hour'),
                'CH' => collect($item)->where('type_throw', PitchThrowTypes::CHANGE_UP->value)
                    ->max('miles_per_hour'),
                'CB' => collect($item)->where('type_throw', PitchThrowTypes::CURVE_BALL->value)
                    ->max('miles_per_hour'),
                'SL' => collect($item)->where('type_throw', PitchThrowTypes::SLIDER->value)
                    ->max('miles_per_hour'),
                'OTHER' => collect($item)->where('type_throw', PitchThrowTypes::OTHER->value)
                    ->max('miles_per_hour'),
            ];
        }
        return $maxVelocities;
    }

    private function topVelocitiesTeam($data)
    {
        return [
            'pitches'=>$data->count(),
            'FB' => $data->where('type_throw', PitchThrowTypes::FAST_BALL->value)
                ->max('miles_per_hour'),
            'CH' => $data->where('type_throw', PitchThrowTypes::CHANGE_UP->value)
                ->max('miles_per_hour'),
            'CB' => $data->where('type_throw', PitchThrowTypes::CURVE_BALL->value)
                ->max('miles_per_hour'),
            'SL' => $data->where('type_throw', PitchThrowTypes::SLIDER->value)
                ->max('miles_per_hour'),
            'OTHER' => $data->where('type_throw', PitchThrowTypes::OTHER->value)
                ->max('miles_per_hour'),
        ];
    }

    private function typeThrowPercentsPlayer($data, $type, bool $param=false)
    {
        $typeThrowPercents = [];
        $dataGroup = $data->groupBy('pitcher_id');
        foreach ($dataGroup as $key => $item) {
            $totals = $item->count();
            $typeThrowPercents[$key] = [
                'pitches'=> $totals,
                'GB'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                    ->where('type_throw', '=', $type)
                    ->count(), $totals)*100, 2),
                'LD'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                    ->where('type_throw', '=', $type)
                    ->count(), $totals)*100, 2),
                'FLY'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                    ->where('type_throw', '=', $type)
                    ->count(), $totals)*100, 2),
                'FOUL'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::FOUL->value)
                    ->where('type_throw', '=', $type)
                    ->count(), $totals)*100, 2),
                'S/M'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                    ->where('type_throw', '=', $type)
                    ->count(), $totals)*100, 2),
                'TAKE'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::TAKE->value)
                    ->where('type_throw', '=', $type)
                    ->count(), $totals)*100, 2),
            ];
        }
        return $typeThrowPercents;
    }

    private function typeThrowPercentsTeam($data, $type)
    {
        $totals = $data->count();
        return [
            'pitches'=> $totals,
            'GB'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                ->where('type_throw', '=', $type)
                ->count(), $totals)*100, 2),
            'LD'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                ->where('type_throw', '=', $type)
                ->count(), $totals)*100, 2),
            'FLY'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                ->where('type_throw', '=', $type)
                ->count(), $totals)*100, 2),
            'FOUL'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::FOUL->value)
                ->where('type_throw', '=', $type)
                ->count(), $totals)*100, 2),
            'S/M'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                ->where('type_throw', '=', $type)
                ->count(), $totals)*100, 2),
            'TAKE'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::TAKE->value)
                ->where('type_throw', '=', $type)
                ->count(), $totals)*100, 2),
        ];
    }

    private function typeTrajectoryPercentTeam($data, $type)
    {
        $totals = $data->count();
        return [
            'pitches'=> $totals,
            'FB'=>round(Helper::caseDivide($data->where('trajectory', '=', $type)
                ->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                ->count(), $totals)*100, 2),
            'CH'=>round(Helper::caseDivide($data->where('trajectory', '=', $type)
                ->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                ->count(), $totals)*100, 2),
            'SL'=>round(Helper::caseDivide($data->where('trajectory', '=', $type)
                ->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                ->count(), $totals)*100, 2),
            'CB'=>round(Helper::caseDivide($data->where('trajectory', '=', $type)
                ->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                ->count(), $totals)*100, 2),
        ];
    }

    private function typeTrajectoryPercentPlayer($data, $type)
    {
        $typeTrajectoryPercent = [];
        $dataGroup = $data->groupBy('pitcher_id');
        foreach ($dataGroup as $key => $item) {
            $totals = $item->count();
            $typeTrajectoryPercent[$key] = [
                'pitches'=> $totals,
                'FB'=>round(Helper::caseDivide($item->where('trajectory', '=', $type)
                    ->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                    ->count(), $totals)*100, 2),
                'CH'=>round(Helper::caseDivide($item->where('trajectory', '=', $type)
                    ->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                    ->count(), $totals)*100, 2),
                'SL'=>round(Helper::caseDivide($item->where('trajectory', '=', $type)
                    ->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                    ->count(), $totals)*100, 2),
                'CB'=>round(Helper::caseDivide($item->where('trajectory', '=', $type)
                    ->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                    ->count(), $totals)*100, 2),
            ];
        }
        return $typeTrajectoryPercent;
    }

    private function strikeThrowPercentsTeam($data, $type)
    {
        $totals = $data->count();
        return [
            'pitches'=> $totals,
            'GB'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                ->where('type_throw', '=', $type)
                ->where('zone', '=', 'S')
                ->count(), $totals)*100, 2),
            'LD'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                ->where('type_throw', '=', $type)
                ->where('zone', '=', 'S')
                ->count(), $totals)*100, 2),
            'FLY'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                ->where('type_throw', '=', $type)
                ->where('zone', '=', 'S')
                ->count(), $totals)*100, 2),

            'S/M'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                ->where('type_throw', '=', $type)
                ->where('zone', '=', 'S')
                ->count(), $totals)*100, 2),
            'TAKE'=>round(Helper::caseDivide($data->where('trajectory', '=', BattingTrajectory::TAKE->value)
                ->where('type_throw', '=', $type)
                ->where('zone', '=', 'S')
                ->count(), $totals)*100, 2),
        ];
    }

    private function strikeThrowPercentsPlayer($data, $type)
    {
        $strikeThrowPercents = [];
        $dataGroup = $data->groupBy('pitcher_id');
        foreach ($dataGroup as $key => $item) {
            $totals = $item->count();
            $strikeThrowPercents[$key] = [
                'pitches'=> $totals,
                'GB'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                    ->where('type_throw', '=', $type)
                    ->where('zone', '=', 'S')
                    ->count(), $totals)*100, 2),
                'LD'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                    ->where('type_throw', '=', $type)
                    ->where('zone', '=', 'S')
                    ->count(), $totals)*100, 2),
                'FLY'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                    ->where('type_throw', '=', $type)
                    ->where('zone', '=', 'S')
                    ->count(), $totals)*100, 2),

                'S/M'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                    ->where('type_throw', '=', $type)
                    ->where('zone', '=', 'S')
                    ->count(), $totals)*100, 2),
                'TAKE'=>round(Helper::caseDivide($item->where('trajectory', '=', BattingTrajectory::TAKE->value)
                    ->where('type_throw', '=', $type)
                    ->where('zone', '=', 'S')
                    ->count(), $totals)*100, 2),
            ];
        }
        return $strikeThrowPercents;
    }


}
