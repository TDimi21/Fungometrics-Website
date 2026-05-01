<?php

declare(strict_types=1);

namespace App\Utils\Filters;

use App\Models\Concerns\PracticeTypes;
use App\Services\ResultTrainingService;
use App\Services\Statistics\CageStatisticsService;

class CageFilters
{
    public function handle(array $params)
    {
        $data=[];
        $stat = new CageStatisticsService();
        $dataProcess = ResultTrainingService::getCageResults(
            $params['team'],
            $params['players'],
            $params['dates']
        );
        if(0 === $dataProcess->count()) {
            return [];
        }
        $cageOptions = [
            29 => ['label'=>'launch-angle-totals','method'=>'launchAngleTotals'],
            30 => ['label'=>'launch-angle-percents','method'=>'launchAnglePercents'],
            31 => ['label'=>'launch-angle-average-exit-velocity','method'=>'launchAngleExitVelocityAverage'],
            32 => ['label'=>'spray-angle-totals','method'=>'sprayAngleTotals'],
            33 => ['label'=>'spray-angle-percents','method'=>'sprayAnglePercents'],
            34 => ['label'=>'spray-angle-average-exit-velocity','method'=>'sprayAngleExitVelocityAverage'],
        ];

        foreach ($cageOptions as $index => $key) {
            if (in_array($index, $params['options'][PracticeTypes::CAGE->value], true)) {
                $data[$key['label']] = $stat->{$key['method']}($dataProcess);
            }
        }
        return $data;
    }

}
