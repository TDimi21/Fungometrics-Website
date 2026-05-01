<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Utils\Helper;

final class LongTossStatisticsService
{
    public array $distances = [
        '0-10'=>[0, 10],
        '11-20'=>[11, 20],
        '21-30'=>[21, 30],
        '31-40'=>[31, 40],
        '41-50'=>[41, 50],
        '51-60'=>[51, 60],
        '61-70'=>[61, 70],
        '71-80'=>[71, 80],
        '81-90'=>[81, 90],
        '91-100'=>[91, 100],
        '101-110'=>[101, 110],
        '111+'=>[111, PHP_INT_MAX]
    ];

    public array $hops = [
        'No Hops'=>0,
        '1 Hop'=>1,
        '2 Hop'=>2,
        '3 Hop'=>3,
    ];

    public function distanceTotals($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->distanceTotalsTeam($data);
        $result['players'] = $this->distanceTotalsPlayers($data);
        return $result;
    }

    public function distancePercentage($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->distancePercentageTeam($data);
        $result['players'] = $this->distancePercentagePlayers($data);
        return $result;
    }

    public function distanceAverage($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->distanceAverageTeam($data);
        $result['players'] = $this->distanceAveragePlayers($data);
        return $result;
    }

    public function totalHops($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->totalHopsTeam($data);
        $result['players'] = $this->totalHopsPlayers($data);
        return $result;
    }

    public function averageHops($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->averageHopsTeam($data);
        $result['players'] = $this->averageHopsPlayers($data);
        return $result;
    }

    public function maxHops($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->maxHopsTeam($data);
        $result['players'] = $this->maxHopsPlayers($data);
        return $result;
    }


    private function distanceTotalsTeam($data)
    {
        $totals = ['throws' => $data->count()];
        foreach ($this->distances as $key => $item) {
            $totals[$key] = $data->whereBetween('distance', $item)->count();
        }
        return $totals;
    }

    private function distanceTotalsPlayers($data)
    {
        $totals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals[$key] = ['throws' => $item->count()];
            foreach ($this->distances as $label=>$distance) {
                $totals[$key][$label] = $item->whereBetween('distance', $distance)->count();
            }
        }

        return $totals;
    }

    private function distancePercentageTeam($data)
    {
        $totals =$data->count();
        $percents = ['throws' => $totals];
        foreach ($this->distances as $key => $item) {
            $percents[$key] = round(Helper::caseDivide($data->whereBetween('distance', $item)->count(), $totals)*100, 2);
        }
        return $percents;
    }

    private function distancePercentagePlayers($data)
    {
        $percents=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {

            $totals = $item->count();
            $percents[$key] = ['throws' => $totals];
            foreach ($this->distances as $label=>$distance) {
                $percents[$key][$label] = round(Helper::caseDivide($item->whereBetween('distance', $distance)->count(), $totals)*100, 2);
            }
        }

        return $percents;
    }

    private function distanceAverageTeam($data)
    {
        $average = ['throws' => $data->count()];
        foreach ($this->distances as $key => $item) {
            $average[$key] = round($data->whereBetween('distance', $item)->avg('hop')??0, 2);
        }
        return $average;
    }

    private function distanceAveragePlayers($data)
    {
        $averages=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {

            $totals = $item->count();
            $averages[$key] = ['throws' => $totals];
            foreach ($this->distances as $label=>$distance) {
                $averages[$key][$label] = round($item->whereBetween('distance', $distance)->avg('hop')??0, 2);
            }
        }

        return $averages;
    }

    private function totalHopsTeam($data)
    {
        $totals = ['throws' => $data->count()];
        foreach ($this->hops as $key => $item) {
            $totals[$key] = $data->where('hop', '=', $item)->count();
        }
        return $totals;
    }

    private function totalHopsPlayers($data)
    {
        $totals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals[$key] = ['throws' => $item->count()];
            foreach ($this->hops as $label=>$hop) {
                $totals[$key][$label] = $item->where('hop', '=', $hop)->count();
            }
        }

        return $totals;
    }

    private function averageHopsTeam($data)
    {
        $average = ['throws' => $data->count()];
        foreach ($this->hops as $key => $item) {
            $average[$key] = round($data->where('hop', '=', $item)->avg('distance')??0, 2);
        }
        return $average;
    }

    private function averageHopsPlayers($data)
    {
        $averages=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {

            $totals = $item->count();
            $averages[$key] = ['throws' => $totals];
            foreach ($this->hops as $label=>$hop) {
                $averages[$key][$label] = round($item->where('hop', $hop)->avg('distance')??0, 2);
            }
        }

        return $averages;
    }

    private function maxHopsTeam($data)
    {
        $totals =$data->count();
        $maxs = ['throws' => $totals];
        foreach ($this->hops as $key => $item) {
            $maxs[$key] = $data->where('hop', '=', $item)->max('distance');
        }
        return $maxs;
    }

    private function maxHopsPlayers($data)
    {
        $maxs=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {

            $totals = $item->count();
            $maxs[$key] = ['throws' => $totals];
            foreach ($this->hops as $label=>$hop) {
                $maxs[$key][$label] = $item->where('hop', '=', $hop)->max('distance');
            }
        }

        return $maxs;
    }

}
