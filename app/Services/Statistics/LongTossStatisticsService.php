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
        * Long Toss Score (LTS) — chart-based model.
     *
     * 5 components (100 pts total):
        *   Extension   25 pts — no-hop peak distance converted to estimated peak mound velo
        *   Carry       25 pts — throw intensity (chart %) blended with hop quality
     *   Consistency 20 pts — per-player CV of distances
     *   Progression 20 pts — oldest vs newest session avg dist
     *   Availability 10 pts — completed sessions ratio
     */
    public function lts($data, array $practiceIds = []): array
    {
        if (0 === $data->count()) {
            return [];
        }

        $clamp = fn($v, $min, $max) => max($min, min($max, (float) $v));
        $hopScore = fn($hop) => match(true) { $hop <= 0 => 100, $hop === 1 => 82, $hop === 2 => 58, default => 25 };

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
        $playerThrows = [];
        foreach ($allThrows as $t) {
            $playerDists[$t['userId']][] = $t['dist'];
            $playerThrows[$t['userId']][] = $t;
        }

        // 1. Extension (25 pts) — convert no-hop distance to estimated mound velo from chart
        $extensionPctScores = [];
        $estimatedPeakVelos = [];
        $peakNoHopDistances = [];
        $intensityScores = [];
        $carryScores = [];

        foreach ($playerThrows as $pid => $throws) {
            $noHopDistances = array_values(array_map(
                fn($t) => (float) $t['dist'],
                array_filter($throws, fn($t) => (int) $t['hop'] <= 0)
            ));

            $peakNoHop = !empty($noHopDistances)
                ? max($noHopDistances)
                : max(array_column($throws, 'dist'));

            $peakNoHop = (float) $peakNoHop;
            $peakNoHopDistances[] = $peakNoHop;

            $estimatedPeakVelo = $this->estimatePeakMoundVelocityFromDistance($peakNoHop);
            $estimatedPeakVelos[] = $estimatedPeakVelo;

            // 67 mph baseline → 100 mph+ ceiling
            $extensionPctScores[] = $clamp((($estimatedPeakVelo - 67.0) / 33.0) * 100.0, 0.0, 100.0);

            foreach ($throws as $t) {
                $dist = (float) $t['dist'];
                $hop = (int) $t['hop'];

                $intensityPct = $this->estimateIntensityPctFromDistance($estimatedPeakVelo, $dist);
                $intensityScores[] = $intensityPct;

                $carryScores[] = ($intensityPct * $hopScore($hop)) / 100.0;
            }
        }

        $avgExtensionPct = count($extensionPctScores) ? array_sum($extensionPctScores) / count($extensionPctScores) : 0.0;
        $extensionScore = $clamp(($avgExtensionPct / 100.0) * 25.0, 0.0, 25.0);

        // 2. Carry (25 pts) — chart intensity × hop quality
        $avgCarry   = count($carryScores) ? array_sum($carryScores) / count($carryScores) : 50;
        $carryScore = $clamp(($avgCarry / 100.0) * 25.0, 0.0, 25.0);

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
        $eliteThrowers = count(array_filter($estimatedPeakVelos, fn($v) => $v >= 90.0));
        $avgIntensity = count($intensityScores) ? array_sum($intensityScores) / count($intensityScores) : 0.0;
        $avgEstimatedPeakVelo = count($estimatedPeakVelos) ? array_sum($estimatedPeakVelos) / count($estimatedPeakVelos) : null;
        $avgPeakNoHopDistance = count($peakNoHopDistances) ? array_sum($peakNoHopDistances) / count($peakNoHopDistances) : null;
        $chartPoints = array_values(array_map(
            fn(array $throw, int $index) => [
                'label' => (string) ($index + 1),
                'distance' => round((float) $throw['dist'], 1),
                'dist' => round((float) $throw['dist'], 1),
                'hop' => (int) $throw['hop'],
                'player_id' => $throw['userId'],
                'date' => $throw['date'] ? (string) $throw['date'] : null,
            ],
            $allThrows,
            array_keys($allThrows)
        ));

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
            'extensionPct'    => round($avgExtensionPct, 1),
            'avgCarryScore'   => round($avgCarry, 1),
            'zeroHopRate'     => count($allThrows) > 0 ? round(($zeroHop / count($allThrows)) * 100, 1) : 0,
            'sessionCount'    => $sessionCount,
            'eliteThrowers'   => $eliteThrowers,
            'eliteThrowerRate'=> $totalPlayers > 0 ? round(($eliteThrowers / $totalPlayers) * 100, 1) : 0,
            'avgIntensityPct' => round($avgIntensity, 1),
            'avgEstimatedPeakVelo' => $avgEstimatedPeakVelo !== null ? round($avgEstimatedPeakVelo, 1) : null,
            'avgPeakNoHopDist' => $avgPeakNoHopDistance !== null ? round($avgPeakNoHopDistance, 1) : null,
            'chartPoints'      => $chartPoints,
            'chart_points'     => $chartPoints,
        ];
    }

    private function longTossChartRows(): array
    {
        return [
            ['velo' => 67.0,  'peak' => 210.0, 'intensity' => [50=>105,55=>116,60=>126,65=>137,70=>147,75=>158,80=>168,85=>179,90=>189,95=>200,100=>210]],
            ['velo' => 72.0,  'peak' => 240.0, 'intensity' => [50=>120,55=>132,60=>144,65=>156,70=>168,75=>180,80=>192,85=>204,90=>216,95=>228,100=>240]],
            ['velo' => 77.0,  'peak' => 260.0, 'intensity' => [50=>130,55=>143,60=>156,65=>169,70=>182,75=>195,80=>208,85=>221,90=>234,95=>247,100=>260]],
            ['velo' => 82.0,  'peak' => 280.0, 'intensity' => [50=>140,55=>154,60=>168,65=>182,70=>196,75=>210,80=>224,85=>238,90=>252,95=>266,100=>280]],
            ['velo' => 87.0,  'peak' => 300.0, 'intensity' => [50=>150,55=>165,60=>180,65=>195,70=>210,75=>225,80=>240,85=>255,90=>270,95=>285,100=>300]],
            ['velo' => 92.0,  'peak' => 330.0, 'intensity' => [50=>165,55=>182,60=>198,65=>215,70=>231,75=>248,80=>264,85=>281,90=>297,95=>314,100=>330]],
            ['velo' => 97.0,  'peak' => 360.0, 'intensity' => [50=>180,55=>198,60=>216,65=>234,70=>252,75=>270,80=>288,85=>306,90=>324,95=>342,100=>360]],
            ['velo' => 100.0, 'peak' => 410.0, 'intensity' => [50=>205,55=>226,60=>246,65=>267,70=>287,75=>308,80=>328,85=>349,90=>369,95=>390,100=>410]],
        ];
    }

    private function estimatePeakMoundVelocityFromDistance(float $distance): float
    {
        $rows = $this->longTossChartRows();
        $distance = max(0.0, $distance);

        if ($distance <= $rows[0]['peak']) {
            $lo = $rows[0];
            return $lo['velo'] * ($distance / max(1.0, $lo['peak']));
        }

        $last = $rows[count($rows) - 1];
        if ($distance >= $last['peak']) {
            $excess = $distance - $last['peak'];
            return min(105.0, $last['velo'] + ($excess / 20.0));
        }

        for ($i = 0; $i < count($rows) - 1; $i++) {
            $a = $rows[$i];
            $b = $rows[$i + 1];
            if ($distance >= $a['peak'] && $distance <= $b['peak']) {
                $pct = ($distance - $a['peak']) / max(1.0, ($b['peak'] - $a['peak']));
                return $a['velo'] + (($b['velo'] - $a['velo']) * $pct);
            }
        }

        return 67.0;
    }

    private function estimateIntensityPctFromDistance(float $estimatedPeakVelo, float $distance): float
    {
        $row = $this->nearestChartRowByVelocity($estimatedPeakVelo);
        $chart = $row['intensity'];
        ksort($chart);

        if ($distance <= $chart[50]) {
            return max(0.0, min(50.0, ($distance / max(1.0, $chart[50])) * 50.0));
        }

        if ($distance >= $chart[100]) {
            return 100.0;
        }

        $keys = array_keys($chart);
        for ($i = 0; $i < count($keys) - 1; $i++) {
            $k1 = (int) $keys[$i];
            $k2 = (int) $keys[$i + 1];
            $d1 = (float) $chart[$k1];
            $d2 = (float) $chart[$k2];
            if ($distance >= $d1 && $distance <= $d2) {
                $pct = ($distance - $d1) / max(1.0, ($d2 - $d1));
                return $k1 + (($k2 - $k1) * $pct);
            }
        }

        return 50.0;
    }

    private function nearestChartRowByVelocity(float $velo): array
    {
        $rows = $this->longTossChartRows();
        $best = $rows[0];
        $bestDiff = abs($velo - $best['velo']);

        foreach ($rows as $row) {
            $diff = abs($velo - $row['velo']);
            if ($diff < $bestDiff) {
                $best = $row;
                $bestDiff = $diff;
            }
        }

        return $best;
    }

}
