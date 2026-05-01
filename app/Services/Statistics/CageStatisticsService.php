<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Utils\Helper;

final class CageStatisticsService
{
    public array $rangesLaunchAngles;

    public array $rangesSprayAngles;

    public function __construct()
    {
        $this->rangesLaunchAngles = config('constants.launchAngleCageRanges');
        $this->rangesSprayAngles = config('constants.sprayAngleCageRanges');
    }

    public function launchAngleTotals($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->launchAngleTotalsTeam($data);
        $result['players'] = $this->launchAngleTotalsPlayers($data);
        return $result;
    }

    public function launchAnglePercents($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->launchAnglePercentsTeam($data);
        $result['players'] = $this->launchAnglePercentsPlayers($data);
        return $result;
    }

    public function launchAngleExitVelocityAverage($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->launchAngleExitVelocityAverageTeam($data);
        $result['players'] = $this->launchAngleExitVelocityAveragePlayers($data);
        return $result;
    }

    public function sprayAngleTotals($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->sprayAngleTotalsTeam($data);
        $result['players'] = $this->sprayAngleTotalsPlayers($data);
        return $result;
    }

    public function sprayAnglePercents($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->sprayAnglePercentsTeam($data);
        $result['players'] = $this->sprayAnglePercentsPlayers($data);
        return $result;


    }

    public function sprayAngleExitVelocityAverage($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->sprayAngleExitVelocityAverageTeam($data);
        $result['players'] = $this->sprayAngleExitVelocityAveragePlayers($data);
        return $result;


    }

    private function launchAngleTotalsTeam($data)
    {
        $launchAngleTotals = ['swings' => $data->count()];
        foreach ($this->rangesLaunchAngles as $range => $limits) {
            $launchAngleTotals[$range] = $data->whereBetween('launch_angle', $limits)->count();
        }
        return $launchAngleTotals;
    }


    private function launchAngleTotalsPlayers($data)
    {
        $launchAngleTotals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $launchAngleTotals[$key] = ['swings' => $item->count()];
            foreach ($this->rangesLaunchAngles as $range => $limits) {
                $launchAngleTotals[$key][$range] = $item->whereBetween('launch_angle', $limits)->count();
            }
        }

        return $launchAngleTotals;
    }

    private function launchAnglePercentsTeam($data)
    {
        $totals = $data->count();
        $launchAngleTotals = ['swings' => $totals];
        foreach ($this->rangesLaunchAngles as $range => $limits) {
            $launchAngleTotals[$range] = round(Helper::caseDivide($data->whereBetween('launch_angle', $limits)->count(), $totals)*100, 2);
        }
        return $launchAngleTotals;
    }

    private function launchAnglePercentsPlayers($data)
    {
        $launchAnglePercents=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $launchAnglePercents[$key] = ['swings' => $totals];
            foreach ($this->rangesLaunchAngles as $range => $limits) {
                $launchAnglePercents[$key][$range] = round(Helper::caseDivide($item->whereBetween('launch_angle', $limits)->count(), $totals)
                    *100, 2);
            }
        }

        return $launchAnglePercents;
    }

    private function launchAngleExitVelocityAverageTeam($data)
    {
        $totals = $data->count();
        $launchAngleTotals = ['swings' => $totals];
        foreach ($this->rangesLaunchAngles as $range => $limits) {
            $launchAngleTotals[$range] = round($data->whereBetween('launch_angle', $limits)->avg('launch_angle_velocity')??0, 2);
        }
        return $launchAngleTotals;
    }

    private function launchAngleExitVelocityAveragePlayers($data)
    {
        $launchAngleExitVelocityAverage=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $launchAngleExitVelocityAverage[$key] = ['swings' => $totals];
            foreach ($this->rangesLaunchAngles as $range => $limits) {
                $launchAngleExitVelocityAverage[$key][$range] = round($item->whereBetween('launch_angle', $limits)
                    ->avg('launch_angle_velocity')??0, 2);
            }
        }

        return $launchAngleExitVelocityAverage;
    }

    private function sprayAngleTotalsTeam($data)
    {
        $sprayAngleTotals = ['swings' => $data->count()];
        foreach ($this->rangesSprayAngles as $range => $limits) {
            $sprayAngleTotals[$range] = $data->whereBetween('spray_angle', $limits)->count();
        }
        return $sprayAngleTotals;
    }

    private function sprayAngleTotalsPlayers($data)
    {
        $sprayAngleTotals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $sprayAngleTotals[$key] = ['swings' => $item->count()];
            foreach ($this->rangesSprayAngles as $range => $limits) {
                $sprayAngleTotals[$key][$range] = $item->whereBetween('spray_angle', $limits)->count();
            }
        }

        return $sprayAngleTotals;
    }

    private function sprayAnglePercentsTeam($data)
    {
        $totals = $data->count();
        $sprayAngleTotals = ['swings' => $totals];
        foreach ($this->rangesSprayAngles as $range => $limits) {
            $sprayAngleTotals[$range] = round(Helper::caseDivide($data->whereBetween('spray_angle', $limits)->count(), $totals)*100, 2);
        }
        return $sprayAngleTotals;
    }

    private function sprayAnglePercentsPlayers($data)
    {
        $sprayAnglePercents=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $sprayAnglePercents[$key] = ['swings' => $totals];
            foreach ($this->rangesSprayAngles as $range => $limits) {
                $sprayAnglePercents[$key][$range] = round(Helper::caseDivide($item->whereBetween(
                    'spray_angle',
                    $limits
                )->count(), $totals)*100, 2);
            }
        }

        return $sprayAnglePercents;
    }

    private function sprayAngleExitVelocityAverageTeam($data)
    {
        $sprayAngleTotals = ['swings' => $data->count()];
        foreach ($this->rangesSprayAngles as $range => $limits) {
            $sprayAngleTotals[$range] = round($data->whereBetween('spray_angle', $limits)
                ->avg('launch_angle_velocity')??0, 2);
        }
        return $sprayAngleTotals;
    }

    private function sprayAngleExitVelocityAveragePlayers($data)
    {
        $sprayAngleExitVelocityAverage=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $sprayAngleExitVelocityAverage[$key] = ['swings' => $totals];
            foreach ($this->rangesSprayAngles as $range => $limits) {
                $sprayAngleExitVelocityAverage[$key][$range] = round($item->whereBetween('spray_angle', $limits)
                    ->avg('launch_angle_velocity')??0, 2);
            }
        }

        return $sprayAngleExitVelocityAverage;
    }


}
