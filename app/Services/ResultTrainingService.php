<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\LiveABPracticeResult;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\TeamsLiveAB;
use App\Models\WeightBallPractice;

final class ResultTrainingService
{
    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     * @return array
     */
    public static function getLiveABResults(
        array $players,
        string $team = null,
        array $dates = null,
        $practiceId=null
    ): array {

        if(null === $practiceId && null !== $dates) {
            $practicesIds = TeamsLiveAB::where('team_id', '=', $team)
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])->pluck('practice_id')->unique()->all();
        } else {
            $practicesIds = [$practiceId];
        }


        //$liveAbResults = LiveABPracticeResult::whereIn('practice_id', $practicesIds)->pluck('id')->all();

        $batters = BattingPracticeResult::with('livePractice')
            ->whereIn('practice_id', $practicesIds)
            ->whereIn('batter_id', $players)
            ->where('is_in_match', true)->get();
        $pitchers = BullpenPracticeResult::with('livePractice')
            ->whereIn('practice_id', $practicesIds)
            ->whereIn('pitcher_id', $players)
            ->where('is_in_match', true)->get();

        return [
            'batters' => $batters,
            'pitchers' => $pitchers,
        ];
    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     */
    public static function getCageResults(string $team, array $players, array $dates)
    {

        return CagePracticeResult::with('profile')
            ->where('team_id', '=', $team)
            ->whereDate('updated_at', '>=', $dates[0])
            ->whereDate('updated_at', '<=', $dates[1])
            ->whereIn('user_id', $players)
            ->get();

    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     */
    public static function getBattingResults(string $team, array $players, array $dates)
    {

        return BattingPracticeResult::where('team_id', $team)
            ->whereDate('updated_at', '>=', $dates[0])
            ->whereDate('updated_at', '<=', $dates[1])
            ->where('is_in_match', false)
            ->whereIn('batter_id', $players)
            ->get();
    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     */
    public static function getBullpenResults(string $team, array $players, array $dates)
    {
        return BullpenPracticeResult::where('team_id', $team)
            ->whereDate('updated_at', '>=', $dates[0])
            ->whereDate('updated_at', '<=', $dates[1])
            ->where('is_in_match', false)
            ->whereIn('pitcher_id', $players)
            ->get();

    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     * @return array
     */
    public static function getLongTossResults(string $team, array $players, array $dates)
    {

        return LongTossPractice::where('team_id', $team)
            ->whereDate('updated_at', '>=', $dates[0])
            ->whereDate('updated_at', '<=', $dates[1])
            ->whereIn('user_id', $players)
            ->get();
    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     * @return array
     */
    public static function getWeightBallResults(string $team, array $players, array $dates)
    {
        return WeightBallPractice::where('team_id', $team)
            ->whereDate('updated_at', '>=', $dates[0])
            ->whereDate('updated_at', '<=', $dates[1])
            ->whereIn('user_id', $players)
            ->get();
    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     * @return array
     */
    public static function getExitVelocityResults(string $team, array $players, array $dates)
    {
        return ExitVelocityPractice::where('team_id', $team)
            ->whereDate('updated_at', '>=', $dates[0])
            ->whereDate('updated_at', '<=', $dates[1])
            ->whereIn('user_id', $players)
            ->get();
    }
}
