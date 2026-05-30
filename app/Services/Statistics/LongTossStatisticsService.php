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

    /**
     * Long Toss Score (LTS) — mirrors computeLongTossScore() in TeamStatsPanel/index.js
     *
     * 5 components (100 pts total):
     *   Extension   25 pts — player max distances vs 250ft target
     *   Carry       25 pts — hop quality on top 20% throws
     *   Consistency 20 pts — per-player CV of distances
     *   Progression 20 pts — oldest vs newest session avg dist
     *   Availability 10 pts — completed sessions ratio
     */
    public function lts($data, array $practiceIds = []): array
    {
        if (0 === $data->count()) {
            return [];
        }

        $EXTENSION_TARGET = 250;
        $clamp = fn($v, $min, $max) => max($min, min($max, (float) $v));
        $hopScore = fn($hop) => match(true) { $hop <= 0 => 100, $hop === 1 => 80, $hop === 2 => 55, default => 20 };

        // Collect valid throws
        $allThrows = [];
        foreach ($data as $row) {
            $dist = (float) ($row->distance ?? 0);
            if ($dist < 20 || $dist > 600) continue;
            $allThrows[] = [
                'dist'   => $dist,
                'hop'    => (int) ($row->hop ?? 3),
                'userId' => (string) ($row->user_id ?? 'unknown'),
                'date'   => $row->created_at ?? null,
            ];
        }

        if (empty($allThrows)) return [];

        // Group by player
        $playerDists = [];
        foreach ($allThrows as $t) {
            $playerDists[$t['userId']][] = $t['dist'];
        }

        // 1. Extension (25 pts)
        $extWeightedSum = 0; $extMaxPossible = 0;
        foreach ($playerDists as $dists) {
            $maxD = max($dists);
            $pct  = $maxD / $EXTENSION_TARGET;
            $w    = $pct >= 1.0 ? 5 : ($pct >= 0.90 ? 3 : ($pct >= 0.75 ? 2 : ($pct >= 0.60 ? 1 : 0)));
            $extWeightedSum += $w;
            $extMaxPossible += 5;
        }
        $extensionScore = $extMaxPossible > 0 ? $clamp(($extWeightedSum / $extMaxPossible) * 25, 0, 25) : 0;

        // 2. Carry (25 pts) — top 20% throws per player scored by hops
        $carryScores = [];
        foreach ($playerDists as $pid => $dists) {
            $pThrows = array_filter($allThrows, fn($t) => $t['userId'] === $pid);
            usort($pThrows, fn($a,$b) => $b['dist'] <=> $a['dist']);
            $topN = max(1, (int) ceil(count($pThrows) * 0.20));
            $topN = max($topN, 3);
            foreach (array_slice($pThrows, 0, $topN) as $t) {
                $carryScores[] = $hopScore($t['hop']);
            }
        }
        $avgCarry   = count($carryScores) ? array_sum($carryScores) / count($carryScores) : 50;
        $carryScore = $clamp(($avgCarry / 100) * 25, 0, 25);

        // 3. Consistency (20 pts)
        $consScores = [];
        foreach ($playerDists as $dists) {
            if (count($dists) < 2) continue;
            $mean = array_sum($dists) / count($dists);
            $variance = array_sum(array_map(fn($d) => ($d - $mean) ** 2, $dists)) / count($dists);
            $cv = $mean > 0 ? sqrt($variance) / $mean : 1;
            $consScores[] = $clamp(1 - $cv, 0, 1) * 100;
        }
        $avgCons  = count($consScores) ? array_sum($consScores) / count($consScores) : 50;
        $consistencyScore = $clamp(($avgCons / 100) * 20, 0, 20);

        // 4. Progression (20 pts) — session avg trend
        $sessionAvgs = [];
        $byPractice = [];
        foreach ($allThrows as $t) {
            $pid = $t['date'] ? date('Y-m-d', strtotime((string) $t['date'])) : 'unknown';
            $byPractice[$pid][] = $t['dist'];
        }
        foreach ($byPractice as $date => $dists) {
            $sessionAvgs[$date] = array_sum($dists) / count($dists);
        }
        $progressionScore = 10;
        if (count($sessionAvgs) >= 2) {
            ksort($sessionAvgs);
            $vals   = array_values($sessionAvgs);
            $oldest = $vals[0];
            $newest = $vals[count($vals) - 1];
            $pctChange = $oldest > 0 ? ($newest - $oldest) / $oldest : 0;
            $progressionScore = $clamp(10 + ($pctChange * 100), 0, 20);
        }

        // 5. Availability (10 pts) — based on total sessions vs those with data
        $sessionCount      = count($sessionAvgs);
        $availabilityScore = $sessionCount > 0 ? $clamp(min($sessionCount / 8, 1) * 10, 0, 10) : 5;

        $totalScore  = round($clamp($extensionScore + $carryScore + $consistencyScore + $progressionScore + $availabilityScore, 0, 100), 1);
        $playerMaxes = array_map(fn($d) => max($d), array_values($playerDists));
        $totalPlayers = count($playerDists);
        $zeroHop = count(array_filter($allThrows, fn($t) => $t['hop'] <= 0));
        $extReached = count(array_filter($playerMaxes, fn($d) => $d >= $EXTENSION_TARGET));

        return [
            'lts'             => $totalScore,
            'total'           => count($allThrows),
            'extensionScore'  => round($extensionScore,  1),
            'carryScore'      => round($carryScore,      1),
            'consistencyScore'=> round($consistencyScore,1),
            'progressionScore'=> round($progressionScore,1),
            'availabilityScore'=> round($availabilityScore,1),
            'totalPlayers'    => $totalPlayers,
            'avgMaxDist'      => $totalPlayers > 0 ? round(array_sum($playerMaxes) / $totalPlayers, 1) : null,
            'extensionPct'    => $totalPlayers > 0 ? round(($extReached / $totalPlayers) * 100, 1) : 0,
            'avgCarryScore'   => round($avgCarry, 1),
            'zeroHopRate'     => count($allThrows) > 0 ? round(($zeroHop / count($allThrows)) * 100, 1) : 0,
            'sessionCount'    => $sessionCount,
        ];
    }

}
