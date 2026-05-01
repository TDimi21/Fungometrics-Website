<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\SidesFieldPosition;
use App\Utils\Helper;
use Illuminate\Support\Collection;

final class LiveABStatisticsService
{
    public function hitterResults($data, $basic = true): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->hitterResultsTeam($data, $basic);
        $result['players'] = $this->hitterResultsPlayers($data, $basic);
        return $result;
    }

    private function hitterResultsTeam($data, $basic)
    {
        $results = [];
        $batter = collect($data['batters']);
        $pitchers = collect($data['pitchers']);
        $totals = $batter->count();
        $singles = $batter->where('livePractice.bases', '=', 1)->count();
        $singlesh = $batter->where('livePractice.bases', '=', 1)->whereNotIn(
            'trajectory',
            [BattingTrajectory::TAKE->value,
                BattingTrajectory::HIT_BY_PITCH->value]
        )->count();
        $doubles = $batter->where('livePractice.bases', '=', 2)->count();
        $triples = $batter->where('livePractice.bases', '=', 3)->count();
        $homeRuns = $batter->where('livePractice.bases', '=', 5)->count();

        $strikeOuts = $batter->where('livePractice.bases', '=', 7)
            ->where('livePractice.turn_strike', '=', 3)
            ->where('livePractice.is_strike', '=', true)->count();
        $baseByBalls = $batter->where('livePractice.turn_is_over', '=', true)
            ->where('livePractice.turn_ball', '=', '4')
            ->where('livePractice.is_ball', '=', true)->count();
        $hitByPitchers = $batter->where('livePractice.bases', '=', 6)->count();
        $hits = $singlesh + $doubles + $triples + $homeRuns;
        $platesAppearances = $batter
            ->where('livePractice.count_b_s', '=', '0-0')
            ->count();
        $atBat = $platesAppearances - ($baseByBalls + $hitByPitchers);
        $battingAverage = round(Helper::caseDivide($hits, $atBat), 3);
        $results['H'] = $hits;
        $results['AB'] = $atBat;
        $results['PA'] = $platesAppearances;
        if ($basic) {
            $totalBases = $singles + $doubles * 2 + $triples * 3 + $homeRuns * 4;
            $onBasePercents = round(Helper::caseDivide(($singles + $doubles + $triples + $homeRuns + $baseByBalls +
                $hitByPitchers), $atBat), 3);
            $sluggingPercent = round(Helper::caseDivide($totalBases, $atBat), 3);
            $onBasePlusSlugging = $onBasePercents + $sluggingPercent;
            $results['B1'] = $singles;
            $results['B2'] = $doubles;
            $results['B3'] = $triples;
            $results['HR'] = $homeRuns;
            $results['k'] = $strikeOuts;
            $results['BB'] = $baseByBalls;
            $results['HBP'] = $hitByPitchers;
            $results['TOTAL-BASES'] = $totalBases;
            $results['BA'] = $battingAverage;
            $results['OBP'] = $onBasePercents;
            $results['SLG'] = $sluggingPercent;
            $results['OPS'] = round($onBasePlusSlugging, 3);
        }

        //plate appearance walks PA/BB
        if ( ! $basic) {
            $pabb = round(Helper::caseDivide($platesAppearances, $baseByBalls), 3);
            $bbk = round(Helper::caseDivide($baseByBalls, $strikeOuts), 3);
            $contactPercent = round(Helper::caseDivide(($atBat - $strikeOuts), $atBat), 2);
            $extraBaseHit = $doubles + $triples + $homeRuns;
            $count2s1b = $batter->where('livePractice.turn_strike', '=', 2)
                ->where('livePractice.turn_ball', '=', 1)
                ->where('livePractice.turn_is_over', '=', true)
                ->count();
            $count2s2b = $batter->where('livePractice.turn_strike', '=', 2)
                ->where('livePractice.turn_ball', '=', 2)
                ->where('livePractice.turn_is_over', '=', true)
                ->count();
            $count2s3b = $batter->where('livePractice.turn_strike', '=', 2)
                ->where('livePractice.turn_ball', '=', 3)
                ->where('livePractice.turn_is_over', '=', true)
                ->count();
            $count2sHr = $batter->where('livePractice.turn_strike', '=', 2)
                ->where('livePractice.bases', '=', 4)
                ->where('livePractice.turn_is_over', '=', true)
                ->count();
            $hitWith2s = ($count2s1b + $count2s2b + $count2s3b + $count2sHr);
            $count2sOutE = $batter->where('livePractice.turn_strike', '=', 2)
                ->where('livePractice.bases', '=', 8)
                ->where('livePractice.turn_is_over', '=', true)
                ->count();
            $atBatWith2s = $hitWith2s + $count2sOutE + $strikeOuts;
            $pspa = round(Helper::caseDivide($totals, $platesAppearances), 3);
            $twoSAverage = round(Helper::caseDivide($hitWith2s, $atBatWith2s), 2);
            $sixPlus = $batter->where('livePractice.turn', '=', 6)->count();

            $sixPlusPercent = round(Helper::caseDivide(
                $sixPlus,
                $platesAppearances
            ) * 100, 2);
            $qWeak = $batter->where('quality_of_contact', '=', ContactQuality::WEAK->value)
                ->count();
            $qHard = $batter->where('quality_of_contact', '=', ContactQuality::HARD->value)
                ->count();
            $qAverage = $batter->where('quality_of_contact', '=', ContactQuality::AVERAGE->value)
                ->count();
            $hardHits = round(Helper::caseDivide($qHard, ($qHard + $qAverage + $qWeak)), 3);

            $typeTrajectoryLD = $pitchers
                ->where('practice_id', '=', $batter[0]->practice_id)
                ->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                ->count();
            $typeTrajectoryGB = $pitchers
                ->where('practice_id', '=', $batter[0]->practice_id)
                ->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                ->count();
            $typeTrajectoryFLY = $pitchers->where('practice_id', '=', $batter[0]->practice_id)
                ->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                ->count();

            $sumTrajectories = ($typeTrajectoryFLY + $typeTrajectoryGB + $typeTrajectoryLD);

            $percentLD = round(Helper::caseDivide($typeTrajectoryLD, $sumTrajectories) * 100, 2);
            $percentGB = round(Helper::caseDivide($typeTrajectoryGB, $sumTrajectories) * 100, 2);
            $percentFLY = round(Helper::caseDivide($typeTrajectoryFLY, $sumTrajectories) * 100, 2);
            $babit = round(Helper::caseDivide(($singles + $doubles + $triples), ($atBat - $strikeOuts -
                $homeRuns)), 3);

            $results['PA_BB'] = $pabb;
            $results['BB_K'] = $bbk;
            $results['C_PERCENTS'] = $contactPercent;
            $results['XBH'] = $extraBaseHit;
            $results['PS_PA'] = $pspa;
            $results['2SAVG'] = $twoSAverage;
            $results['6+'] = $sixPlus;
            $results['6+ %'] = $sixPlusPercent;
            $results['HARDHIT'] = $hardHits;
            $results['LDP'] = $percentLD;
            $results['GBP'] = $percentGB;
            $results['FLYP'] = $percentFLY;
            $results['BABIP'] = $babit;
        }

        return $results;


    }

    private function hitterResultsPlayers($data, $basic)
    {


        $batter = $data['batters']->groupBy('batter_id');
        $pitchers = $data['pitchers'];
        $results = [];
        foreach ($batter as $index => $item) {
            $totals = $item->count();
            $singles = $item->where('livePractice.bases', '=', 1)->count();
            $doubles = $item->where('livePractice.bases', '=', 2)->count();
            $triples = $item->where('livePractice.bases', '=', 3)->count();
            $homeRuns = $item->where('livePractice.bases', '=', 5)->count();
            $strikeOuts = $item->where('livePractice.bases', '=', 7)
                ->where('livePractice.turn_strike', '=', 3)
                ->where('livePractice.is_strike', '=', true)->count();
            $baseByBalls = $item->where('livePractice.turn_is_over', '=', true)
                ->where('livePractice.turn_ball', '=', '4')
                ->where('livePractice.is_ball', '=', true)->count();
            $hitByPitchers = $item->where('livePractice.bases', '=', 6)->count();
            $hits = $singles + $doubles + $triples + $homeRuns;
            $platesAppearances = $item->where('livePractice.count_b_s', '=', '0-0')
                ->count();
            $atBat = $platesAppearances - ($baseByBalls + $hitByPitchers);
            $battingAverage = round(Helper::caseDivide($hits, $atBat), 3);
            $results[$index]['H'] = $hits;
            $results[$index]['AB'] = $atBat;
            $results[$index]['PA'] = $platesAppearances;
            if ($basic) {
                $totalBases = $singles + $doubles * 2 + $triples * 3 + $homeRuns * 4;
                $onBasePercents = round(Helper::caseDivide(
                    ($singles + $doubles + $triples + $homeRuns + $baseByBalls +
                        $hitByPitchers),
                    $atBat
                ), 2);
                $sluggingPercent = round(Helper::caseDivide($totalBases, $atBat), 3);
                $onBasePlusSlugging = $onBasePercents + $sluggingPercent;
                $results[$index]['B1'] = $singles;
                $results[$index]['B2'] = $doubles;
                $results[$index]['B3'] = $triples;
                $results[$index]['HR'] = $homeRuns;
                $results[$index]['k'] = $strikeOuts;
                $results[$index]['BB'] = $baseByBalls;
                $results[$index]['HBP'] = $hitByPitchers;
                $results[$index]['TOTAL-BASES'] = $totalBases;
                $results[$index]['BA'] = $battingAverage;
                $results[$index]['OBP'] = $onBasePercents;
                $results[$index]['SLG'] = $sluggingPercent;
                $results[$index]['OPS'] = round($onBasePlusSlugging, 3);
            }

            //plate appearance walks PA/BB
            if ( ! $basic) {
                $pabb = round(Helper::caseDivide($platesAppearances, $baseByBalls), 3);
                $bbk = round(Helper::caseDivide($baseByBalls, $strikeOuts), 3);
                $contactPercent = round(Helper::caseDivide(($atBat - $strikeOuts), $atBat), 2);
                $extraBaseHit = $doubles + $triples + $homeRuns;
                $count2s1b = $item->where('livePractice.turn_strike', '=', 2)
                    ->where('livePractice.turn_ball', '=', 1)
                    ->where('livePractice.turn_is_over', '=', true)
                    ->count();
                $count2s2b = $item->where('livePractice.turn_strike', '=', 2)
                    ->where('livePractice.turn_ball', '=', 2)
                    ->where('livePractice.turn_is_over', '=', true)
                    ->count();
                $count2s3b = $item->where('livePractice.turn_strike', '=', 2)
                    ->where('livePractice.turn_ball', '=', 3)
                    ->where('livePractice.turn_is_over', '=', true)
                    ->count();
                $count2sHr = $item->where('livePractice.turn_strike', '=', 2)
                    ->where('livePractice.bases', '=', 4)
                    ->where('livePractice.turn_is_over', '=', true)
                    ->count();
                $hitWith2s = ($count2s1b + $count2s2b + $count2s3b + $count2sHr);
                $count2sOutE = $item->where('livePractice.turn_strike', '=', 2)
                    ->where('livePractice.bases', '=', 8)
                    ->where('livePractice.turn_is_over', '=', true)
                    ->count();
                $atBatWith2s = $hitWith2s + $count2sOutE + $strikeOuts;
                $pspa = round(Helper::caseDivide($totals, $platesAppearances), 3);
                $twoSAverage = round(Helper::caseDivide($hitWith2s, $atBatWith2s), 3);
                $sixPlus = $item->where('livePractice.turn', '=', 6)->count();

                $sixPlusPercent = round(Helper::caseDivide(
                    $sixPlus,
                    $platesAppearances
                ) * 100, 2);
                $qWeak = $item->where('quality_of_contact', '=', ContactQuality::WEAK->value)
                    ->count();
                $qHard = $item->where('quality_of_contact', '=', ContactQuality::HARD->value)
                    ->count();
                $qAverage = $item->where('quality_of_contact', '=', ContactQuality::AVERAGE->value)
                    ->count();
                $hardHits = round(Helper::caseDivide($qHard, ($qHard + $qAverage + $qWeak)), 3);
                $pitchId = $item->pluck('livePractice.pitching_result_id')->all();

                $typeTrajectoryLD = $pitchers
                    ->whereIn('id', $pitchId)
                    ->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)
                    ->count();

                $typeTrajectoryGB = $pitchers
                    ->whereIn('id', $pitchId)
                    ->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)
                    ->count();
                $typeTrajectoryFLY = $pitchers
                    ->whereIn('id', $pitchId)
                    ->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)
                    ->count();

                $sumTrajectories = ($typeTrajectoryFLY + $typeTrajectoryGB + $typeTrajectoryLD);

                $percentLD = round(Helper::caseDivide($typeTrajectoryLD, $sumTrajectories) * 100, 2);
                $percentGB = round(Helper::caseDivide($typeTrajectoryGB, $sumTrajectories) * 100, 2);
                $percentFLY = round(Helper::caseDivide($typeTrajectoryFLY, $sumTrajectories) * 100, 2);
                $babit = round(Helper::caseDivide(($singles + $doubles + $triples), ($atBat - $strikeOuts -
                    $homeRuns)), 2);

                $results[$index]['PA_BB'] = $pabb;
                $results[$index]['BB_K'] = $bbk;
                $results[$index]['C_PERCENTS'] = $contactPercent;
                $results[$index]['XBH'] = $extraBaseHit;
                $results[$index]['PS_PA'] = $pspa;
                $results[$index]['2SAVG'] = $twoSAverage;
                $results[$index]['6+'] = $sixPlus;
                $results[$index]['6+ %'] = $sixPlusPercent;
                $results[$index]['HARDHIT'] = $hardHits;
                $results[$index]['LDP'] = $percentLD;
                $results[$index]['GBP'] = $percentGB;
                $results[$index]['FLYP'] = $percentFLY;
                $results[$index]['BABIP'] = $babit;

            }
        }
        return $results;
    }

    public function pitcherResults($data, $basic = true): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->pitcherResultsTeam($data, $basic);
        $result['players'] = $this->pitcherResultsPlayers($data, $basic);
        return $result;
    }

    private function pitcherResultsTeam($data, mixed $basic)
    {
        $results = [];
        $pitchers = $data['pitchers'];
        $batters = $data['batters'];
        $totals = $results['TOTAL_PITCHES'] = $pitchers->count();
        $battersFaced = $pitchers->where('livePractice.count_b_s', '=', '0-0')->count();
        $balls = $pitchers->where('zone', '=', 'B')->count();
        $strike = $pitchers->where('zone', '=', 'S')->count();
        $strikePercents = round(Helper::caseDivide($strike, $totals) * 100, 2);
        $fastBalls = $pitchers->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count();
        $change = $pitchers->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count();
        $curveBalls = $pitchers->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count();
        $sliders = $pitchers->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count();
        $others = $pitchers->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count();
        $fastBallPercents = round(Helper::caseDivide($fastBalls, $totals) * 100, 2);
        $changePercents = round(Helper::caseDivide($change, $totals) * 100, 2);
        $curveBallsPercents = round(Helper::caseDivide($curveBalls, $totals) * 100, 3);
        $sliderPercents = round(Helper::caseDivide($sliders, $totals) * 100, 2);
        $otherPercents = round(Helper::caseDivide($others, $totals) * 100, 2);

        if ($basic) {
            $results['BALL'] = $balls;
            $results['BF'] = $battersFaced;
            $results['STRIKES'] = $strike;
            $results['STRIKES %'] = $strikePercents;
            $results['FB'] = $fastBalls;
            $results['FB %'] = $fastBallPercents;
            $results['CH'] = $change;
            $results['CH %'] = $changePercents;
            $results['CB'] = $curveBalls;
            $results['CB %'] = $curveBallsPercents;
            $results['SL'] = $sliders;
            $results['SL %'] = $sliderPercents;
            $results['OTHER'] = $others;
            $results['OTHER %'] = $otherPercents;
        }

        if ( ! $basic) {
            $singleh = $pitchers->where('livePractice.bases', '=', 1)->whereNotIn('trajectory', [BattingTrajectory::TAKE->value,
                BattingTrajectory::HIT_BY_PITCH->value])->count();
            $single = $pitchers->where('livePractice.bases', '=', 1)->count();
            $doubles = $pitchers->where('livePractice.bases', '=', 2)->count();
            $triples = $pitchers->where('livePractice.bases', '=', 3)->count();
            $homeRuns = $pitchers->where('livePractice.bases', '=', 4)->count();
            $totalBases = ($single + $doubles * 2 + $triples * 3 + $homeRuns * 4);
            $countBases = ($single + $doubles  + $triples  + $homeRuns);
            $hits = $singleh + $doubles + $triples + $homeRuns;
            $sw = $pitchers->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                ->count();
            $strikeOuts = $pitchers->where('livePractice.bases', '=', 7)
                ->where('livePractice.turn_strike', '=', 3)
                ->where('livePractice.is_strike', '=', true)->count();

            //P/BF = Total pitches / batters faced
            $pbf = round(Helper::caseDivide($totals, $battersFaced), 3);

            // <=3% percentage of plate aperance that are  3 pitches or less
            $pitches3less = $this->getPitches3less($pitchers);

            $less3Percents = round(Helper::caseDivide($pitches3less, $battersFaced) * 100, 2);

            $fps = $pitchers->where('livePractice.count_b_s', '=', '0-0')
                ->where('livePractice.is_strike', '=', true)
                ->count();

            $baseByBalls = $pitchers->where('livePractice.turn_is_over', '=', true)
                ->where('livePractice.bases', '=', 4)->count();
            $hitByPitch = $pitchers->where('livePractice.turn_is_over', '=', true)
                ->where('livePractice.bases', '=', 6)->count();
            $totalsWeak = $batters->where('practice_id', '=', $pitchers[0]->practice_id)
                ->where('quality_of_contact', '=', ContactQuality::WEAK->value)->count();
            $totalsLD = $pitchers->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)->count();
            $totalsGB = $pitchers->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)->count();
            $totalsFly = $pitchers->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)->count();
            $totalsTrajectories = $totalsFly + $totalsLD + $totalsGB;
            $atBat = $battersFaced - ($baseByBalls + $hitByPitch);
            // FPS% = count of strike and pitch_count =0-0 / BF
            $fpsPercents = round(Helper::caseDivide($fps, $battersFaced) * 100, 2);
            //FPSo % = ((total bases = k o bases = outE) / (count pitch 0-0 is strike))*100
            $kAndOuts = $pitchers->whereIn('livePractice.bases', [7,8])
                ->where('livePractice.turn_is_over', '=', true)->count();
            $fpsPercentsO = round(Helper::caseDivide($kAndOuts, $fps) * 100, 2);
            //FPSw% = (count (total bases (BB,HBP)) / (count pitch 0-0 is strike)) *100
            $fpsPercentsW = round(Helper::caseDivide(($hitByPitch + $baseByBalls), $fps) * 100, 2);
            //FPSh% = (count (total bases (1b,2b,3b,4b)) / (count pitch 0-0 is strike)) *100
            $fpsPercentsH = round(Helper::caseDivide($countBases, $fps) * 100, 2);
            //SM% = (count(swin miss) / Total pitches)*100
            $swPercents = round(Helper::caseDivide($sw, $totals) * 100, 2);
            //K/BF % = (total bases k / BF) *100
            $kbfPercents = round(Helper::caseDivide($strikeOuts, $battersFaced), 2);
            // Weak % = (total Weak / BF) *100
            $weakPercents = round(Helper::caseDivide($totalsWeak, $battersFaced), 2);
            // LD % = (total LD / (LD+GB-FLY))) *100
            $ldPercents = round(Helper::caseDivide($totalsLD, $totalsTrajectories) * 100, 2);
            // GB % = (total GB / (LD+GB-FLY)) *100
            $gbPercents = round(Helper::caseDivide($totalsGB, $totalsTrajectories) * 100, 2);

            // Fly % = (total Fly / (LD+GB-FLY)) *100
            $flyPercents = round(Helper::caseDivide($totalsFly, $totalsTrajectories) * 100, 2);
            //babip (b1,b2,b3)/(AB-k-HR)
            $babip = round(Helper::caseDivide(($single + $doubles + $triples), ($battersFaced - $strikeOuts - $homeRuns)), 3);


            $results['Hits'] = $hits;
            $results['P/BF'] = $pbf;
            $results['<=3 %'] = $less3Percents;
            $results['FPS%'] = $fpsPercents;
            $results['FPSo%'] = $fpsPercentsO;
            $results['FPSw%'] = $fpsPercentsW;
            $results['FPSh%'] = $fpsPercentsH;
            $results['SM%'] = $swPercents;
            $results['K/BF%'] = $kbfPercents;
            $results['Weak%'] = $weakPercents;
            $results['BABIP'] = $babip;
            $results['LD%'] = $ldPercents;
            $results['GB%'] = $gbPercents;
            $results['Fly%'] = $flyPercents;

        }
        return $results;
    }

    private function pitcherResultsPlayers($data, mixed $basic)
    {
        $pitchers = $data['pitchers']->groupBy('pitcher_id');
        $batters = $data['batters'];
        $results = [];
        foreach ($pitchers as $index => $item) {
            $totals = $results[$index]['TOTAL_PITCHES'] = $item->count();
            $battersFaced = $item->where('livePractice.count_b_s', '=', '0-0')->count();
            $balls = $item->where('zone', '=', 'B')->count();
            $strike = $item->where('zone', '=', 'S')->count();
            $strikePercents = round(Helper::caseDivide($strike, $totals) * 100, 2);
            $fastBalls = $item->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count();
            $change = $item->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count();
            $curveBalls = $item->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count();
            $sliders = $item->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count();
            $others = $item->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count();
            $fastBallPercents = round(Helper::caseDivide($fastBalls, $totals) * 100, 2);
            $changePercents = round(Helper::caseDivide($change, $totals) * 100, 2);
            $curveBallsPercents = round(Helper::caseDivide($curveBalls, $totals) * 100, 2);
            $sliderPercents = round(Helper::caseDivide($sliders, $totals) * 100, 2);
            $otherPercents = round(Helper::caseDivide($others, $totals) * 100, 2);

            if ($basic) {
                $results[$index]['BALL'] = $balls;
                $results[$index]['BF'] = $battersFaced;
                $results[$index]['STRIKES'] = $strike;
                $results[$index]['STRIKES %'] = $strikePercents;
                $results[$index]['FB'] = $fastBalls;
                $results[$index]['FB %'] = $fastBallPercents;
                $results[$index]['CH'] = $change;
                $results[$index]['CH %'] = $changePercents;
                $results[$index]['CB'] = $curveBalls;
                $results[$index]['CB %'] = $curveBallsPercents;
                $results[$index]['SL'] = $sliders;
                $results[$index]['SL %'] = $sliderPercents;
                $results[$index]['OTHER'] = $others;
                $results[$index]['OTHER %'] = $otherPercents;

            }

            if ( ! $basic) {
                $singleh = $item->where('livePractice.bases', '=', 1)->whereNotIn('trajectory', [BattingTrajectory::TAKE->value,
                    BattingTrajectory::HIT_BY_PITCH->value])->count();
                $single = $item->where('livePractice.bases', '=', 1)->count();
                $doubles = $item->where('livePractice.bases', '=', 2)->count();
                $triples = $item->where('livePractice.bases', '=', 3)->count();
                $homeRuns = $item->where('livePractice.bases', '=', 4)->count();
                $totalBases = ($single + $doubles * 2 + $triples * 3 + $homeRuns * 4);
                $countBases = ($single + $doubles  + $triples  + $homeRuns);
                $hits = $singleh + $doubles + $triples + $homeRuns;
                $sw = $item->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)
                    ->count();
                $strikeOuts = $item->where('livePractice.bases', '=', 7)
                    ->where('livePractice.turn_strike', '=', 3)
                    ->where('livePractice.is_strike', '=', true)->count();

                //P/BF = Total pitches / batters faced
                $pbf = round(Helper::caseDivide($totals, $battersFaced), 3);

                // <=3% percentage of plate aperance that are  3 pitches or less
                $pitches3less = $this->getPitches3less($item);

                $less3Percents = round(Helper::caseDivide($pitches3less, $battersFaced) * 100, 2);

                $fps = $item->where('livePractice.count_b_s', '=', '0-0')
                    ->where('livePractice.is_strike', '=', true)
                    ->count();

                $baseByBalls = $item->where('livePractice.turn_is_over', '=', true)
                    ->where('livePractice.bases', '=', 4)->count();
                $hitByPitch = $item->where('livePractice.turn_is_over', '=', true)
                    ->where('livePractice.bases', '=', 6)->count();
                $batsId =  $item->pluck('livePractice.batting_result_id')->all();

                $totalsWeak = $batters->whereIn('id', $batsId)
                    ->where('quality_of_contact', '=', ContactQuality::WEAK->value)
                    ->count();
                $totalsLD = $item->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)->count();
                $totalsGB = $item->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)->count();
                $totalsFly = $item->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)->count();
                $totalsTrajectories = $totalsFly + $totalsLD + $totalsGB;
                $atBat = $battersFaced - ($baseByBalls + $hitByPitch);
                // FPS% = count of strike and pitch_count =0-0 / BF
                $fpsPercents = round(Helper::caseDivide($fps, $battersFaced) * 100, 2);
                //FPSo % = ((total bases = k o bases = outE) / (count pitch 0-0 is strike))*100
                $kAndOuts = $item->whereIn('livePractice.bases', [7,8])
                    ->where('livePractice.turn_is_over', '=', true)->count();
                $fpsPercentsO = round(Helper::caseDivide($kAndOuts, $fps) * 100, 2);
                //FPSw% = (count (total bases (BB,HBP)) / (count pitch 0-0 is strike)) *100
                $fpsPercentsW = round(Helper::caseDivide(($hitByPitch + $baseByBalls), $fps) * 100, 2);
                //FPSh% = (count (total bases (1b,2b,3b,4b)) / (count pitch 0-0 is strike)) *100
                $fpsPercentsH = round(Helper::caseDivide($countBases, $fps) * 100, 2);
                //SM% = (count(swin miss) / Total pitches)*100
                $swPercents = round(Helper::caseDivide($sw, $totals) * 100, 2);
                //K/BF % = (total bases k / BF) *100
                $kbfPercents = round(Helper::caseDivide($strikeOuts, $battersFaced), 2);
                // Weak % = (total Weak / BF) *100
                $weakPercents = round(Helper::caseDivide($totalsWeak, $battersFaced), 2);
                // LD % = (total LD / (LD+GB-FLY))) *100
                $ldPercents = round(Helper::caseDivide($totalsLD, $totalsTrajectories) * 100, 2);
                // GB % = (total GB / (LD+GB-FLY)) *100
                $gbPercents = round(Helper::caseDivide($totalsGB, $totalsTrajectories) * 100, 2);

                // Fly % = (total Fly / (LD+GB-FLY)) *100
                $flyPercents = round(Helper::caseDivide($totalsFly, $totalsTrajectories) * 100, 2);
                //babip (b1,b2,b3)/(AB-k-HR)
                $babip = round(Helper::caseDivide(($single + $doubles + $triples), ($battersFaced - $strikeOuts - $homeRuns)), 3);


                $results[$index]['Hits'] = $hits;
                $results[$index]['P/BF'] = $pbf;
                $results[$index]['<=3 %'] = $less3Percents;
                $results[$index]['FPS%'] = $fpsPercents;
                $results[$index]['FPSo%'] = $fpsPercentsO;
                $results[$index]['FPSw%'] = $fpsPercentsW;
                $results[$index]['FPSh%'] = $fpsPercentsH;
                $results[$index]['SM%'] = $swPercents;
                $results[$index]['K/BF%'] = $kbfPercents;
                $results[$index]['Weak%'] = $weakPercents;
                $results[$index]['BABIP'] = $babip;
                $results[$index]['LD%'] = $ldPercents;
                $results[$index]['GB%'] = $gbPercents;
                $results[$index]['Fly%'] = $flyPercents;

            }
        }
        return $results;
    }

    public function hitterPitchBreakdown($data): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->hitterPitchBreakdownTeam($data);
        $result['players'] = $this->hitterPitchBreakdownPlayers($data);
        return $result;
    }

    private function hitterPitchBreakdownTeam($data)
    {

        $batters = $data['batters'];
        $marks = collect()->merge(($batters->map(function ($item) {
            return [
                'pitch_mark' => $item->pitch_mark,
                'type_of_hit' => $item->type_of_hit,
                'field_direction' => $item->field_direction,
            ];

        })->filter()))->toArray();
        return [
            'TOTAL-SWINGS' => $batters->count(),
            'TOTAL-LEFT' => $batters->where('field_direction', '=', SidesFieldPosition::LEFT->value)->count(),
            'TOTAL-RIGHT' => $batters->where('field_direction', '=', SidesFieldPosition::RIGHT->value)->count(),
            'TOTAL-MIDDLE' => $batters->where('field_direction', '=', SidesFieldPosition::CENTER->value)->count(),
            'TOTAL-NIP' => $batters->where('field_direction', '=', SidesFieldPosition::NONE->value)->count(),
            'TOTAL-PITCH-LOCATION' => $marks,
        ];


    }

    private function hitterPitchBreakdownPlayers($data)
    {
        $batters = $data['batters']->groupBy('batter_id');
        $results = [];
        foreach ($batters as $index => $batter) {
            $marks = collect()->merge(($batter->map(function ($item) {
                return [
                    'pitch_mark' => $item->pitch_mark,
                    'type_of_hit' => $item->type_of_hit,
                    'field_direction' => $item->field_direction,
                ];
            })->filter()))->toArray();

            $results[$index]['TOTAL-SWINGS'] = $batter->count();
            $results[$index]['TOTAL-LEFT'] = $batter->where(
                'field_direction',
                '=',
                SidesFieldPosition::LEFT->value
            )->count();
            $results[$index]['TOTAL-RIGHT'] = $batter->where(
                'field_direction',
                '=',
                SidesFieldPosition::RIGHT->value
            )->count();
            $results[$index]['TOTAL-MIDDLE'] = $batter->where(
                'field_direction',
                '=',
                SidesFieldPosition::CENTER->value
            )->count();
            $results[$index]['TOTAL-NIP'] = $batter->where(
                'field_direction',
                '=',
                SidesFieldPosition::NONE->value
            )->count();
            $results[$index]['TOTAL-PITCH-LOCATION'] = $marks;

        }

        return $results;
    }

    public function hitterContact($data): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->hitterContactTeam($data);
        $result['players'] = $this->hitterContactPlayers($data);
        return $result;
    }

    private function hitterContactTeam($data)
    {
        $batters = $data['batters'];
        $marks = collect()->merge(($batters->map(function ($item) {
            return [
                'field_mark' => $item->field_mark,
                'quality_of_contact' => $item->quality_of_contact,
                'type_of_hit' => $item->type_of_hit,
            ];
        })->filter()))->toArray();
        return [
            'TOTAL-SWINGS' => $batters->count(),
            'TOTAL-FOUL' => $batters->where('quality_of_contact', '=', ContactQuality::MISS_FOUL->value)
                ->count(),
            'TOTAL-SM' => $batters->where('quality_of_contact', '=', ContactQuality::NONE->value)->count(),
            'TOTAL-WEAK' => $batters->where('quality_of_contact', '=', ContactQuality::WEAK->value)
                ->count(),
            'TOTAL-AVERAGE' => $batters->where('quality_of_contact', '=', ContactQuality::AVERAGE->value)
                ->count(),
            'TOTAL-HARD' => $batters->where('quality_of_contact', '=', ContactQuality::HARD->value)
                ->count(),
            'TOTAL-PITCH-LOCATION' => $marks,
        ];

    }

    private function hitterContactPlayers($data)
    {
        $batters = $data['batters']->groupBy('batter_id');
        $results = [];
        foreach ($batters as $index => $batter) {
            $marks = collect()->merge(($batter->map(function ($item) {
                return [
                    'field_mark' => $item->field_mark,
                    'quality_of_contact' => $item->quality_of_contact,
                    'type_of_hit' => $item->type_of_hit,
                ];
            })->filter()))->toArray();

            $results[$index]['TOTAL-SWINGS'] = $batter->count();
            $results[$index]['TOTAL-FOUL'] = $batter->where(
                'batting.quality_of_contact',
                '=',
                ContactQuality::MISS_FOUL->value
            )
                ->count();
            $results[$index]['TOTAL-SM'] = $batter->where(
                'quality_of_contact',
                '=',
                ContactQuality::NONE->value
            )->count();
            $results[$index]['TOTAL-WEAK'] = $batter->where(
                'quality_of_contact',
                '=',
                ContactQuality::WEAK->value
            )
                ->count();
            $results[$index]['TOTAL-AVERAGE'] = $batter->where(
                'quality_of_contact',
                '=',
                ContactQuality::AVERAGE->value
            )
                ->count();
            $results[$index]['TOTAL-HARD'] = $batter->where(
                'quality_of_contact',
                '=',
                ContactQuality::HARD->value
            )
                ->count();
            $results[$index]['TOTAL-PITCH-LOCATION'] = $marks;

        }

        return $results;
    }

    public function hitterTrajectory($data): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->hitterTrajectoryTeam($data);
        $result['players'] = $this->hitterTrajectoryPlayers($data);
        return $result;
    }

    private function hitterTrajectoryTeam($data)
    {
        $batters = $data['batters'];
        $marks = collect()->merge(($batters->map(function ($item) {
            return [
                'field_mark' => $item->field_mark,
                'type_of_hit' => $item->type_of_hit,
                'field_direction' => $item->field_direction
            ];
        })->filter()))->toArray();
        return [
            'TOTAL-SWINGS' => $batters->count(),
            'TOTAL-FB' => $batters->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)
                ->count(),
            'TOTAL-SM' => $batters->where('type_of_hit', '=', BattingTrajectory::SWING_MISS->value)->count(),
            'TOTAL-LD' => $batters->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)
                ->count(),
            'TOTAL-GB' => $batters->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)
                ->count(),
            'TOTAL-FOUL' => $batters->where('type_of_hit', '=', BattingTrajectory::FOUL->value)
                ->count(),
            'TOTAL-HBP' => $batters->where('type_of_hit', '=', BattingTrajectory::HIT_BY_PITCH->value)
                ->count(),
            'TOTAL-PF' => $batters->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)
                ->count(),
            'SPRAY-PITCH-LOCATION' => $marks,
        ];
    }

    private function hitterTrajectoryPlayers($data)
    {
        $batters = $data['batters']->groupBy('batter_id');
        $results = [];
        foreach ($batters as $index => $batter) {
            $marks = collect()->merge(($batter->map(function ($item) {
                return [
                    'field_mark' => $item->field_mark,
                    'type_of_hit' => $item->type_of_hit,
                    'field_direction' => $item->field_direction
                ];
            })->filter()))->toArray();
            $results[$index]['TOTAL-SWINGS'] = $batter->count();
            $results[$index]['TOTAL-FB'] = $batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::FLY_BALL->value
            )
                ->count();
            $results[$index]['TOTAL-SM'] = $batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::SWING_MISS->value
            )->count();
            $results[$index]['TOTAL-LD'] = $batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::LINE_DRIVE->value
            )
                ->count();
            $results[$index]['TOTAL-GB'] = $batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::GROUND_BALL->value
            )
                ->count();
            $results[$index]['TOTAL-FOUL'] = $batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::FOUL->value
            )
                ->count();
            $results[$index]['TOTAL-HBP'] = $batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::HIT_BY_PITCH->value
            )
                ->count();
            $results[$index]['TOTAL-PF'] = $batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::POP_FLY->value
            )
                ->count();
            $results[$index]['SPRAY-PITCH-LOCATION'] = $marks;
        }

        return $results;
    }

    public function hitterVelocity($data): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->hitterVelocityTeam($data);
        $result['players'] = $this->hitterVelocityPlayers($data);
        return $result;
    }

    private function hitterVelocityTeam($data)
    {
        $batters = $data['batters'];
        $marks = collect()->merge(($batters->map(function ($item) {
            if ($item->type_of_hit === BattingTrajectory::FLY_BALL->value ||
                $item->type_of_hit === BattingTrajectory::GROUND_BALL->value ||
                $item->type_of_hit === BattingTrajectory::LINE_DRIVE->value) {
                return [
                    'velocity' => $item->velocity,
                    'field_direction' => $item->field_direction,
                    'type_of_hit' => $item->type_of_hit,
                ];
            }

        })->filter()))->toArray();
        $totals = $batters->count();
        return [
            'LD%' => round(
                Helper::caseDivide($batters->where(
                    'type_of_hit',
                    '=',
                    BattingTrajectory::LINE_DRIVE->value
                )
                    ->count(), $totals) * 100,
                2
            ),
            'FB%' => round(Helper::caseDivide($batters->where(
                'type_of_hit',
                '=',
                BattingTrajectory::FLY_BALL->value
            )
                ->count(), $totals) * 100, 2),
            'GB%' => round(Helper::caseDivide($batters->where(
                'type_of_hit',
                '=',
                BattingTrajectory::GROUND_BALL->value
            )
                ->count(), $totals) * 100, 2),
            'MAX' => $batters->whereIn('type_of_hit', [
                BattingTrajectory::LINE_DRIVE->value,
                BattingTrajectory::GROUND_BALL->value,
                BattingTrajectory::FLY_BALL->value,
            ])->max('velocity'),
            'TOTAL-PITCH-LOCATION' => $marks,
        ];
    }

    private function hitterVelocityPlayers($data)
    {
        $batters = $data['batters']->groupBy('batter_id');
        $results = [];
        foreach ($batters as $index => $batter) {
            $marks = collect()->merge(($batter->map(function ($item) {
                if ($item->type_of_hit === BattingTrajectory::FLY_BALL->value ||
                    $item->type_of_hit === BattingTrajectory::GROUND_BALL->value ||
                    $item->type_of_hit === BattingTrajectory::LINE_DRIVE->value) {
                    return [
                        'velocity' => $item->velocity,
                        'field_direction' => $item->field_direction,
                        'type_of_hit' => $item->type_of_hit,
                    ];
                }

            })->filter()))->toArray();
            $totals = $batter->count();
            $results[$index]['LD%'] = round(Helper::caseDivide($batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::LINE_DRIVE->value
            )
                ->count(), $totals) * 100, 2);
            $results[$index]['FB%'] = round(Helper::caseDivide($batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::FLY_BALL->value
            )
                ->count(), $totals) * 100, 2);
            $results[$index]['GB%'] = round(Helper::caseDivide($batter->where(
                'type_of_hit',
                '=',
                BattingTrajectory::GROUND_BALL->value
            )
                ->count(), $totals) * 100, 2);
            $results[$index]['MAX'] = $batter->whereIn('type_of_hit', [
                BattingTrajectory::LINE_DRIVE->value,
                BattingTrajectory::GROUND_BALL->value,
                BattingTrajectory::FLY_BALL->value,
            ])->max('velocity');
            $results[$index]['TOTAL-PITCH-LOCATION'] = $marks;
        }
        return $results;
    }

    public function pitcherPitchBreakdown($data): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->pitcherPitchBreakdownTeam($data);
        $result['players'] = $this->pitcherPitchBreakdownPlayers($data);
        return $result;
    }

    private function pitcherPitchBreakdownTeam($data)
    {
        $pitchers = $data['pitchers'];
        $marks = collect()->merge(($pitchers->map(function ($item) {
            return [
                'pitch_mark' => $item->pitch_mark,
                'type_throw' => $item->type_throw,
            ];
        })->filter()))->toArray();
        $totals = $pitchers->count();
        return [
            'TOTAL' => $totals,
            'SL %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count(),
                    $totals
                ),
                2
            ),
            'SL-STRIKE %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            ),
            'FB %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count(),
                    $totals
                ),
                2
            ),
            'FB-STRIKE %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            ),
            'CB %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count(),
                    $totals
                ),
                2
            ),
            'CB-STRIKE %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            ),
            'CH %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count(),
                    $totals
                ),
                2
            ),
            'CH-STRIKE %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            ),
            'OTHER %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count(),
                    $totals
                ),
                2
            ),
            'OTHER-STRIKE %' => round(
                Helper::caseDivide(
                    $pitchers->where('type_throw', '=', PitchThrowTypes::OTHER->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            ),
            'TOTAL-PITCH-LOCATION' => $marks,
        ];
    }

    private function pitcherPitchBreakdownPlayers($data)
    {
        $pitchers = $data['pitchers']->groupBy('pitcher_id');
        $results = [];
        foreach ($pitchers as $index => $pitcher) {
            $marks = collect()->merge(($pitcher->map(function ($item) {
                return [
                    'pitch_mark' => $item->pitch_mark,
                    'type_throw' => $item->type_throw,
                ];
            })->filter()))->toArray();
            $totals = $pitcher->count();
            $results[$index]['TOTAL'] = $totals;
            $results[$index]['SL %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count(),
                    $totals
                ),
                2
            );
            $results[$index]['SL-STRIKE %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            );
            $results[$index]['FB %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count(),
                    $totals
                ),
                2
            );
            $results[$index]['FB-STRIKE %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            );
            $results[$index]['CB %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count(),
                    $totals
                ),
                2
            );
            $results[$index]['CB-STRIKE %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            );
            $results[$index]['CH %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count(),
                    $totals
                ),
                2
            );
            $results[$index]['CH-STRIKE %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            );
            $results[$index]['OTHER %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count(),
                    $totals
                ),
                2
            );
            $results[$index]['OTHER-STRIKE %'] = round(
                Helper::caseDivide(
                    $pitcher->where('type_throw', '=', PitchThrowTypes::OTHER->value)
                        ->where('zone', '=', 'S')->count(),
                    $totals
                ),
                2
            );
            $results[$index]['TOTAL-PITCH-LOCATION'] = $marks;
        }
        return $results;
    }

    public function pitcherContact($data): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->pitcherContactTeam($data);
        $result['players'] = $this->pitcherContactPlayers($data);
        return $result;
    }

    private function pitcherContactTeam($data)
    {
        $pitchers = $data['pitchers'];
        $marks = collect()->merge(($pitchers->map(function ($item) {
            return [
                'pitch_mark' => $item->pitch_mark,
                'trajectory' => $item->trajectory,
                'type_throw' => $item->type_throw,
            ];
        })->filter()))->toArray();
        $totals = $pitchers->count();
        return [
            'TOTAL' => $totals,
            'SL' => $pitchers->where('type_throw', '=', PitchThrowTypes::SLIDER->value)->count(),
            'FB' => $pitchers->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)->count(),
            'CB' => $pitchers->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)->count(),
            'CH' => $pitchers->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)->count(),
            'OTHER' => $pitchers->where('type_throw', '=', PitchThrowTypes::OTHER->value)->count(),
            'TOTAL-PITCH-LOCATION' => $marks,
        ];
    }

    private function pitcherContactPlayers($data)
    {
        $pitchers = $data['pitchers']->groupBy('pitcher_id');
        $results = [];
        foreach ($pitchers as $index => $pitcher) {
            $marks = collect()->merge(($pitcher->map(function ($item) {
                return [
                    'pitch_mark' => $item->pitch_mark,
                    'trajectory' => $item->trajectory,
                    'type_throw' => $item->type_throw,
                ];
            })->filter()))->toArray();
            $totals = $pitcher->count();
            $results[$index]['TOTAL'] = $totals;
            $results[$index]['SL'] = $pitcher->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                ->count();
            $results[$index]['FB'] = $pitcher->where(
                'type_throw',
                '=',
                PitchThrowTypes::FAST_BALL->value
            )->count();
            $results[$index]['CB'] = $pitcher->where(
                'type_throw',
                '=',
                PitchThrowTypes::CURVE_BALL->value
            )->count();
            $results[$index]['CH'] = $pitcher->where(
                'type_throw',
                '=',
                PitchThrowTypes::CHANGE_UP->value
            )->count();
            $results[$index]['OTHER'] = $pitcher->where(
                'type_throw',
                '=',
                PitchThrowTypes::OTHER->value
            )->count();
            $results[$index]['TOTAL-PITCH-LOCATION'] = $marks;

        }
        return $results;
    }

    public function pitcherVelocity($data): array
    {
        if (0 === count($data)) {
            return [];
        }
        $result['team_totals'] = $this->pitcherVelocityTeam($data);
        $result['players'] = $this->pitcherVelocityPlayers($data);
        return $result;
    }

    private function pitcherVelocityTeam($data)
    {
        $pitchers = $data['pitchers'];
        $marks = collect()->merge(($pitchers->map(function ($item) {
            return [
                'miles_per_hour' => $item->miles_per_hour,
                'zone' => $item->zone,
                'type_throw' => $item->type_throw,
            ];
        })->filter()))->toArray();
        return [
            'SL' => round($pitchers->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                ->avg('miles_per_hour') ?? 0, 2),
            'FB' => round($pitchers->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                ->avg('miles_per_hour') ?? 0, 2),
            'CB' => round($pitchers->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                ->avg('miles_per_hour') ?? 0, 2),
            'CH' => round($pitchers->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                ->avg('miles_per_hour') ?? 0, 2),
            'OTHER' => round($pitchers->where('type_throw', '=', PitchThrowTypes::OTHER->value)
                ->avg('miles_per_hour') ?? 0, 2),
            'MAX-FB' => $pitchers->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                ->max('miles_per_hour'),
            'TOTAL-PITCH-LOCATION' => $marks,
        ];
    }

    private function pitcherVelocityPlayers($data)
    {
        $pitchers = $data['pitchers']->groupBy('pitcher_id');
        $results = [];
        foreach ($pitchers as $index => $pitcher) {
            $marks = collect()->merge(($pitcher->map(function ($item) {
                return [
                    'miles_per_hour' => $item->miles_per_hour,
                    'zone' => $item->zone,
                    'type_throw' => $item->type_throw,
                ];
            })->filter()))->toArray();
            $results[$index]['SL'] = round($pitcher->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                ->avg('miles_per_hour') ?? 0, 2);
            $results[$index]['FB'] = round($pitcher->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                ->avg('miles_per_hour') ?? 0, 2);
            $results[$index]['CB'] = round($pitcher->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                ->avg('miles_per_hour') ?? 0, 2);
            $results[$index]['CH'] = round($pitcher->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                ->avg('miles_per_hour') ?? 0, 2);
            $results[$index]['OTHER'] = round($pitcher->where('type_throw', '=', PitchThrowTypes::OTHER->value)
                ->avg('miles_per_hour') ?? 0, 2);
            $results[$index]['MAX-FB'] = $pitcher->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                ->max('miles_per_hour');
            $results[$index]['TOTAL-PITCH-LOCATION'] = $marks;
        }
        return $results;
    }

    /**
     * @param  mixed  $item
     * @return mixed
     */
    public function getPitches3less(mixed $item): mixed
    {
        return HelperStatisticsLiveAB::getPitches3less($item);
    }

    /**
     * @param  Collection  $batter
     * @return array
     */

}
