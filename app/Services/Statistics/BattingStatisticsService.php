<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\CaptureZone;
use App\Models\Concerns\ContactQuality;
use App\Models\Concerns\SidesFieldPosition;
use App\Utils\Helper;

final class BattingStatisticsService
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

    public function maxVelocityBreakDown($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->maxVelocitiesTeam($data);
        $result['players'] = $this->maxVelocitiesPlayer($data);
        return $result;
    }

    public function typeOfHitPercents($data, string $position): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->typeOfHitPercentsTeam($data, $position);
        $result['players'] = $this->typeOfHitPercentsPlayers($data, $position);
        return $result;
    }


    public function qualityOfHitPercents($data, string $position): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->qualityOfHitPercentsTeam($data, $position);
        $result['players'] = $this->qualityOfHitPercentsPlayers($data, $position);
        return $result;
    }

    private function getTeamTotals($data)
    {
        return [
            'swings'=>$data->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count(),
            'BALL'=>$data->where('zone', '=', CaptureZone::BALL->value)->count(),
            'STRIKE'=>$data->where('zone', '=', CaptureZone::STRIKE->value)->count(),
            'GB'=>$data->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)->count(),
            'LD'=>$data->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)->count(),
            'PF'=>$data->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)->count(),
            'FB'=>$data->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)->count(),
            'SM'=>$data->where('type_of_hit', '=', BattingTrajectory::SWING_MISS->value)->count(),
            'WEAK'=>$data->where('type_of_hit', '=', ContactQuality::WEAK->value)->count(),
            'AVERAGE'=>$data->where('type_of_hit', '=', ContactQuality::AVERAGE->value)->count(),
            'HARD'=>$data->where('type_of_hit', '=', ContactQuality::HARD->value)->count(),
            'TAKE'=>$data->where('field_direction', '=', null)->count(),
            'LEFT'=>$data->where('field_direction', '=', SidesFieldPosition::LEFT->value)->count(),
            'RIGHT'=>$data->where('field_direction', '=', SidesFieldPosition::RIGHT->value)->count(),
            'MIDDLE'=>$data->where('field_direction', '=', SidesFieldPosition::CENTER->value)->count(),
        ];
    }

    private function getPlayersTotals($data)
    {
        $dataGroup = $data->groupBy('batter_id');

        $playerTotals = [];
        foreach ($dataGroup as $key => $item) {
            $playerTotals[$key] = [
                'swings'=>$item->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count(),
                'BALL'=>$item->where('zone', '=', CaptureZone::BALL->value)->count(),
                'STRIKE'=>$item->where('zone', '=', CaptureZone::STRIKE->value)->count(),
                'GB'=>$item->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)->count(),
                'LD'=>$item->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)->count(),
                'PF'=>$item->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)->count(),
                'FB'=>$item->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)->count(),
                'SM'=>$item->where('type_of_hit', '=', BattingTrajectory::SWING_MISS->value)->count(),
                'WEAK'=>$item->where('type_of_hit', '=', ContactQuality::WEAK->value)->count(),
                'AVERAGE'=>$item->where('type_of_hit', '=', ContactQuality::AVERAGE->value)->count(),
                'HARD'=>$item->where('type_of_hit', '=', ContactQuality::HARD->value)->count(),
                'TAKE'=>$item->where('field_direction', '=', null)->count(),
                'LEFT'=>$item->where('field_direction', '=', SidesFieldPosition::LEFT->value)->count(),
                'RIGHT'=>$item->where('field_direction', '=', SidesFieldPosition::RIGHT->value)->count(),
                'MIDDLE'=>$item->where('field_direction', '=', SidesFieldPosition::CENTER->value)->count(),
            ];

        }
        return $playerTotals;
    }

    private function getPlayersPercents($data)
    {

        $dataGroup = $data->groupBy('batter_id');

        $playerTotals = [];
        foreach ($dataGroup as $key => $item) {
            $totals = $item->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
            $playerTotals[$key] = [
                'swings'=>$totals,
                'BALL'=>round(Helper::caseDivide($item->where('zone', '=', CaptureZone::BALL->value)
                    ->count(), $totals)*100, 2),
                'STRIKE'=>round(Helper::caseDivide($item->where('zone', '=', CaptureZone::STRIKE->value)
                    ->count(), $totals)*100, 2),
                'GB'=>round(Helper::caseDivide($item->where(
                    'type_of_hit',
                    '=',
                    BattingTrajectory::GROUND_BALL->value
                )->count(), $totals)*100, 2),
                'LD'=>round(Helper::caseDivide($item->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)
                    ->count(), $totals)*100, 2),
                'PF'=>round(Helper::caseDivide($item->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)
                    ->count(), $totals)*100, 2),
                'FB'=>round(Helper::caseDivide($item->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)
                    ->count(), $totals)*100, 2),
                'SM'=>round(Helper::caseDivide($item->where('type_of_hit', '=', BattingTrajectory::SWING_MISS->value)
                    ->count(), $totals)*100, 2),
                'WEAK'=>round(Helper::caseDivide($item->where('type_of_hit', '=', ContactQuality::WEAK->value)->count(), $totals)*100, 2),
                'AVERAGE'=>round(Helper::caseDivide($item->where('type_of_hit', '=', ContactQuality::AVERAGE->value)
                    ->count(), $totals)*100, 2),
                'HARD'=>round(Helper::caseDivide($item->where('type_of_hit', '=', ContactQuality::HARD->value)->count(), $totals)*100, 2),
                'TAKE'=>round(Helper::caseDivide($item->where('field_direction', '=', null)->count(), $totals)*100, 2),
                'LEFT'=>round(Helper::caseDivide($item->where(
                    'field_direction',
                    '=',
                    SidesFieldPosition::LEFT->value
                )->count(), $totals)*100, 2),
                'RIGHT'=>round(Helper::caseDivide($item->where(
                    'field_direction',
                    '=',
                    SidesFieldPosition::RIGHT->value
                )->count(), $totals)*100, 2),
                'MIDDLE'=>round(Helper::caseDivide($item->where(
                    'field_direction',
                    '=',
                    SidesFieldPosition::CENTER->value
                )->count(), $totals)*100, 2),
            ];

        }
        return $playerTotals;
    }

    private function getTeamPercents($data)
    {
        $totals = $data->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
        return [
            'swings'=>$totals,
            'BALL'=>round(Helper::caseDivide($data->where('zone', '=', CaptureZone::BALL->value)
                ->count(), $totals)*100, 2),
            'STRIKE'=>round(Helper::caseDivide($data->where('zone', '=', CaptureZone::STRIKE->value)
                ->count(), $totals)*100, 2),
            'GB'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)
                ->count(), $totals)*100, 2),
            'LD'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)
                ->count(), $totals)*100, 2),
            'PF'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)->count(), $totals)*100, 2),
            'FB'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)
                ->count(), $totals)*100, 2),
            'SM'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::SWING_MISS->value)
                ->count(), $totals)*100, 2),
            'WEAK'=>round(Helper::caseDivide($data->where('type_of_hit', '=', ContactQuality::WEAK->value)->count(), $totals)*100, 2),
            'AVERAGE'=>round(Helper::caseDivide($data->where('type_of_hit', '=', ContactQuality::AVERAGE->value)
                ->count(), $totals)*100, 2),
            'HARD'=>round(Helper::caseDivide($data->where('type_of_hit', '=', ContactQuality::HARD->value)->count(), $totals)*100, 2),
            'TAKE'=>round(Helper::caseDivide($data->where('field_direction', '=', null)->count(), $totals)*100, 2),
            'LEFT'=>round(Helper::caseDivide($data->where('field_direction', '=', SidesFieldPosition::LEFT->value)
                ->count(), $totals)*100, 2),
            'RIGHT'=>round(Helper::caseDivide($data->where('field_direction', '=', SidesFieldPosition::RIGHT->value)
                ->count(), $totals)*100, 2),
            'MIDDLE'=>round(Helper::caseDivide($data->where(
                'field_direction',
                '=',
                SidesFieldPosition::CENTER->value
            )->count(), $totals)*100, 2),
        ];
    }

    /**
     * @param $data
     * @return array
     */
    private function getAverageForPlayers($data): array
    {

        $dataGroup = $data->groupBy('batter_id');
        $averageVelocity = [];
        foreach ($dataGroup as $key => $item) {
            $averageVelocity[$key] = [
                'swings'=>$item->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count(),

                'GB' => round(collect($item)->where('type_of_hit', BattingTrajectory::GROUND_BALL->value)
                    ->avg('velocity')??0, 2),
                'LD' => round(collect($item)->where('type_of_hit', BattingTrajectory::LINE_DRIVE->value)
                    ->avg('velocity')??0, 2),
                'PF' => round(collect($item)->where('type_of_hit', BattingTrajectory::POP_FLY->value)
                    ->avg('velocity')??0, 2),
                'FB' => round(collect($item)->where('type_of_hit', BattingTrajectory::FLY_BALL->value)
                    ->avg('velocity')??0, 2),
            ];
        }

        return $averageVelocity;
    }

    private function getAverageForTeam($data)
    {
        $totals = $data->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
        return [
            'swings'=>$totals,
            'GB'=>round($data->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)
                ->avg('velocity')??0, 2),
            'LD'=>round($data->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)
                ->avg('velocity')??0, 2),
            'PF'=>round($data->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)
                ->avg('velocity')??0, 2),
            'FB'=>round($data->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)
                ->avg('velocity')??0, 2),
        ];
    }

    /**
     * @param $data
     * @return array
     */
    private function maxVelocitiesPlayer($data): array
    {
        $maxVelocities = [];
        $dataGroup = $data->groupBy('batter_id');
        foreach ($dataGroup as $key => $item) {
            $maxVelocities[$key] = [
                'swings'=>$item->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count(),

                'GB' => collect($item)->where('type_of_hit', BattingTrajectory::GROUND_BALL->value)
                    ->max('velocity'),
                'LD' => collect($item)->where('type_of_hit', BattingTrajectory::LINE_DRIVE->value)
                    ->max('velocity'),
                'PF' => collect($item)->where('type_of_hit', BattingTrajectory::POP_FLY->value)
                    ->max('velocity'),
                'FB' => collect($item)->where('type_of_hit', BattingTrajectory::FLY_BALL->value)
                    ->max('velocity'),
            ];
        }
        return $maxVelocities;
    }

    private function maxVelocitiesTeam($data)
    {
        $totals = $data->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
        return [
            'swings'=>$totals,
            'GB'=>$data->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)->max('velocity'),
            'LD'=>$data->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)->max('velocity'),
            'PF'=>$data->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)->max('velocity'),
            'FB'=>$data->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)->max('velocity'),
        ];
    }

    /**
     * @return array
     */
    private function typeOfHitPercentsPlayers($data, string $position): array
    {
        $dataGroup = $data->groupBy('batter_id');
        $percentByHits = [];
        foreach ($dataGroup as $key => $item) {
            $totals = $item->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
            $percentByHits[$key] = [
                'swings'=>$totals,
                'GB' => round(Helper::caseDivide(collect($item)->where('type_of_hit', BattingTrajectory::GROUND_BALL->value)
                    ->where('field_direction', $position)->count(), $totals)*100, 2),
                'LD' => round(Helper::caseDivide(collect($item)->where('type_of_hit', BattingTrajectory::LINE_DRIVE->value)
                    ->where('field_direction', $position)->count(), $totals)*100, 2),
                'PF' => round(Helper::caseDivide(collect($item)->where('type_of_hit', BattingTrajectory::POP_FLY->value)
                    ->where('field_direction', $position)->count(), $totals)*100, 2),
                'FB' => round(Helper::caseDivide(collect($item)->where('type_of_hit', BattingTrajectory::FLY_BALL->value)
                    ->where('field_direction', $position)->count(), $totals)*100, 2),
            ];
        }
        return $percentByHits;
    }

    private function typeOfHitPercentsTeam($data, string $position)
    {
        $totals = $data->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
        return [
            'swings'=>$totals,
            'GB'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)
                ->where('field_direction', $position)->count(), $totals)*100, 2),
            'LD'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)
                ->where('field_direction', $position)->count(), $totals)*100, 2),
            'PF'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::POP_FLY->value)
                ->where('field_direction', $position)->count(), $totals)*100, 2),
            'FB'=>round(Helper::caseDivide($data->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)
                ->where('field_direction', $position)->count(), $totals)*100, 2),
        ];
    }

    private function qualityOfHitPercentsPlayers($data, string $position): array
    {
        $dataGroup = $data->groupBy('batter_id');
        $qualityByHits = [];
        foreach ($dataGroup as $key => $item) {
            $totals = $item->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
            $qualityByHits[$key] = [
                'swings'=>$totals,
                'WEAK' => round(Helper::caseDivide(collect($item)->where('quality_of_contact', ContactQuality::WEAK->value)
                    ->where('field_direction', $position)->count(), $totals)*100, 2),
                'AVERAGE' => round(Helper::caseDivide(collect($item)->where('quality_of_contact', ContactQuality::AVERAGE->value)
                    ->where('field_direction', $position)->count(), $totals)*100, 2),
                'HARD' => round(Helper::caseDivide(collect($item)->where('quality_of_contact', ContactQuality::HARD->value)
                    ->where('field_direction', $position)->count(), $totals)*100, 2),
            ];
        }
        return $qualityByHits;
    }

    private function qualityOfHitPercentsTeam($data, string $position)
    {
        $totals = $data->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)->count();
        return [
            'swings'=>$totals,
            'WEAK'=>round(Helper::caseDivide($data->where('quality_of_contact', '=', ContactQuality::WEAK->value)
                ->where('field_direction', $position)->count(), $totals)*100, 2),
            'AVERAGE'=>round(Helper::caseDivide($data->where('quality_of_contact', '=', ContactQuality::AVERAGE->value)
                ->where('field_direction', $position)->count(), $totals)*100, 2),
            'HARD'=>round(Helper::caseDivide($data->where('quality_of_contact', '=', ContactQuality::HARD->value)
                ->where('field_direction', $position)->count(), $totals)*100, 2),

        ];
    }
}
