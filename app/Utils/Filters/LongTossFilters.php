<?php

declare(strict_types=1);

namespace App\Utils\Filters;

use App\Models\Concerns\PracticeModes;
use App\Services\ResultTrainingService;
use App\Services\Statistics\LongTossStatisticsService;

class LongTossFilters
{
    public function handle(array $params)
    {
        $data=[];
        $stat = new LongTossStatisticsService();
        $dataProcess = ResultTrainingService::getLongTossResults(
            $params['team'],
            $params['players'],
            $params['dates']
        );
        if(0 === $dataProcess->count()) {
            return [];
        }
        $longTossOptions = [
            39 => ['label'=>'totals-distances','method'=>'distanceTotals'],
            40 => ['label'=>'percents-distances','method'=>'distancePercentage'],
            41 => ['label'=>'average-distance','method'=>'distanceAverage'],
            42 => ['label'=>'total-hops','method'=>'totalHops'],
            43 => ['label'=>'average-hops','method'=>'averageHops'],
            44 => ['label'=>'max-hops','method'=>'maxHops'],
        ];

        foreach ($longTossOptions as $index => $key) {
            if (in_array($index, $params['options'][PracticeModes::LONG_TOSS->value], true)) {
                $data[$key['label']] = $stat->{$key['method']}($dataProcess);
            }
        }
        return $data;
    }

}
