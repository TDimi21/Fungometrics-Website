<?php

declare(strict_types=1);

namespace App\Utils\Filters;

use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\SidesFieldPosition;
use App\Services\ResultTrainingService;
use App\Services\Statistics\BattingStatisticsService;

class BattingFilters
{
    /**
     * @param array $params
     * @return array
     */
    public function handle(array $params): array
    {
        $data=[];
        $stat = new BattingStatisticsService();
        $dataProcess = ResultTrainingService::getBattingResults(
            $params['team'],
            $params['players'],
            $params['dates']
        );
        if(0 === $dataProcess->count()) {
            return [];
        }
        $battingOptions = [
            0 => ['label'=>'totals','method'=>'totals','params'=>null],
            1 => ['label'=>'percents','method'=>'percents','params'=>null],
            2 => ['label'=>'average_velocity_breakdown','method'=>'averageVelocityBreakDown','params'=>null],
            3 => ['label'=>'max_velocity_breakdown','method'=>'maxVelocityBreakDown','params'=>null],
            4 => ['label'=>'TOH-L','method'=>'typeOfHitPercents','params'=>SidesFieldPosition::LEFT->value],
            5 => ['label'=>'TOH-R','method'=>'typeOfHitPercents','params'=>SidesFieldPosition::RIGHT->value],
            6 => ['label'=>'TOH-M','method'=>'typeOfHitPercents','params'=>SidesFieldPosition::CENTER->value],
            7 => ['label'=>'QOH-L','method'=>'qualityOfHitPercents','params'=>SidesFieldPosition::LEFT->value],
            8 => ['label'=>'QOH-R','method'=>'qualityOfHitPercents','params'=>SidesFieldPosition::RIGHT->value],
            9 => ['label'=>'QOH-M','method'=>'qualityOfHitPercents','params'=>SidesFieldPosition::CENTER->value],
        ];

        foreach ($battingOptions as $index => $key) {
            if (in_array($index, $params['options'][PracticeTypes::BATTING->value], true)) {
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
