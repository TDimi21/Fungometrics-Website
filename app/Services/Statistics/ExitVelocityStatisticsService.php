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

    /**
     * Exit Velocity Score (EVS) — mirrors computeEVScore() in TeamStatsPanel/index.js
     *
     * Per-swing: evPts(60) + trajectoryBonus(25) + hardHitBonus(15), then +consistency bonus
     */
    public function evs($data): array
    {
        if (0 === $data->count()) {
            return [];
        }

        $hardHitEV = 90;
        $eliteEV   = 100;
        $clamp     = fn($v, $min, $max) => max($min, min($max, (float)$v));

        $swingScores = [];
        foreach ($data as $row) {
            $ev = (float) ($row->velocity ?? 0);
            if ($ev < 10 || $ev > 130) continue;

            $traj = strtoupper((string) ($row->trajectory ?? ''));
            if (!in_array($traj, ['LD','FB','GB','PU'], true)) $traj = 'GB';

            $evPts   = $clamp(60 * ($ev - ($hardHitEV - 10)) / max(1, $eliteEV - ($hardHitEV - 10)), 0, 60);
            $tBonus  = match ($traj) { 'LD' => 25, 'FB' => 18, 'PU' => 6, default => 12 };
            $hhBonus = $ev >= $hardHitEV ? 15.0 : 15 * ($ev / $hardHitEV);

            $swingScores[] = [
                'ev'     => $ev,
                'traj'   => $traj,
                'evPts'  => $evPts,
                'tBonus' => $tBonus,
                'hh'     => $hhBonus,
                'total'  => $clamp($evPts + $tBonus + $hhBonus, 0, 100),
            ];
        }

        $total = count($swingScores);
        if ($total === 0) return [];

        $base             = array_sum(array_column($swingScores, 'total')) / $total;
        $hardHitCount     = count(array_filter($swingScores, fn($s) => $s['ev'] >= $hardHitEV));
        $hhPct            = ($hardHitCount / $total) * 100;
        $consistencyBonus = $hhPct >= 50 ? 5 : ($hhPct >= 35 ? 3 : ($hhPct >= 20 ? 1 : 0));
        $evs              = round($clamp($base + $consistencyBonus, 0, 100), 1);

        $evVals    = array_column($swingScores, 'ev');
        $trajCount = array_count_values(array_column($swingScores, 'traj'));
        $pct       = fn($k) => round((($trajCount[$k] ?? 0) / $total) * 100, 1);

        return [
            'evs'             => $evs,
            'total'           => $total,
            'avgEV'           => round(array_sum($evVals) / $total, 1),
            'topEV'           => round(max($evVals), 1),
            'hardHitCount'    => $hardHitCount,
            'hhPct'           => round($hhPct, 1),
            'evPowerScore'    => round((array_sum(array_column($swingScores,'evPts'))  / $total / 60)  * 100, 1),
            'trajectoryScore' => round((array_sum(array_column($swingScores,'tBonus')) / $total / 25)  * 100, 1),
            'hardHitScore'    => round((array_sum(array_column($swingScores,'hh'))     / $total / 15)  * 100, 1),
            'ldPct'           => $pct('LD'),
            'fbPct'           => $pct('FB'),
            'gbPct'           => $pct('GB'),
            'puPct'           => $pct('PU'),
        ];
    }

}
