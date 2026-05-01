<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\SidesFieldPosition;
use App\Models\LiveABPracticeResult;
use App\Models\TeamsLiveAB;
use App\Utils\Helper;

final class TeamStatisticsService
{
    public $batting;
    public $liveAB;
    public $battingAB;
    public $pitching;
    public $pitchingAB;
    public $cage;

    public function __construct(private $id)
    {
        $this->batting = BattingPracticeResult::where('team_id', '=', $this->id)->get();
        $this->pitching = BullpenPracticeResult::where('team_id', '=', $this->id)->get();
        $liveAbPractices = TeamsLiveAB::where('team_id', '=', $this->id)
            ->pluck('practice_id')
            ->unique()
            ->all();
        $this->cage = CagePracticeResult::where('team_id', '=', $this->id)->get();
        $this->liveAB = LiveABPracticeResult::whereIn('practice_id', $liveAbPractices)->get();
    }

    public function getBallsStrikeData(): array
    {
        $data = $this->batting;
        $totalStrikes = $data->where('zone', 'S')
            ->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)
            ->count();
        $totalBalls = $data->where('zone', 'B')
            ->where('type_of_hit', '<>', BattingTrajectory::TAKE->value)
            ->count();
        $total = $totalBalls+$totalStrikes;
        return [
            'total_s_b' => $total,
            'strikes' => [
                'count' => $totalStrikes,
                'percent' => round(Helper::caseDivide($totalStrikes, $total) * 100)
            ],
            'balls' => [
                'count' => $totalBalls,
                'percent' => round(Helper::caseDivide($totalBalls, $total) * 100)
            ],
        ];
    }

    public function getDirectionalData()
    {
        $total = $this->batting->where('is_in_match', '=', false)->count();

        $countLeft = $this->batting
            ->where('field_direction', '=', SidesFieldPosition::LEFT->value)
            ->count();
        $countRight = $this->batting
            ->where('field_direction', '=', SidesFieldPosition::RIGHT->value)
            ->count();
        $countMiddle = $this->batting
            ->where('field_direction', '=', SidesFieldPosition::CENTER->value)
            ->count();

        $effectiveBats = $countLeft+$countRight+$countMiddle;
        return [
            'total' => $total,
            'effective'=>$effectiveBats,
            'LEFT' => [
                'count'=> $countLeft,
                'percent'=>round(Helper::caseDivide($countLeft, $effectiveBats) * 100)
            ],
            'RIGHT' => [
                'count'=> $countRight,
                'percent'=>round(Helper::caseDivide($countRight, $effectiveBats) * 100)
            ],
            'MIDDLE' => [
                'count'=> $countMiddle,
                'percent'=>round(Helper::caseDivide($countMiddle, $effectiveBats) * 100)
            ]
        ];
    }

    public function getHitTypeBattingData()
    {

        $totals = $this->batting->count();

        $totalsGB = $this->batting->where('type_of_hit', '=', BattingTrajectory::GROUND_BALL->value)->count();
        $totalsLD = $this->batting->where('type_of_hit', '=', BattingTrajectory::LINE_DRIVE->value)->count() ;
        $totalsFLY = $this->batting->where('type_of_hit', '=', BattingTrajectory::FLY_BALL->value)->count();
        $totalsSwingMiss = $this->batting->where('type_of_hit', '=', BattingTrajectory::SWING_MISS->value)->count();
        $totalsFoul = $this->batting->where('type_of_hit', '=', BattingTrajectory::FOUL->value)->count();
        $totalsTake = $this->batting->where('type_of_hit', '=', BattingTrajectory::TAKE->value)->count();

        $effectiveContacts = $totalsFoul+$totalsTake+$totalsFLY+$totalsGB+$totalsSwingMiss+$totalsLD;

        return [
            'totals'=>$totals,
            'effective'=>$effectiveContacts,
            'GB'=>[
                'count'=>$totalsGB,
                'percent'=>round(Helper::caseDivide($totalsGB, $effectiveContacts) *100)
            ],

            'LD'=>[
                'count'=>$totalsLD,
                'percent'=>round(Helper::caseDivide($totalsLD, $effectiveContacts) *100)
            ],
            'FLY'=>[
                'count'=>$totalsFLY,
                'percent'=>round(Helper::caseDivide($totalsFLY, $effectiveContacts) *100)
            ],
            'SM/F'=>[
                'count'=> $totalsFoul +$totalsSwingMiss,
                'percent'=>round(Helper::caseDivide($totalsFoul +$totalsSwingMiss, $effectiveContacts) *100)
            ],
            'TAKE'=>[
                'count'=>$totalsTake,
                'percent'=>round(Helper::caseDivide($totalsTake, $effectiveContacts) *100)
            ],
        ];

    }

    public function averagePitchVelocityData()
    {
        return [
            'totals'=>$this->pitching->count(),
            'FB'=>round($this->pitching->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                ->where('miles_per_hour', '>', 0)
                ->avg('miles_per_hour')??0),
            'CH'=>round($this->pitching->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                ->where('miles_per_hour', '>', 0)
                ->avg('miles_per_hour')??0),
            'CB'=>round($this->pitching->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                ->where('miles_per_hour', '>', 0)
                ->avg('miles_per_hour')??0),
            'SL'=>round($this->pitching->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                ->where('miles_per_hour', '>', 0)
                ->avg('miles_per_hour')??0),
            'OTHER'=>round($this->pitching->where('type_throw', '=', PitchThrowTypes::OTHER->value)
                ->where('miles_per_hour', '>', 0)
                ->avg('miles_per_hour')??0),
        ];
    }

    public function pitchesThrowData()
    {
        return [
            'totals'=>$this->pitching->count(),
            'FB'=>$this->pitching->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
                ->count(),
            'CH'=>$this->pitching->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
                ->count(),
            'CB'=>$this->pitching->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
                ->count(),
            'SL'=>$this->pitching->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
                ->count(),
            'OTHER'=>$this->pitching->where('type_throw', '=', PitchThrowTypes::OTHER->value)
                ->count(),
        ];
    }

    public function getHitTypePitchingData()
    {

        $totals = $this->pitching->count();

        $totalsGB = $this->pitching->where('trajectory', '=', BattingTrajectory::GROUND_BALL->value)->count();
        $totalsLD = $this->pitching->where('trajectory', '=', BattingTrajectory::LINE_DRIVE->value)->count() ;
        $totalsFLY = $this->pitching->where('trajectory', '=', BattingTrajectory::FLY_BALL->value)->count();
        $totalsSwingMiss = $this->pitching->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count();
        $totalsFoul = $this->pitching->where('trajectory', '=', BattingTrajectory::FOUL->value)->count();

        $effectiveThrows = $totalsFoul+$totalsFLY+$totalsGB+$totalsSwingMiss+$totalsLD;

        return [
            'totals'=>$totals,
            'effective'=>$effectiveThrows,
            'GB'=>[
                'count'=>$totalsGB,
                'percent'=>round(Helper::caseDivide($totalsGB, $effectiveThrows) *100, 2)
            ],

            'LD'=>[
                'count'=>$totalsLD,
                'percent'=>round(Helper::caseDivide($totalsLD, $effectiveThrows) *100, 2)
            ],
            'FLY'=>[
                'count'=>$totalsFLY,
                'percent'=>round(Helper::caseDivide($totalsFLY, $effectiveThrows) *100, 2)
            ],
            'SM'=>[
                'count'=> $totalsSwingMiss,
                'percent'=>round(Helper::caseDivide($totalsSwingMiss, $effectiveThrows) *100, 2)
            ],
            'FOUL'=>[
                'count'=> $totalsFoul,
                'percent'=>round(Helper::caseDivide($totalsFoul, $effectiveThrows) *100, 2)
            ],

        ];

    }

    public function launchAngleAverageVelocityData()
    {
        $ranges = config('constants.toChartAngleCageRanges');
        $launchAngleTotals = ['totals' => $this->cage->count()];
        foreach ($ranges as $range => $limits) {
            $launchAngleTotals[$range] = round($this->cage->whereBetween('launch_angle', $limits)
                ->average('launch_angle_velocity') ?? 0, 0);
        }
        return $launchAngleTotals;
    }

    public function pitchThrowResult()
    {
        $data = $this->pitching;
        $totals = $data->count();
        $smfb = round(Helper::caseDivide($data
            ->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count(), $totals) * 100, 2);
        $takeFB = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::FAST_BALL->value)
            ->where('trajectory', '=', BattingTrajectory::TAKE->value)->count(), $totals) * 100, 2);
        $takeCH = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
            ->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count(), $totals) * 100, 2);
        $smCH = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::CHANGE_UP->value)
            ->where('trajectory', '=', BattingTrajectory::TAKE->value)->count(), $totals) * 100, 2);
        $smCB = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
            ->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count(), $totals) * 100, 2);
        $takeCB = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::CURVE_BALL->value)
            ->where('trajectory', '=', BattingTrajectory::TAKE->value)->count(), $totals) * 100, 2);
        $smSL = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
            ->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count(), $totals) * 100, 2);
        $takeSL = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::SLIDER->value)
            ->where('trajectory', '=', BattingTrajectory::TAKE->value)->count(), $totals) * 100, 2);
        $smOther = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::OTHER->value)
            ->where('trajectory', '=', BattingTrajectory::SWING_MISS->value)->count(), $totals) * 100, 2);
        $takeOther = round(Helper::caseDivide($data->where('type_throw', '=', PitchThrowTypes::OTHER->value)
            ->where('trajectory', '=', BattingTrajectory::TAKE->value)->count(), $totals) * 100, 2);
        return[
            'totals'=>$totals,
            'FB'=>[
                'SM'=> $smfb,
                'TAKE'=> $takeFB
            ],
            'CH'=>[
                'SM'=> $takeCH,
                'TAKE'=> $smCH
            ],
            'CB'=>[
                'SM'=> $smCB,
                'TAKE'=> $takeCB
            ],
            'SL'=>[
                'SM'=> $smSL,
                'TAKE'=> $takeSL
            ],
            'OTHER'=>[
                'SM'=> $smOther,
                'TAKE'=> $takeOther
            ]
        ];

    }

}
