<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Player;
use App\Utils\Helper;
use Carbon\Carbon;

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
     *
     * Velocity thresholds are age-adjusted with ranges:
     * Average / Above Average / Elite.
     */
    public function evs($data): array
    {
        if (0 === $data->count()) {
            return [];
        }

        $clamp     = fn($v, $min, $max) => max($min, min($max, (float)$v));

        $playerIds = $data->pluck('user_id')->filter()->unique()->values()->all();
        $bornDates = empty($playerIds)
            ? collect()
            : Player::query()->whereIn('user_id', $playerIds)->pluck('born_date', 'user_id');

        $swingScores = [];
        $rangeCounts = [
            'below_average' => 0,
            'average' => 0,
            'above_average' => 0,
            'elite' => 0,
        ];
        $playerMaxById = [];
        $eliteThresholdByPlayer = [];
        $hardHitThresholdByPlayer = [];

        foreach ($data as $row) {
            $ev = (float) ($row->velocity ?? 0);
            if ($ev < 10 || $ev > 130) continue;

            $playerId = (string) ($row->user_id ?? '');
            $age = $this->ageFromBornDate($bornDates->get($playerId));
            $thresholds = $this->exitVelocityThresholdsForAge($age);

            $avgMin = (float) $thresholds['avg_min'];
            $avgMax = (float) $thresholds['avg_max'];
            $aboveMin = (float) $thresholds['above_min'];
            $aboveMax = (float) $thresholds['above_max'];
            $eliteMin = (float) $thresholds['elite_min'];

            $hardHitEV = $aboveMin;
            $eliteEV = $eliteMin;

            $traj = strtoupper((string) ($row->trajectory ?? ''));
            if (!in_array($traj, ['LD','FB','GB','PU'], true)) $traj = 'GB';

            $evPts   = $clamp(60 * ($ev - $avgMin) / max(1, $eliteEV - $avgMin), 0, 60);
            $tBonus  = match ($traj) { 'LD' => 25, 'FB' => 18, 'PU' => 6, default => 12 };
            $hhBonus = $ev >= $hardHitEV ? 15.0 : 15 * ($ev / $hardHitEV);

            $rangeLabel = 'below_average';
            if ($ev >= $eliteMin) {
                $rangeLabel = 'elite';
            } elseif ($ev >= $aboveMin && $ev <= $aboveMax) {
                $rangeLabel = 'above_average';
            } elseif ($ev >= $avgMin && $ev <= $avgMax) {
                $rangeLabel = 'average';
            }
            $rangeCounts[$rangeLabel]++;

            if ($playerId !== '') {
                $eliteThresholdByPlayer[$playerId] = $eliteEV;
                $hardHitThresholdByPlayer[$playerId] = $hardHitEV;
                $playerMaxById[$playerId] = max($playerMaxById[$playerId] ?? 0, $ev);
            }

            $swingScores[] = [
                'ev'     => $ev,
                'traj'   => $traj,
                'evPts'  => $evPts,
                'tBonus' => $tBonus,
                'hh'     => $hhBonus,
                'hardHitEV' => $hardHitEV,
                'total'  => $clamp($evPts + $tBonus + $hhBonus, 0, 100),
            ];
        }

        $total = count($swingScores);
        if ($total === 0) return [];

        $base             = array_sum(array_column($swingScores, 'total')) / $total;
        $hardHitCount     = count(array_filter($swingScores, fn($s) => $s['ev'] >= $s['hardHitEV']));
        $hhPct            = ($hardHitCount / $total) * 100;
        $consistencyBonus = $hhPct >= 50 ? 5 : ($hhPct >= 35 ? 3 : ($hhPct >= 20 ? 1 : 0));
        $evs              = round($clamp($base + $consistencyBonus, 0, 100), 1);

        $eliteHitters = 0;
        foreach ($playerMaxById as $pid => $maxV) {
            $eliteThresh = (float) ($eliteThresholdByPlayer[$pid] ?? 999);
            if ($maxV >= $eliteThresh) {
                $eliteHitters++;
            }
        }

        $playersScored = count($playerMaxById);
        $avgHardHitThreshold = count($hardHitThresholdByPlayer) > 0
            ? round(array_sum($hardHitThresholdByPlayer) / count($hardHitThresholdByPlayer), 1)
            : null;
        $avgEliteThreshold = count($eliteThresholdByPlayer) > 0
            ? round(array_sum($eliteThresholdByPlayer) / count($eliteThresholdByPlayer), 1)
            : null;

        $evVals    = array_column($swingScores, 'ev');
        $trajCount = array_count_values(array_column($swingScores, 'traj'));
        $pct       = fn($k) => round((($trajCount[$k] ?? 0) / $total) * 100, 1);
        // Average exit velocity per trajectory (LD / FB / GB) for the EV panel.
        $avgEvByTraj = function (string $k) use ($swingScores): ?float {
            $vals = array_column(array_values(array_filter($swingScores, fn ($s) => $s['traj'] === $k)), 'ev');

            return count($vals) ? round(array_sum($vals) / count($vals), 1) : null;
        };

        return [
            'evs'             => $evs,
            'total'           => $total,
            'avgEV'           => round(array_sum($evVals) / $total, 1),
            'topEV'           => round(max($evVals), 1),
            'hardHitCount'    => $hardHitCount,
            'hhPct'           => round($hhPct, 1),
            'avgHardHitThreshold' => $avgHardHitThreshold,
            'avgEliteThreshold' => $avgEliteThreshold,
            'eliteHitters' => $eliteHitters,
            'playersScored' => $playersScored,
            'eliteHitterRate' => $playersScored > 0 ? round(($eliteHitters / $playersScored) * 100, 1) : 0.0,
            'evPowerScore'    => round((array_sum(array_column($swingScores,'evPts'))  / $total / 60)  * 100, 1),
            'trajectoryScore' => round((array_sum(array_column($swingScores,'tBonus')) / $total / 25)  * 100, 1),
            'hardHitScore'    => round((array_sum(array_column($swingScores,'hh'))     / $total / 15)  * 100, 1),
            'rangeCounts' => $rangeCounts,
            'rangePercents' => [
                'below_average' => round(($rangeCounts['below_average'] / $total) * 100, 1),
                'average' => round(($rangeCounts['average'] / $total) * 100, 1),
                'above_average' => round(($rangeCounts['above_average'] / $total) * 100, 1),
                'elite' => round(($rangeCounts['elite'] / $total) * 100, 1),
            ],
            'ldPct'           => $pct('LD'),
            'fbPct'           => $pct('FB'),
            'gbPct'           => $pct('GB'),
            'puPct'           => $pct('PU'),
            'ldAvgEV'         => $avgEvByTraj('LD'),
            'fbAvgEV'         => $avgEvByTraj('FB'),
            'gbAvgEV'         => $avgEvByTraj('GB'),
        ];
    }

    private function ageFromBornDate(mixed $bornDate): ?int
    {
        if (empty($bornDate)) {
            return null;
        }

        try {
            $age = Carbon::parse((string) $bornDate)->age;
            return $age > 0 ? $age : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function exitVelocityThresholdsForAge(?int $age): array
    {
        $groups = [
            '8U' => ['avg_min' => 30, 'avg_max' => 39, 'above_min' => 40, 'above_max' => 49, 'elite_min' => 50],
            '10U' => ['avg_min' => 45, 'avg_max' => 50, 'above_min' => 51, 'above_max' => 59, 'elite_min' => 60],
            '12U' => ['avg_min' => 52, 'avg_max' => 59, 'above_min' => 60, 'above_max' => 64, 'elite_min' => 65],
            '14U' => ['avg_min' => 65, 'avg_max' => 70, 'above_min' => 71, 'above_max' => 79, 'elite_min' => 80],
            '16U' => ['avg_min' => 65, 'avg_max' => 70, 'above_min' => 71, 'above_max' => 79, 'elite_min' => 80],
            '18U' => ['avg_min' => 80, 'avg_max' => 85, 'above_min' => 86, 'above_max' => 91, 'elite_min' => 92],
            'COLLEGE' => ['avg_min' => 85, 'avg_max' => 95, 'above_min' => 96, 'above_max' => 100, 'elite_min' => 101],
            'PRO' => ['avg_min' => 88, 'avg_max' => 98, 'above_min' => 99, 'above_max' => 105, 'elite_min' => 106],
        ];

        if ($age === null) return $groups['14U'];
        if ($age <= 8) return $groups['8U'];
        if ($age <= 10) return $groups['10U'];
        if ($age <= 12) return $groups['12U'];
        if ($age <= 14) return $groups['14U'];
        if ($age <= 16) return $groups['16U'];
        if ($age <= 18) return $groups['18U'];
        if ($age <= 22) return $groups['COLLEGE'];
        return $groups['PRO'];
    }

}
