<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Player;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\PitchThrowTypes;
use App\Utils\Helper;
use Carbon\Carbon;

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

    /**
     * Bullpen Performance Score (0-100).
     *
     * Components:
     *  - Strike rate      35% — strikes / total pitches × 100
        *  - Velocity score   30% — age-adjusted FB velocity score (chart-based)
     *  - Pitch mix        20% — non-FB pitch types present / 3 (CB, CH, SL) × 100
     *  - First-pitch str  15% — pitches in zone on sort=1 / total sort-1 pitches × 100
     */
    public function bps($data): array
    {
        $total = $data->count();
        if ($total < 1) {
            return [];
        }

        // Strike rate (35%)
        $strikes     = $data->where('zone', 'S')->count();
        $strikeRate  = ($strikes / $total) * 100;

        // Velocity score (30%) — FB average, age-adjusted by chart
        $fbPitches   = $data->filter(fn($r) => strtoupper($r->type_throw ?? '') === 'FB'
                                            && isset($r->miles_per_hour)
                                            && $r->miles_per_hour > 30);
        $avgVelo     = $fbPitches->isNotEmpty() ? $fbPitches->avg('miles_per_hour') : 0.0;
        $topVelo     = $fbPitches->isNotEmpty() ? $fbPitches->max('miles_per_hour') : 0.0;

        $veloScore = 0.0;
        $eliteThrowers = 0;
        $pitchersScored = 0;
        $avgEliteThreshold = null;
        $avgAge = null;

        if ($fbPitches->isNotEmpty()) {
            $fbByPitcher = $fbPitches->groupBy(fn($r) => (string) ($r->pitcher_id ?? ''));
            $pitcherIds = $fbByPitcher->keys()->filter(fn($id) => $id !== '')->values()->all();

            $bornDates = Player::query()
                ->whereIn('user_id', $pitcherIds)
                ->pluck('born_date', 'user_id');

            $playerScores = [];
            $eliteThresholds = [];
            $ages = [];

            foreach ($fbByPitcher as $pitcherId => $rows) {
                $pid = (string) $pitcherId;
                if ($pid === '') {
                    continue;
                }

                $playerAvgVelo = (float) ($rows->avg('miles_per_hour') ?? 0);
                if ($playerAvgVelo <= 0) {
                    continue;
                }

                $age = $this->ageFromBornDate($bornDates->get($pid));
                $thresholds = $this->velocityChartThresholdsForAge($age);
                $playerScores[] = $this->ageAdjustedVelocityScore($playerAvgVelo, $thresholds);
                $eliteThresholds[] = $thresholds['elite'];
                if ($age !== null) {
                    $ages[] = $age;
                }

                if ($playerAvgVelo >= $thresholds['elite']) {
                    $eliteThrowers++;
                }
                $pitchersScored++;
            }

            $veloScore = count($playerScores) > 0
                ? array_sum($playerScores) / count($playerScores)
                : ($avgVelo > 0 ? min(100.0, ($avgVelo / 95) * 100) : 0.0);

            if (count($eliteThresholds) > 0) {
                $avgEliteThreshold = round(array_sum($eliteThresholds) / count($eliteThresholds), 1);
            }

            if (count($ages) > 0) {
                $avgAge = round(array_sum($ages) / count($ages), 1);
            }
        }

        // Pitch mix (20%) — count distinct non-FB types used
        $offspeed    = ['CB', 'CH', 'SL', 'CV'];
        $typesUsed   = $data->pluck('type_throw')
                            ->map(fn($t) => strtoupper($t ?? ''))
                            ->filter(fn($t) => in_array($t, $offspeed, true))
                            ->unique()
                            ->count();
        $mixScore    = min(100.0, ($typesUsed / 3) * 100);

        // First-pitch strikes (15%)
        $firstPitch  = $data->where('sort', 1);
        $fpTotal     = $firstPitch->count();
        $fpStrikes   = $firstPitch->where('zone', 'S')->count();
        $fpScore     = $fpTotal > 0 ? ($fpStrikes / $fpTotal) * 100 : $strikeRate;

        $bps = round(
            $strikeRate * 0.35
            + $veloScore  * 0.30
            + $mixScore   * 0.20
            + $fpScore    * 0.15,
            1
        );

        return [
            'bps'         => $bps,
            'total'       => $total,
            'strikeRate'  => round($strikeRate, 1),
            'veloScore'   => round($veloScore, 1),
            'mixScore'    => round($mixScore, 1),
            'fpScore'     => round($fpScore, 1),
            'avgVelo'     => round($avgVelo, 1),
            'topVelo'     => round($topVelo, 1),
            'typesUsed'   => $typesUsed,
            'eliteThrowers' => $eliteThrowers,
            'pitchersScored' => $pitchersScored,
            'eliteThrowerRate' => $pitchersScored > 0 ? round(($eliteThrowers / $pitchersScored) * 100, 1) : 0.0,
            'avgEliteVeloThreshold' => $avgEliteThreshold,
            'avgPitcherAge' => $avgAge,
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

    /**
     * Velocity chart (mph) provided by user: age 8-17 with Average→Select tiers.
     */
    private function velocityChartThresholdsForAge(?int $age): array
    {
        $chart = [
            8  => ['average' => 40, 'good' => 43, 'competitive' => 47, 'elite' => 50, 'ultra' => 54, 'select' => 57],
            9  => ['average' => 43, 'good' => 47, 'competitive' => 51, 'elite' => 55, 'ultra' => 59, 'select' => 63],
            10 => ['average' => 46, 'good' => 50, 'competitive' => 54, 'elite' => 58, 'ultra' => 62, 'select' => 66],
            11 => ['average' => 48, 'good' => 52, 'competitive' => 56, 'elite' => 60, 'ultra' => 64, 'select' => 68],
            12 => ['average' => 50, 'good' => 55, 'competitive' => 60, 'elite' => 65, 'ultra' => 70, 'select' => 74],
            13 => ['average' => 54, 'good' => 59, 'competitive' => 64, 'elite' => 69, 'ultra' => 74, 'select' => 79],
            14 => ['average' => 60, 'good' => 66, 'competitive' => 72, 'elite' => 78, 'ultra' => 84, 'select' => 90],
            15 => ['average' => 66, 'good' => 73, 'competitive' => 78, 'elite' => 84, 'ultra' => 90, 'select' => 93],
            16 => ['average' => 72, 'good' => 78, 'competitive' => 84, 'elite' => 88, 'ultra' => 92, 'select' => 94],
            17 => ['average' => 78, 'good' => 82, 'competitive' => 86, 'elite' => 90, 'ultra' => 93, 'select' => 96],
        ];

        if ($age === null) {
            return $chart[14];
        }

        if ($age <= 8) {
            return $chart[8];
        }

        if ($age >= 17) {
            return $chart[17];
        }

        return $chart[$age] ?? $chart[14];
    }

    private function ageAdjustedVelocityScore(float $avgVelo, array $t): float
    {
        $avg = (float) ($t['average'] ?? 50);
        $good = (float) ($t['good'] ?? 55);
        $comp = (float) ($t['competitive'] ?? 60);
        $elite = (float) ($t['elite'] ?? 65);
        $ultra = (float) ($t['ultra'] ?? 70);
        $select = (float) ($t['select'] ?? 75);

        if ($avgVelo <= $avg) {
            return max(0.0, min(40.0, ($avgVelo / max(1.0, $avg)) * 40.0));
        }

        if ($avgVelo <= $good) {
            return 40.0 + (($avgVelo - $avg) / max(1.0, ($good - $avg))) * 15.0;
        }

        if ($avgVelo <= $comp) {
            return 55.0 + (($avgVelo - $good) / max(1.0, ($comp - $good))) * 15.0;
        }

        if ($avgVelo <= $elite) {
            return 70.0 + (($avgVelo - $comp) / max(1.0, ($elite - $comp))) * 15.0;
        }

        if ($avgVelo <= $ultra) {
            return 85.0 + (($avgVelo - $elite) / max(1.0, ($ultra - $elite))) * 10.0;
        }

        if ($avgVelo <= $select) {
            return 95.0 + (($avgVelo - $ultra) / max(1.0, ($select - $ultra))) * 5.0;
        }

        return 100.0;
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
