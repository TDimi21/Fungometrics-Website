<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PracticeTypes;
use App\Models\ExitVelocityPractice;
use App\Models\LiveABPracticeResult;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\ScriptedBpSwing;
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
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
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
        $batting = BattingPracticeResult::where('team_id', $team)
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->where('is_in_match', false)
            ->whereIn('batter_id', $players)
            ->get();

        // Also include Scripted BP swings for the same team / date range
        $scriptedPracticeIds = Practice::where('team_id', $team)
            ->where('type', PracticeTypes::BATTING->value)
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->pluck('id')
            ->all();

        $scriptedSwings = collect();
        if (!empty($scriptedPracticeIds)) {
            $rawSwings = ScriptedBpSwing::whereIn('practice_id', $scriptedPracticeIds)
                ->whereIn('batter_id', $players)
                ->get();
            $scriptedSwings = self::normalizeScriptedSwings($rawSwings);
        }

        return $batting->concat($scriptedSwings);
    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     */
    public static function getBullpenResults(string $team, array $players, array $dates)
    {
        return BullpenPracticeResult::where('team_id', $team)
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
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
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
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
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
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
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->whereIn('user_id', $players)
            ->get();
    }

    // ── Last-N-sessions helpers (used by Performance Overview) ───────────────

    /**
     * Returns batting practice results from the last $limit distinct sessions.
     */
    public static function getBattingResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        $practiceIds = BattingPracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->where('is_in_match', false)
            ->whereIn('batter_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        $batting = collect();
        if (!empty($practiceIds)) {
            $batting = BattingPracticeResult::where('team_id', $team)
                ->where('is_in_match', false)
                ->whereIn('batter_id', $players)
                ->whereIn('practice_id', $practiceIds)
                ->get();
        }

        // Also include Scripted BP swings from the last $limit scripted BP sessions for this team
        $scriptedPracticeIds = Practice::where('team_id', $team)
            ->where('type', PracticeTypes::BATTING->value)
            ->whereHas('scriptedBpSwings')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $scriptedSwings = collect();
        if (!empty($scriptedPracticeIds)) {
            $rawSwings = ScriptedBpSwing::whereIn('practice_id', $scriptedPracticeIds)
                ->whereIn('batter_id', $players)
                ->get();
            $scriptedSwings = self::normalizeScriptedSwings($rawSwings);
        }

        return $batting->concat($scriptedSwings);
    }

    /**
     * Returns bullpen practice results from the last $limit distinct sessions.
     */
    public static function getBullpenResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        // Primary lookup: by pitcher_id (claimed-player roster)
        $practiceIds = BullpenPracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('is_in_match', false)
            ->whereIn('pitcher_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        if (!empty($practiceIds)) {
            return BullpenPracticeResult::where('is_in_match', false)
                ->whereIn('pitcher_id', $players)
                ->whereIn('practice_id', $practiceIds)
                ->get();
        }

        // Fallback: look up by team_id in case pitches were recorded before players
        // claimed their accounts (pitcher_id not yet in PlayerTeam).
        $practiceIdsByTeam = BullpenPracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('is_in_match', false)
            ->where('team_id', $team)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        if (empty($practiceIdsByTeam)) {
            return collect();
        }

        return BullpenPracticeResult::where('is_in_match', false)
            ->where('team_id', $team)
            ->whereIn('practice_id', $practiceIdsByTeam)
            ->get();
    }

    /**
     * Returns cage practice results from the last $limit distinct sessions.
     */
    public static function getCageResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        $practiceIds = CagePracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        if (empty($practiceIds)) {
            return collect();
        }

        return CagePracticeResult::with('profile')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->whereIn('practice_id', $practiceIds)
            ->get();
    }

    /**
     * Returns exit velocity results from the last $limit distinct sessions.
     */
    public static function getExitVelocityResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        $practiceIds = ExitVelocityPractice::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        if (empty($practiceIds)) {
            return collect();
        }

        return ExitVelocityPractice::where('team_id', $team)
            ->whereIn('user_id', $players)
            ->whereIn('practice_id', $practiceIds)
            ->get();
    }

    /**
     * Returns long toss results from the last $limit distinct sessions.
     */
    public static function getLongTossResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        $practiceIds = LongTossPractice::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        if (empty($practiceIds)) {
            return collect();
        }

        return LongTossPractice::where('team_id', $team)
            ->whereIn('user_id', $players)
            ->whereIn('practice_id', $practiceIds)
            ->get();
    }

    /**
     * Normalizes ScriptedBpSwing Eloquent rows into plain objects whose property
     * names match BattingPracticeResult so BattingStatisticsService can process
     * both batting and scripted BP swings in a single pass.
     *
     * Field mapping:
     *   trajectory    → type_of_hit  (camelCase → BattingTrajectory short value)
     *   contact_type  → quality_of_contact
     *   exit_velocity → velocity
     *   direction     → field_direction
     */
    private static function normalizeScriptedSwings($swings): \Illuminate\Support\Collection
    {
        // Map scripted BP trajectory names to BattingTrajectory enum values
        $trajectoryMap = [
            'LineDrive'  => 'LD',
            'FlyBall'    => 'FB',
            'GroundBall' => 'GB',
            'PopUp'      => 'PF',
            'Foul'       => 'F',
            'Miss'       => 'SM',
        ];

        return $swings->map(function (ScriptedBpSwing $swing) use ($trajectoryMap): object {
            return (object) [
                'type_of_hit'        => $trajectoryMap[$swing->trajectory ?? ''] ?? $swing->trajectory,
                'quality_of_contact' => $swing->contact_type,
                'velocity'           => $swing->exit_velocity,
                'zone'               => null,
                'field_direction'    => $swing->direction,
                'batter_id'          => $swing->batter_id,
                'practice_id'        => $swing->practice_id,
                'is_scripted'        => true,
            ];
        });
    }
}
