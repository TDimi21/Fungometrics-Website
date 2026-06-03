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
        $query = CagePracticeResult::with('profile')
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->whereIn('user_id', $players);

        if ($team !== '') {
            $query->where('team_id', '=', $team);
        }

        $direct = $query->get();
        if ($direct->count() > 0 || empty($players)) {
            return $direct;
        }

        // Player fallback: sessions assigned to player but result row may not carry user_id consistently
        $practiceIds = Practice::query()
            ->where('type', PracticeTypes::CAGE->value)
            ->where('user_id', $players[0])
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->pluck('id')
            ->all();

        if (empty($practiceIds)) {
            return $direct;
        }

        return CagePracticeResult::with('profile')
            ->whereIn('practice_id', $practiceIds)
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->get();

    }

    /**
     * @param string $team
     * @param array $players
     * @param array $dates
     */
    public static function getBattingResults(string $team, array $players, array $dates)
    {
        $battingQuery = BattingPracticeResult::query()
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->whereIn('batter_id', $players);

        if ($team !== '') {
            $battingQuery->where('is_in_match', false);
        }

        if ($team !== '') {
            $battingQuery->where('team_id', $team);
        }

        $batting = $battingQuery->get();

        if ($team === '' && $batting->count() === 0 && !empty($players)) {
            $practiceIds = Practice::query()
                ->where('type', PracticeTypes::BATTING->value)
                ->where('user_id', $players[0])
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->pluck('id')
                ->all();

            if (!empty($practiceIds)) {
                $batting = BattingPracticeResult::query()
                    ->whereIn('practice_id', $practiceIds)
                    ->whereDate('created_at', '>=', $dates[0])
                    ->whereDate('created_at', '<=', $dates[1])
                    ->get();

                if ($team !== '') {
                    $batting = $batting->where('is_in_match', false)->values();
                }
            }
        }

        // Also include Scripted BP swings for the same team / date range
        $scriptedPracticeIds = [];
        if ($team !== '') {
            $scriptedPracticeIds = Practice::where('team_id', $team)
                ->where('type', PracticeTypes::BATTING->value)
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->pluck('id')
                ->all();
        }

        $scriptedSwings = collect();
        if ($team === '') {
            $rawSwings = ScriptedBpSwing::whereIn('batter_id', $players)
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->get();
            $scriptedSwings = self::normalizeScriptedSwings($rawSwings);
        } elseif (!empty($scriptedPracticeIds)) {
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
        $baseQuery = BullpenPracticeResult::query()
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1])
            ->whereIn('pitcher_id', $players);

        if ($team !== '') {
            $baseQuery->where('is_in_match', false);
        }

        if ($team === '') {
            $direct = $baseQuery->get();

            if ($direct->count() > 0 || empty($players)) {
                return $direct;
            }

            $practiceIds = Practice::query()
                ->where('type', PracticeTypes::BULLPEN->value)
                ->where('user_id', $players[0])
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->pluck('id')
                ->all();

            if (empty($practiceIds)) {
                return $direct;
            }

            return BullpenPracticeResult::query()
                ->whereIn('practice_id', $practiceIds)
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->get();
        }

        $direct = (clone $baseQuery)
            ->where('team_id', $team)
            ->get();

        if ($direct->count() > 0) {
            return $direct;
        }

        $teamPracticeIds = Practice::where('team_id', $team)
            ->where('type', PracticeTypes::BULLPEN->value)
            ->pluck('id')
            ->all();

        if (empty($teamPracticeIds)) {
            return collect();
        }

        return $baseQuery
            ->whereIn('practice_id', $teamPracticeIds)
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
        $baseQuery = LongTossPractice::query()
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1]);

        if (!empty($players)) {
            $baseQuery->whereIn('user_id', $players);
        }

        if ($team === '') {
            $direct = $baseQuery->get();

            if ($direct->count() > 0 || empty($players)) {
                return $direct;
            }

            $practiceIds = Practice::query()
                ->where('type', PracticeTypes::TRAINING->value)
                ->where('user_id', $players[0])
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->pluck('id')
                ->all();

            if (empty($practiceIds)) {
                return $direct;
            }

            return LongTossPractice::query()
                ->whereIn('practice_id', $practiceIds)
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->get();
        }

        // Primary path: rows where team_id is populated
        $direct = (clone $baseQuery)
            ->where('team_id', $team)
            ->get();

        if ($direct->count() > 0) {
            return $direct;
        }

        // Fallback path: many historical rows have team_id = null
        $teamPracticeIds = Practice::where('team_id', $team)
            ->where('type', PracticeTypes::TRAINING->value)
            ->pluck('id')
            ->all();

        if (empty($teamPracticeIds)) {
            return collect();
        }

        return $baseQuery
            ->whereIn('practice_id', $teamPracticeIds)
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
        $baseQuery = WeightBallPractice::query()
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1]);

        if (!empty($players)) {
            $baseQuery->whereIn('user_id', $players);
        }

        if ($team === '') {
            $direct = $baseQuery->get();

            if ($direct->count() > 0 || empty($players)) {
                return $direct;
            }

            $practiceIds = Practice::query()
                ->where('type', PracticeTypes::TRAINING->value)
                ->where('user_id', $players[0])
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->pluck('id')
                ->all();

            if (empty($practiceIds)) {
                return $direct;
            }

            return WeightBallPractice::query()
                ->whereIn('practice_id', $practiceIds)
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->get();
        }

        // Primary path: rows where team_id is populated
        $direct = (clone $baseQuery)
            ->where('team_id', $team)
            ->get();

        if ($direct->count() > 0) {
            return $direct;
        }

        // Fallback path: many historical rows have team_id = null
        $teamPracticeIds = Practice::where('team_id', $team)
            ->where('type', PracticeTypes::TRAINING->value)
            ->pluck('id')
            ->all();

        if (empty($teamPracticeIds)) {
            return collect();
        }

        return $baseQuery
            ->whereIn('practice_id', $teamPracticeIds)
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
        $baseQuery = ExitVelocityPractice::query()
            ->whereDate('created_at', '>=', $dates[0])
            ->whereDate('created_at', '<=', $dates[1]);

        if (!empty($players)) {
            $baseQuery->whereIn('user_id', $players);
        }

        if ($team === '') {
            $direct = $baseQuery->get();

            if ($direct->count() > 0 || empty($players)) {
                return $direct;
            }

            $practiceIds = Practice::query()
                ->where('type', PracticeTypes::TRAINING->value)
                ->where('user_id', $players[0])
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->pluck('id')
                ->all();

            if (empty($practiceIds)) {
                return $direct;
            }

            return ExitVelocityPractice::query()
                ->whereIn('practice_id', $practiceIds)
                ->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1])
                ->get();
        }

        // Primary path: rows where team_id is populated
        $direct = (clone $baseQuery)
            ->where('team_id', $team)
            ->get();

        if ($direct->count() > 0) {
            return $direct;
        }

        // Fallback path: many historical rows have team_id = null
        $teamPracticeIds = Practice::where('team_id', $team)
            ->where('type', PracticeTypes::TRAINING->value)
            ->pluck('id')
            ->all();

        if (empty($teamPracticeIds)) {
            return collect();
        }

        return $baseQuery
            ->whereIn('practice_id', $teamPracticeIds)
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

        // Fallback: include team batting sessions even when batter_id does not match claimed players
        if (empty($practiceIds)) {
            $practiceIds = BattingPracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
                ->where('team_id', $team)
                ->where('is_in_match', false)
                ->groupBy('practice_id')
                ->orderByDesc('latest')
                ->limit($limit)
                ->pluck('practice_id')
                ->all();
        }

        // Fallback: many historical rows have team_id = null or a different team_id —
        // resolve via the practices table (same pattern as cage/long-toss methods)
        if (empty($practiceIds)) {
            $teamPracticeIds = Practice::where('team_id', $team)
                ->where('type', PracticeTypes::BATTING->value)
                ->pluck('id')
                ->all();

            if (!empty($teamPracticeIds)) {
                $practiceIds = BattingPracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
                    ->whereIn('practice_id', $teamPracticeIds)
                    ->where('is_in_match', false)
                    ->groupBy('practice_id')
                    ->orderByDesc('latest')
                    ->limit($limit)
                    ->pluck('practice_id')
                    ->all();
            }
        }

        $batting = collect();
        if (!empty($practiceIds)) {
            $battingQuery = BattingPracticeResult::whereIn('practice_id', $practiceIds)
                ->where('is_in_match', false);

            if (!empty($players)) {
                $battingQuery->whereIn('batter_id', $players);
            }

            $batting = $battingQuery->get();

            // If claimed-player filter returned nothing, retry without it
            if ($batting->isEmpty() && !empty($players)) {
                $batting = BattingPracticeResult::whereIn('practice_id', $practiceIds)
                    ->where('is_in_match', false)
                    ->get();
            }
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
            $scriptedQuery = ScriptedBpSwing::whereIn('practice_id', $scriptedPracticeIds);
            if (!empty($players)) {
                $scriptedQuery->whereIn('batter_id', $players);
            }

            $rawSwings = $scriptedQuery->get();

            if ($rawSwings->isEmpty() && !empty($players)) {
                $rawSwings = ScriptedBpSwing::whereIn('practice_id', $scriptedPracticeIds)->get();
            }

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
     * team_id is often NULL on result rows — resolve via the practices table as fallback.
     */
    public static function getCageResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        // Primary path: rows where team_id is populated
        $practiceIds = CagePracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        // Fallback: include team sessions even when user_id values are not claimed players
        if (empty($practiceIds)) {
            $practiceIds = CagePracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
                ->where('team_id', $team)
                ->groupBy('practice_id')
                ->orderByDesc('latest')
                ->limit($limit)
                ->pluck('practice_id')
                ->all();
        }

        // Fallback: many historical cage rows have team_id = null — resolve via practices table
        if (empty($practiceIds)) {
            $teamPracticeIds = Practice::where('team_id', $team)
                ->where('type', PracticeTypes::CAGE->value)
                ->pluck('id')
                ->all();

            if (!empty($teamPracticeIds)) {
                $practiceIds = CagePracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
                    ->whereIn('practice_id', $teamPracticeIds)
                    ->whereIn('user_id', $players)
                    ->groupBy('practice_id')
                    ->orderByDesc('latest')
                    ->limit($limit)
                    ->pluck('practice_id')
                    ->all();

                // Fallback: team practices may contain historical/unclaimed user ids
                if (empty($practiceIds)) {
                    $practiceIds = CagePracticeResult::selectRaw('practice_id, MAX(created_at) as latest')
                        ->whereIn('practice_id', $teamPracticeIds)
                        ->groupBy('practice_id')
                        ->orderByDesc('latest')
                        ->limit($limit)
                        ->pluck('practice_id')
                        ->all();
                }
            }
        }

        if (empty($practiceIds)) {
            return collect();
        }

        return CagePracticeResult::with('profile')
            ->whereIn('practice_id', $practiceIds)
            ->get();
    }

    /**
     * Returns exit velocity results from the last $limit distinct sessions.
     * team_id is often NULL on result rows — resolve via the practices table.
     */
    public static function getExitVelocityResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        // Primary path: rows where team_id is populated (mirrors getExitVelocityResults)
        $practiceIds = ExitVelocityPractice::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        if (empty($practiceIds)) {
            $practiceIds = ExitVelocityPractice::selectRaw('practice_id, MAX(created_at) as latest')
                ->where('team_id', $team)
                ->groupBy('practice_id')
                ->orderByDesc('latest')
                ->limit($limit)
                ->pluck('practice_id')
                ->all();
        }

        // Fallback: result rows have team_id = null — resolve via practices table
        if (empty($practiceIds)) {
            $teamPracticeIds = Practice::where('team_id', $team)
                ->pluck('id')
                ->all();

            if (empty($teamPracticeIds)) {
                return collect();
            }

            $practiceIds = ExitVelocityPractice::selectRaw('practice_id, MAX(created_at) as latest')
                ->whereIn('practice_id', $teamPracticeIds)
                ->whereIn('user_id', $players)
                ->groupBy('practice_id')
                ->orderByDesc('latest')
                ->limit($limit)
                ->pluck('practice_id')
                ->all();

            if (empty($practiceIds)) {
                $practiceIds = ExitVelocityPractice::selectRaw('practice_id, MAX(created_at) as latest')
                    ->whereIn('practice_id', $teamPracticeIds)
                    ->groupBy('practice_id')
                    ->orderByDesc('latest')
                    ->limit($limit)
                    ->pluck('practice_id')
                    ->all();
            }
        }

        if (empty($practiceIds)) {
            return collect();
        }

        return ExitVelocityPractice::whereIn('practice_id', $practiceIds)->get();
    }

    /**
     * Returns long toss results from the last $limit distinct sessions.
     * team_id is often NULL on result rows — resolve via the practices table.
     */
    public static function getLongTossResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        // Primary path: rows where team_id is populated (mirrors getLongTossResults)
        $practiceIds = LongTossPractice::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        if (empty($practiceIds)) {
            $practiceIds = LongTossPractice::selectRaw('practice_id, MAX(created_at) as latest')
                ->where('team_id', $team)
                ->groupBy('practice_id')
                ->orderByDesc('latest')
                ->limit($limit)
                ->pluck('practice_id')
                ->all();
        }

        // Fallback: result rows have team_id = null — resolve via practices table
        if (empty($practiceIds)) {
            $teamPracticeIds = Practice::where('team_id', $team)
                ->pluck('id')
                ->all();

            if (empty($teamPracticeIds)) {
                return collect();
            }

            $practiceIds = LongTossPractice::selectRaw('practice_id, MAX(created_at) as latest')
                ->whereIn('practice_id', $teamPracticeIds)
                ->whereIn('user_id', $players)
                ->groupBy('practice_id')
                ->orderByDesc('latest')
                ->limit($limit)
                ->pluck('practice_id')
                ->all();

            if (empty($practiceIds)) {
                $practiceIds = LongTossPractice::selectRaw('practice_id, MAX(created_at) as latest')
                    ->whereIn('practice_id', $teamPracticeIds)
                    ->groupBy('practice_id')
                    ->orderByDesc('latest')
                    ->limit($limit)
                    ->pluck('practice_id')
                    ->all();
            }
        }

        if (empty($practiceIds)) {
            return collect();
        }

        return LongTossPractice::whereIn('practice_id', $practiceIds)->get();
    }

    /**
     * Returns weight ball results from the last $limit distinct sessions.
     * Some rows have team_id = null — resolve via the practices table as fallback.
     */
    public static function getWeightBallResultsLastSessions(string $team, array $players, int $limit = 10)
    {
        // First try direct team_id match (rows that have it populated)
        $practiceIds = WeightBallPractice::selectRaw('practice_id, MAX(created_at) as latest')
            ->where('team_id', $team)
            ->whereIn('user_id', $players)
            ->groupBy('practice_id')
            ->orderByDesc('latest')
            ->limit($limit)
            ->pluck('practice_id')
            ->all();

        // Fallback: resolve team via practices table for rows with team_id = null
        if (empty($practiceIds)) {
            $teamPracticeIds = Practice::where('team_id', $team)
                ->pluck('id')
                ->all();

            if (empty($teamPracticeIds)) {
                return collect();
            }

            $practiceIds = WeightBallPractice::selectRaw('practice_id, MAX(created_at) as latest')
                ->whereIn('practice_id', $teamPracticeIds)
                ->whereIn('user_id', $players)
                ->groupBy('practice_id')
                ->orderByDesc('latest')
                ->limit($limit)
                ->pluck('practice_id')
                ->all();

            // Fallback: include team practices regardless of claimed user_id
            if (empty($practiceIds)) {
                $practiceIds = WeightBallPractice::selectRaw('practice_id, MAX(created_at) as latest')
                    ->whereIn('practice_id', $teamPracticeIds)
                    ->groupBy('practice_id')
                    ->orderByDesc('latest')
                    ->limit($limit)
                    ->pluck('practice_id')
                    ->all();
            }

            if (empty($practiceIds)) {
                return collect();
            }
        }

        return WeightBallPractice::whereIn('practice_id', $practiceIds)->get();
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
