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

    /**
     * Weighted Ball Score (WBS) — mirrors computeWeightedBallScore() in TeamStatsPanel/index.js
     *
     * 5 components:
     *   Velocity Score      30 pts — actual vs target velo by ball weight
     *   Ball Progression    20 pts — variety of weights used per player
     *   Consistency         20 pts — per-player CV of velocities
     *   Progress            20 pts — oldest vs newest session avg velo
     *   Availability        10 pts — completed sessions ratio
     */
    public function wbs($data): array
    {
        if (0 === $data->count()) {
            return [];
        }

        $TARGET_BY_OZ  = [1=>82, 3=>82, 4=>80, 5=>78, 6=>75, 7=>72, 9=>70, 11=>68, 13=>66];
        $DEFAULT_TARGET = 78;
        $clamp = fn($v, $min, $max) => max($min, min($max, (float) $v));

        $allThrows = [];
        foreach ($data as $row) {
            $velo = (float) ($row->velocity ?? 0);
            if ($velo <= 20 || $velo >= 130) continue;
            $allThrows[] = [
                'velo'   => $velo,
                'weight' => (int) ($row->weight ?? 5),
                'userId' => (string) ($row->user_id ?? 'unknown'),
                'date'   => $row->created_at ?? null,
            ];
        }

        if (empty($allThrows)) return [];

        $playerIds = array_unique(array_column($allThrows, 'userId'));
        $totalPlayers = count($playerIds);

        // 1. Velocity Score (30 pts)
        $playerVeloScores = [];
        foreach ($playerIds as $pid) {
            $pThrows = array_filter($allThrows, fn($t) => $t['userId'] === $pid);
            $scores  = array_map(function ($t) use ($TARGET_BY_OZ, $DEFAULT_TARGET) {
                $target = $TARGET_BY_OZ[$t['weight']] ?? $DEFAULT_TARGET;
                return min($t['velo'] / $target, 1.0) * 100;
            }, $pThrows);
            $playerVeloScores[] = array_sum($scores) / count($scores);
        }
        $avgVeloScore   = array_sum($playerVeloScores) / $totalPlayers;
        $velocityScore  = $clamp(($avgVeloScore / 100) * 30, 0, 30);

        // 2. Ball Progression (20 pts) — weight variety per player
        $varietyScores = [];
        foreach ($playerIds as $pid) {
            $weights = array_unique(array_map(
                fn($t) => $t['weight'],
                array_filter($allThrows, fn($t) => $t['userId'] === $pid)
            ));
            $varietyScores[] = min(count($weights) / 3, 1.0);
        }
        $avgVariety           = array_sum($varietyScores) / $totalPlayers;
        $ballProgressionScore = $clamp($avgVariety * 20, 0, 20);
        $uniqueWeights        = array_unique(array_column($allThrows, 'weight'));
        sort($uniqueWeights);

        // 3. Consistency (20 pts)
        $consScores = [];
        foreach ($playerIds as $pid) {
            $velos = array_column(array_filter($allThrows, fn($t) => $t['userId'] === $pid), 'velo');
            if (count($velos) < 2) continue;
            $mean   = array_sum($velos) / count($velos);
            $stdDev = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $velos)) / count($velos));
            $cv     = $mean > 0 ? $stdDev / $mean : 1;
            $consScores[] = $clamp(1 - $cv, 0, 1) * 100;
        }
        $avgCons          = count($consScores) ? array_sum($consScores) / count($consScores) : 50;
        $consistencyScore = $clamp(($avgCons / 100) * 20, 0, 20);

        // 4. Progress (20 pts)
        $byDate = [];
        foreach ($allThrows as $t) {
            $d = $t['date'] ? date('Y-m-d', strtotime((string) $t['date'])) : 'unknown';
            $byDate[$d][] = $t['velo'];
        }
        $sessionAvgs = [];
        foreach ($byDate as $date => $velos) {
            $sessionAvgs[$date] = array_sum($velos) / count($velos);
        }
        $progressScore = 10;
        if (count($sessionAvgs) >= 2) {
            ksort($sessionAvgs);
            $vals    = array_values($sessionAvgs);
            $oldest  = $vals[0];
            $newest  = $vals[count($vals) - 1];
            $pctChg  = $oldest > 0 ? ($newest - $oldest) / $oldest : 0;
            $progressScore = $clamp(10 + ($pctChg * 100), 0, 20);
        }
        $progressPct = count($sessionAvgs) >= 2
            ? (function () use ($sessionAvgs) {
                $vals = array_values($sessionAvgs);
                ksort($sessionAvgs); $v = array_values($sessionAvgs);
                return $v[0] > 0 ? round((($v[count($v)-1] - $v[0]) / $v[0]) * 100, 1) : 0;
            })()
            : 0;

        // 5. Availability (10 pts)
        $sessionCount      = count($sessionAvgs);
        $availabilityScore = $sessionCount > 0 ? $clamp(min($sessionCount / 8, 1) * 10, 0, 10) : 5;

        $totalScore = round($clamp($velocityScore + $ballProgressionScore + $consistencyScore + $progressScore + $availabilityScore, 0, 100), 1);
        $allVelos   = array_column($allThrows, 'velo');

        return [
            'wbs'                 => $totalScore,
            'total'               => count($allThrows),
            'velocityScore'       => round($velocityScore,       1),
            'ballProgressionScore'=> round($ballProgressionScore,1),
            'consistencyScore'    => round($consistencyScore,    1),
            'progressScore'       => round($progressScore,       1),
            'availabilityScore'   => round($availabilityScore,   1),
            'totalPlayers'        => $totalPlayers,
            'avgVelo'             => round(array_sum($allVelos) / count($allVelos), 1),
            'topVelo'             => round(max($allVelos), 1),
            'progressPct'         => $progressPct,
            'uniqueWeightsUsed'   => $uniqueWeights,
            'sessionCount'        => $sessionCount,
        ];
    }

}
