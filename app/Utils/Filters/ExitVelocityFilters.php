<?php

declare(strict_types=1);

namespace App\Utils\Filters;

use App\Models\Concerns\PracticeModes;
use App\Services\ResultTrainingService;
use App\Services\Statistics\ExitVelocityStatisticsService;

class ExitVelocityFilters
{
    public function handle(array $params)
    {
        $data=[];
        $stat = new ExitVelocityStatisticsService();
        $dataProcess = ResultTrainingService::getExitVelocityResults(
            $params['team'],
            $params['players'],
            $params['dates']
        );
        if(0 === $dataProcess->count()) {
            return [];
        }
        $exitVelocityOptions = [
            35 => ['label'=>'totals','method'=>'totals'],
            36 => ['label'=>'percents','method'=>'percents'],
            37 => ['label'=>'average-velocity','method'=>'averageVelocities'],
            38 => ['label'=>'top-velocity','method'=>'maxVelocities'],
        ];

        foreach ($exitVelocityOptions as $index => $key) {
            if (in_array($index, $params['options'][PracticeModes::EXIT_VELOCITY->value], true)) {
                $data[$key['label']] = $stat->{$key['method']}($dataProcess);
            }
        }
        return $data;
    }

}
