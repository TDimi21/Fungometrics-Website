<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Utils\Helper;

final class ExitVelocityStatisticsService
{
    public array $trajectories = [
        'GB','LD','FLY'
    ];

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
        $result['team_totals']= $this->percentsTeam($data);
        $result['players'] = $this->percentsPlayers($data);
        return $result;
    }

    public function averageVelocities($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->averageForTeam($data);
        $result['players'] = $this->averageForPlayers($data);
        return $result;
    }

    public function maxVelocities($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->maxVelocitiesTeam($data);
        $result['players'] = $this->maxVelocitiesPlayer($data);
        return $result;
    }

    private function getTeamTotals($data)
    {
        $totals = ['swings' => $data->count()];
        foreach ($this->trajectories as $type) {
            $totals[$type] = $data->where('trajectory', '=', $type)->count();
        }
        return $totals;
    }

    private function averageForTeam($data)
    {
        $averages = ['swings' => $data->count()];
        foreach ($this->trajectories as $type) {
            $averages[$type] = round($data->where('trajectory', '=', $type)->avg('velocity')??0, 2);
        }
        return $averages;
    }

    private function maxVelocitiesTeam($data)
    {
        $maxVelocities = ['swings' => $data->count()];
        foreach ($this->trajectories as $type) {
            $maxVelocities[$type] = $data->where('trajectory', '=', $type)->max('velocity');
        }
        return $maxVelocities;
    }

    private function getPlayersTotals($data)
    {
        $totals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals[$key] = ['swings' => $item->count()];
            foreach ($this->trajectories as $type) {
                $totals[$key][$type] = $item->where('trajectory', '=', $type)->count();
            }
        }

        return $totals;
    }

    private function averageForPlayers($data)
    {
        $averages=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $averages[$key] = ['swings' => $item->count()];
            foreach ($this->trajectories as $type) {
                $averages[$key][$type] = round($item->where('trajectory', '=', $type)->avg('velocity')??0, 2);
            }
        }

        return $averages;
    }

    private function maxVelocitiesPlayer($data)
    {
        $maxVelocities=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $maxVelocities[$key] = ['swings' => $item->count()];
            foreach ($this->trajectories as $type) {
                $maxVelocities[$key][$type] = $item->where('trajectory', '=', $type)->max('velocity');
            }
        }

        return $maxVelocities;
    }

    private function percentsTeam($data)
    {
        $total = $data->count();
        $percent = ['swings' => $total];
        foreach ($this->trajectories as $type) {
            $percent[$type] = round(Helper::caseDivide($data->where('trajectory', '=', $type)->count(), $total)*100, 2);
        }
        return $percent;
    }

    private function percentsPlayers($data)
    {
        $percents=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals =  $item->count();
            $percents[$key] = ['swings' =>$totals];
            foreach ($this->trajectories as $type) {
                $percents[$key][$type] = round(Helper::caseDivide($item->where('trajectory', '=', $type)->count(), $totals)*100, 2);
            }
        }

        return $percents;
    }

}
