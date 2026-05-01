<?php

declare(strict_types=1);

namespace App\Utils\Filters;

use App\Models\Concerns\PracticeModes;
use App\Services\ResultTrainingService;
use App\Services\Statistics\WeightBallStatisticsService;

class WeightBallFilters
{
    public function handle(array $params)
    {
        $data=[];
        $stat = new WeightBallStatisticsService();
        $dataProcess = ResultTrainingService::getWeightBallResults(
            $params['team'],
            $params['players'],
            $params['dates']
        );
        if(0 === $dataProcess->count()) {
            return [];
        }
        $cageOptions = [
            45 => ['label'=>'totals','method'=>'totals'],
            46 => ['label'=>'average-velocity','method'=>'averageVelocities'],
            47 => ['label'=>'max-velocity','method'=>'maxVelocities'],
        ];

        foreach ($cageOptions as $index => $key) {
            if (in_array($index, $params['options'][PracticeModes::WEIGHT_BALL->value], true)) {
                $data[$key['label']] = $stat->{$key['method']}($dataProcess);
            }
        }
        return $data;
    }

}
