<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Services\Statistics\HelperStatisticsLiveAB;
use App\Utils\Helper;

class ServiceCountBSLiveAB
{
    private GetLiveABPracticeResults $getLiveABPracticeResults;
    private $bsCount;

    public function __construct(GetLiveABPracticeResults $getLiveABPracticeResults, $bsCount)
    {
        $this->getLiveABPracticeResults = $getLiveABPracticeResults;
        $this->bsCount = $bsCount;
    }

    public function getBSTeamPitch($data)
    {

        foreach ($this->bsCount as $pitchType) {
            $results['b-s'][$pitchType] = HelperStatisticsLiveAB::countBSxPitchTypes($data, $pitchType);
        }
        $results['b-s']['total'] = HelperStatisticsLiveAB::sumBSByPitch($data);

        return $results;

    }

    public function getBSTeamTrajectory($data)
    {
        foreach ($this->bsCount as $pitchType) {
            $results['b-s'][$pitchType] = HelperStatisticsLiveAB::countBSxTrajectory($data, $pitchType);
        }
        $results['b-s']['total'] = HelperStatisticsLiveAB::sumBSByTrajectory($data);
        return $results;

    }

    public function getBSTeamContactQuality($data)
    {
        foreach ($this->bsCount as $pitchType) {
            $results['b-s'][$pitchType] = HelperStatisticsLiveAB::countBSxQualityContact($data, $pitchType);
        }
        $results['b-s']['total'] = HelperStatisticsLiveAB::sumBSByContact($data);
        return $results;
    }

    public function getBSTeam1B($data)
    {
        $results=[];
        foreach ($this->bsCount as $bsCount) {
            $results['b-s'][$bsCount] = HelperStatisticsLiveAB::countBSx1B($data, $bsCount);
        }
        $results['b-s']['total'] = array_sum($results['b-s']);
        return $results;
    }

    public function getBSTeamXHB($data, $atBat)
    {
        $results=[];
        foreach ($this->bsCount as $bsCount) {
            $results['b-s'][$bsCount] = HelperStatisticsLiveAB::countBSXBH($data, $bsCount);
        }
        $results['b-s']['total'] = round(array_sum($results['b-s']), 4);
        return $results;
    }

    public function getBSTeamBatAvg($data, $atBat)
    {
        $results =[];
        foreach ($this->bsCount as $bsCount) {
            $results['b-s'][$bsCount] = round(Helper::caseDivide(
                HelperStatisticsLiveAB::countBSxBatAvg($data, $bsCount),
                $atBat
            ), 4);
        }
        $results['b-s']['total'] = round(array_sum($results['b-s']), 2);
        return $results;
    }

    public function getBSTeamSLGPercent($data, $atBat)
    {
        $results=[];
        foreach ($this->bsCount as $bsCount) {
            $results['b-s'][$bsCount] = round(Helper::caseDivide(HelperStatisticsLiveAB::countBSxSlgPercents(
                $data,
                $bsCount
            ), $atBat), 4);
        }
        $results['b-s']['total'] =round(array_sum($results['b-s']), 4);
        return $results;
    }
}
