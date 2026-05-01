<?php

declare(strict_types=1);

namespace App\Utils\Filters;

use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\PracticeTypes;
use App\Services\ResultTrainingService;
use App\Services\Statistics\BullpenStatisticsService;

class BullpenFilters
{
    public function handle(array $params)
    {
        $data=[];
        $stat = new BullpenStatisticsService();
        $dataProcess = ResultTrainingService::getBullpenResults(
            $params['team'],
            $params['players'],
            $params['dates']
        );
        if(0 === $dataProcess->count()) {
            return [];
        }
        $bullpenOptions = [
            10 => ['label'=>'totals','method'=>'totals','params'=>null],
            11 => ['label'=>'percents','method'=>'percents','params'=>null],
            12 => ['label'=>'average_velocity_breakdown','method'=>'averageVelocityBreakDown','params'=>null],
            13 => ['label'=>'top_velocity_breakdown','method'=>'topVelocityBreakDown','params'=>null],
            14 => ['label'=>'TOT-FAST','method'=>'typeThrowPercents','params'=>PitchThrowTypes::FAST_BALL->value],
            15 => ['label'=>'TOT-CURVE','method'=>'typeThrowPercents','params'=>PitchThrowTypes::CURVE_BALL->value],
            16 => ['label'=>'TOT-CHANGE','method'=>'typeThrowPercents','params'=>PitchThrowTypes::CHANGE_UP->value],
            17 => ['label'=>'TOT-SLIDER','method'=>'typeThrowPercents','params'=>PitchThrowTypes::SLIDER->value],
            18 => ['label'=>'TOT-OTHER','method'=>'typeThrowPercents','params'=>PitchThrowTypes::OTHER->value],
            19 => ['label'=>'TRAJECTORY-GB','method'=>'typeTrajectoryPercent','params'=>BattingTrajectory::GROUND_BALL->value],
            20 => ['label'=>'TRAJECTORY-LD','method'=>'typeTrajectoryPercent','params'=>BattingTrajectory::LINE_DRIVE->value],
            21 => ['label'=>'TRAJECTORY-FB','method'=>'typeTrajectoryPercent','params'=>BattingTrajectory::FLY_BALL->value],
            22 => ['label'=>'TRAJECTORY-PF','method'=>'typeTrajectoryPercent','params'=>BattingTrajectory::POP_FLY->value],
            23 => ['label'=>'TRAJECTORY-FOUL','method'=>'typeTrajectoryPercent','params'=>BattingTrajectory::FOUL->value],
            24 => ['label'=>'TOT-FAST-STRIKE','method'=>'strikeThrowPercents','params'=>PitchThrowTypes::FAST_BALL->value],
            25 => ['label'=>'TOT-CURVE-STRIKE','method'=>'strikeThrowPercents','params'=>PitchThrowTypes::CURVE_BALL->value],
            26 => ['label'=>'TOT-CHANGE-STRIKE','method'=>'strikeThrowPercents','params'=>PitchThrowTypes::CHANGE_UP->value],
            27 => ['label'=>'TOT-SLIDER-STRIKE','method'=>'strikeThrowPercents','params'=>PitchThrowTypes::SLIDER->value],
            28 => ['label'=>'TOT-OTHER-STRIKE','method'=>'strikeThrowPercents','params'=>PitchThrowTypes::OTHER->value],
        ];

        foreach ($bullpenOptions as $index => $key) {
            if (in_array($index, $params['options'][PracticeTypes::BULLPEN->value], true)) {
                if(null!==$key['params']) {
                    $data[$key['label']] = $stat->{$key['method']}($dataProcess, $key['params']);
                } else {
                    $data[$key['label']] = $stat->{$key['method']}($dataProcess);
                }

            }
        }
        return $data;
    }


}
