<?php

declare(strict_types=1);

namespace App\Services\Statistics;

final class WeightBallStatisticsService
{
    public array $weightedBalls = ['3', '4', '5', '6', '7', '9', '11', '13'];

    public function totals($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->getTeamTotals($data);
        $result['players'] = $this->getPlayersTotals($data);
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
        $totals = ['throws' => $data->count()];
        foreach ($this->weightedBalls as $balls) {
            $totals[$balls] = $data->where('weight', '=', $balls)->count();
        }
        return $totals;
    }

    private function averageForTeam($data)
    {
        $averages = ['throws' => $data->count()];
        foreach ($this->weightedBalls as $balls) {
            $averages[$balls] = round($data->where('weight', '=', $balls)->avg('velocity')??0, 2);
        }
        return $averages;
    }

    private function maxVelocitiesTeam($data)
    {
        $maxVelocities = ['throws' => $data->count()];
        foreach ($this->weightedBalls as $balls) {
            $maxVelocities[$balls] = $data->where('weight', '=', $balls)->max('velocity');
        }
        return $maxVelocities;
    }

    private function getPlayersTotals($data)
    {
        $totals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals[$key] = ['throws' => $item->count()];
            foreach ($this->weightedBalls as $balls) {
                $totals[$key][$balls] = $item->where('weight', '=', $balls)->count();
            }
        }

        return $totals;
    }

    private function averageForPlayers($data)
    {
        $averages=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $averages[$key] = ['throws' => $item->count()];
            foreach ($this->weightedBalls as $balls) {
                $averages[$key][$balls] = round($item->where('weight', '=', $balls)->avg('velocity')??0, 2);
            }
        }

        return $averages;
    }

    private function maxVelocitiesPlayer($data)
    {
        $maxVelocities=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $maxVelocities[$key] = ['throws' => $item->count()];
            foreach ($this->weightedBalls as $balls) {
                $maxVelocities[$key][$balls] = $item->where('weight', '=', $balls)->max('velocity');
            }
        }

        return $maxVelocities;
    }

}
