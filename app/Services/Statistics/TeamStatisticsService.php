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
use App\Models\LongTossPractice;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\TeamsLiveAB;
use App\Models\WeightBallPractice;
use App\Services\ResultTrainingService;
use App\Utils\Helper;

final class TeamStatisticsService
{
    public $batting;
    public $liveAB;
    public $battingAB;
    public $pitching;
    public $pitchingAB;
    public $cage;
    public $longToss;
    public $weightBall;

    public function __construct(private $id)
    {
        $this->batting = BattingPracticeResult::where('team_id', '=', $this->id)->with('profile')->get();
        $this->pitching = BullpenPracticeResult::where('team_id', '=', $this->id)->get();
        $liveAbPractices = TeamsLiveAB::where('team_id', '=', $this->id)
            ->pluck('practice_id')
            ->unique()
            ->all();
        $this->cage = CagePracticeResult::where('team_id', '=', $this->id)->get();
        $this->liveAB = LiveABPracticeResult::whereIn('practice_id', $liveAbPractices)->get();

        // Long toss / weighted ball RESULT rows frequently have team_id = NULL (they
        // link to a Practice that carries the team) — querying by team_id alone came
        // back empty even when data existed. Use the SAME resolver the performance
        // overview uses (team_id → team practices → all team practices fallback), so
        // the charts see exactly the data the score tiles do.
        $playerIds = PlayerTeam::where('team_id', $this->id)->whereNotNull('user_id')->pluck('user_id')->all();
        $this->longToss = ResultTrainingService::getLongTossResultsLastSessions((string) $this->id, $playerIds, 10);
        $this->weightBall = ResultTrainingService::getWeightBallResultsLastSessions((string) $this->id, $playerIds, 10);
    }

    /**
     * Long toss "distance by throw" — team-average distance and hops at each throw
     * index (shows the fatigue curve). Powers the Long Toss line chart.
     */
    public function getLongTossCurve(): array
    {
        $rows = $this->longToss->filter(fn ($r) => is_numeric($r->distance) && (float) $r->distance > 0);
        if ($rows->isEmpty()) {
            return [];
        }
        return $rows->groupBy(fn ($r) => (int) ($r->sort ?? 0))
            ->map(fn ($group, $idx) => [
                'throw'    => (int) $idx + 1,
                'distance' => round($group->avg(fn ($r) => (float) $r->distance), 1),
                'hop'      => round($group->avg(fn ($r) => (float) ($r->hop ?? 0)), 1),
            ])
            ->sortBy('throw')
            ->values()
            ->toArray();
    }

    /**
     * Weighted-ball velocity curve — team average + top velocity per ball weight
     * (oz). Powers the Weighted Ball line chart.
     */
    public function getWeightedBallCurve(): array
    {
        $rows = $this->weightBall->filter(fn ($r) => is_numeric($r->velocity) && (float) $r->velocity > 0 && is_numeric($r->weight));
        if ($rows->isEmpty()) {
            return [];
        }
        return $rows->groupBy(fn ($r) => (int) $r->weight)
            ->map(fn ($group, $weight) => [
                'weight' => (int) $weight,
                'avg'    => round($group->avg(fn ($r) => (float) $r->velocity), 1),
                'top'    => round($group->max(fn ($r) => (float) $r->velocity), 1),
            ])
            ->sortBy('weight')
            ->values()
            ->toArray();
    }

    public function getBallsStrikeData(): array
    {
        $data = $this->batting;

        // A "swing" is any pitch where the batter made an attempt:
        // quality_of_contact in (MF, W, A, H) OR type_of_hit in (SM, GB, PF, FB, LD, F)
        $swingTypes = [
            BattingTrajectory::SWING_MISS->value,
            BattingTrajectory::GROUND_BALL->value,
            BattingTrajectory::POP_FLY->value,
            BattingTrajectory::FLY_BALL->value,
            BattingTrajectory::LINE_DRIVE->value,
            BattingTrajectory::FOUL->value,
        ];
        $contactQualities = ['MF', 'W', 'A', 'H'];

        $swings = $data->filter(function ($row) use ($swingTypes, $contactQualities) {
            return in_array($row->quality_of_contact, $contactQualities)
                || in_array($row->type_of_hit, $swingTypes);
        });

        $totalSwings = $swings->count();

        $strikesWithContact = $swings->where('zone', 'S')->count();
        $ballsWithContact   = $swings->where('zone', 'B')->count();

        return [
            'total_s_b' => $totalSwings,
            'strikes' => [
                'count'   => $strikesWithContact,
                'percent' => round(Helper::caseDivide($strikesWithContact, $totalSwings) * 100),
            ],
            'balls' => [
                'count'   => $ballsWithContact,
                'percent' => round(Helper::caseDivide($ballsWithContact, $totalSwings) * 100),
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
        $total = $this->pitching->count();
        $strikes = $this->pitching->where('is_strike', true)->count();
        return [
            'totals'         => $total,
            'strike_percent'  => $total > 0 ? round(($strikes / $total) * 100) : 0,
            'strike_count'    => $strikes,
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

    public function getContactSprayData(): array
    {
        $swingTypes = [
            BattingTrajectory::SWING_MISS->value,
            BattingTrajectory::GROUND_BALL->value,
            BattingTrajectory::POP_FLY->value,
            BattingTrajectory::FLY_BALL->value,
            BattingTrajectory::LINE_DRIVE->value,
            BattingTrajectory::FOUL->value,
        ];
        $contactQualities = ['MF', 'W', 'A', 'H'];

        // All swings (same definition as getBallsStrikeData)
        $swings = $this->batting->filter(function ($row) use ($swingTypes, $contactQualities) {
            return in_array($row->quality_of_contact, $contactQualities)
                || in_array($row->type_of_hit, $swingTypes);
        });

        $mapRow = fn($r) => [
            'point'      => (int)($r->field_mark ?? 0),
            'feature'    => $r->quality_of_contact ?? 'MF',
            'trajectory' => $r->type_of_hit ?? null,
            'velocity'   => (int)($r->velocity ?? 0), // exit velocity — powers the velocity spray field
            'player'     => $r->profile
                ? trim(($r->profile->first_name ?? '') . ' ' . ($r->profile->last_name ?? ''))
                : 'Unknown',
        ];

        $strikes = $swings->where('zone', 'S')->map($mapRow)->values()->toArray();
        $balls   = $swings->where('zone', 'B')->map($mapRow)->values()->toArray();

        return [
            'strikes' => $strikes,
            'balls'   => $balls,
        ];
    }

    /**
     * Cage spray — every cage swing that has a real spray angle, with its true
     * distance, exit velocity, launch angle and trajectory. Unlike batting (which
     * only taps a field grid), cage records real physics, so this powers a true
     * spray chart with trajectory lines.
     */
    public function getCageSprayData(): array
    {
        return $this->cage
            ->filter(fn ($r) => is_numeric($r->spray_angle) && (float) $r->spray_angle >= -90 && (float) $r->spray_angle <= 90)
            ->map(fn ($r) => [
                'spray_angle'     => (float) $r->spray_angle,
                'distance_travel' => is_numeric($r->distance_travel) ? (float) $r->distance_travel : 0,
                'launch_angle'    => is_numeric($r->launch_angle) ? (float) $r->launch_angle : null,
                'velocity'        => is_numeric($r->launch_angle_velocity) ? (float) $r->launch_angle_velocity : 0,
                'trajectory'      => $r->type_of_hit ?? null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Bullpen pitches — every team pitch that has a location (pitch_mark) and/or a
     * velocity (miles_per_hour), for the catcher's-view heat / velocity map. Carries
     * strike flag and pitch type so the panel can filter and show strike %.
     */
    public function getBullpenPitchData(): array
    {
        return $this->pitching
            ->filter(fn ($r) => ((int) ($r->pitch_mark ?? 0)) > 0
                || (is_numeric($r->miles_per_hour) && (float) $r->miles_per_hour > 0))
            ->map(fn ($r) => [
                'pitch_mark' => (int) ($r->pitch_mark ?? 0),
                'velocity'   => is_numeric($r->miles_per_hour) ? (float) $r->miles_per_hour : 0,
                'is_strike'  => (bool) $r->is_strike,
                'pitch_type' => $r->type_throw ?? $r->intended_pitch_type ?? null,
            ])
            ->values()
            ->toArray();
    }

}
