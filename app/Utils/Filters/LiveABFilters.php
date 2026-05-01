<?php

declare(strict_types=1);

namespace App\Utils\Filters;

use App\Models\Concerns\PracticeTypes;
use App\Services\ResultTrainingService;
use App\Services\Statistics\LiveABStatisticsService;

class LiveABFilters
{
    public function handle(array $params)
    {
        $data=[];
        $stat = new LiveABStatisticsService();
        $dataProcess = ResultTrainingService::getLiveABResults(
            team: $params['team'],
            players: $params['players'],
            dates: $params['dates']
        );
        if(0===count($dataProcess)) {
            return [];
        }
        $liveOptions = [
            48=>['label'=>'hitter-basic','method'=>'hitterResults','params'=>null],
            49=>['label'=>'hitter-advance','method'=>'hitterResults','params'=>false],
            50=>['label'=>'pitcher-basic','method'=>'pitcherResults','params'=>null],
            51=>['label'=>'pitcher-advance','method'=>'pitcherResults','params'=>false],
            52=>['label'=>'hitter-pitch-breakdown','method'=>'hitterPitchBreakdown','params'=>null],
            53=>['label'=>'hitter-contact','method'=>'hitterContact','params'=>null],
            54=>['label'=>'hitter-trajectory','method'=>'hitterTrajectory','params'=>null],
            55=>['label'=>'hitter-velocity','method'=>'hitterVelocity','params'=>null],
            56=>['label'=>'pitcher-pitch-breakdown','method'=>'pitcherPitchBreakdown','params'=>null],
            57=>['label'=>'pitcher-contact','method'=>'pitcherContact','params'=>null],
            58=>['label'=>'pitcher-velocity','method'=>'pitcherVelocity','params'=>null]

        ];

        foreach ($liveOptions as $index => $key) {
            if (in_array($index, $params['options'][PracticeTypes::LIVE_AB->value], true)) {
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
