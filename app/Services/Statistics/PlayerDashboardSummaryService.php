<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\WeightBallPractice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the single payload behind GET player/dashboard-summary: per-type
 * session counts, the stat breakdowns the player home dashboard renders, and
 * the most recent sessions. The math mirrors the per-session report pages so
 * the dashboard always agrees with them.
 */
final class PlayerDashboardSummaryService
{
    public const CACHE_TTL_SECONDS = 120;

    public const RECENT_SESSIONS_LIMIT = 8;

    // A self-created practice with no recorded balls still shows in "recent"
    // for this long, so a player who just started a session sees it listed.
    private const FRESH_SESSION_SECONDS = 3600;

    // Strike-zone bounds on the 60x60 location grid, where a mark encodes
    // column and row as mark = (col - 1) * 60 + row.
    private const ZONE_COL_MIN = 19;
    private const ZONE_COL_MAX = 41;
    private const ZONE_ROW_MIN = 18;
    private const ZONE_ROW_MAX = 43;

    public static function cacheKey(string $userId): string
    {
        return "player-dashboard-summary:{$userId}";
    }

    public function build(string $userId): array
    {
        $lineupIds = $this->lineupPracticeIds($userId);
        $battingLineupIds = $this->lineupPracticeIds($userId, 'is_batting');
        $pitchingLineupIds = $this->lineupPracticeIds($userId, 'is_pitching');

        $battingPractices = $this->participatedPractices(
            $userId,
            PracticeTypes::BATTING->value,
            BattingPracticeResult::query()->where('batter_id', $userId)->where('is_in_match', false)->pluck('practice_id')->unique(),
            $battingLineupIds
        );
        $bullpenPractices = $this->participatedPractices(
            $userId,
            PracticeTypes::BULLPEN->value,
            BullpenPracticeResult::query()->where('pitcher_id', $userId)->pluck('practice_id')->unique(),
            $pitchingLineupIds
        );
        $cagePractices = $this->participatedPractices(
            $userId,
            PracticeTypes::CAGE->value,
            CagePracticeResult::query()->where('user_id', $userId)->pluck('practice_id')->unique(),
            $lineupIds
        );
        $trainingPractices = $this->participatedPractices(
            $userId,
            PracticeTypes::TRAINING->value,
            LongTossPractice::query()->where('user_id', $userId)->pluck('practice_id')
                ->merge(WeightBallPractice::query()->where('user_id', $userId)->pluck('practice_id'))
                ->merge(ExitVelocityPractice::query()->where('user_id', $userId)->pluck('practice_id'))
                ->unique(),
            $lineupIds
        );
        $liveabPractices = Practice::query()
            ->where('type', PracticeTypes::LIVE_AB->value)
            ->where('user_id', $userId)
            ->get();

        $battingRows = BattingPracticeResult::query()
            ->where('batter_id', $userId)
            ->where('is_in_match', false)
            ->whereIn('practice_id', $battingPractices->pluck('id')->all())
            ->orderBy('created_at')
            ->orderBy('sort')
            ->get();
        $bullpenRows = BullpenPracticeResult::query()
            ->where('pitcher_id', $userId)
            ->where('is_in_match', false)
            ->whereIn('practice_id', $bullpenPractices->pluck('id')->all())
            ->orderBy('created_at')
            ->orderBy('sort')
            ->get();
        $cageRows = CagePracticeResult::query()
            ->where('user_id', $userId)
            ->whereIn('practice_id', $cagePractices->pluck('id')->all())
            ->get();
        $trainingPracticeIds = $trainingPractices->pluck('id')->all();
        $weightBallRows = WeightBallPractice::query()
            ->where('user_id', $userId)
            ->whereIn('practice_id', $trainingPracticeIds)
            ->get();
        $exitVelocityRows = ExitVelocityPractice::query()
            ->where('user_id', $userId)
            ->whereIn('practice_id', $trainingPracticeIds)
            ->get();
        $longTossRows = LongTossPractice::query()
            ->where('user_id', $userId)
            ->whereIn('practice_id', $trainingPracticeIds)
            ->get();

        return [
            'counts' => [
                'batting' => $battingPractices->count(),
                'bullpen' => $bullpenPractices->count(),
                'cage' => $cagePractices->count(),
                'training' => $trainingPractices->count(),
                'weighted' => $trainingPractices->where('modes', PracticeModes::WEIGHT_BALL->value)->count(),
                'exitVel' => $trainingPractices->where('modes', PracticeModes::EXIT_VELOCITY->value)->count(),
                'longToss' => $trainingPractices->where('modes', PracticeModes::LONG_TOSS->value)->count(),
            ],
            'breakdowns' => [
                'batting' => $this->battingBreakdown($battingRows),
                'bullpen' => $this->bullpenBreakdown($bullpenRows),
                'cage' => $this->cageBreakdown($cageRows),
                'weighted' => $this->weightedBreakdown($weightBallRows),
                'exitVel' => $this->exitVelocityBreakdown($exitVelocityRows),
                'longToss' => $this->longTossBreakdown($longTossRows),
            ],
            'recent_sessions' => $this->recentSessions(
                $userId,
                $battingPractices->concat($bullpenPractices)->concat($cagePractices)->concat($trainingPractices)->concat($liveabPractices),
                $battingRows->countBy('practice_id')
                    ->union($bullpenRows->countBy('practice_id'))
                    ->union($cageRows->countBy('practice_id'))
                    ->union(
                        $weightBallRows->countBy('practice_id')
                            ->union($exitVelocityRows->countBy('practice_id'))
                            ->union($longTossRows->countBy('practice_id'))
                    )
            ),
        ];
    }

    private function lineupPracticeIds(string $userId, ?string $roleColumn = null): Collection
    {
        return PracticeLineUp::query()
            ->where('user_id', $userId)
            ->when($roleColumn, fn ($query) => $query->where($roleColumn, true))
            ->pluck('practice_id')
            ->unique();
    }

    private function participatedPractices(string $userId, string $type, Collection $resultPracticeIds, Collection $lineupPracticeIds): Collection
    {
        return Practice::query()
            ->where('type', $type)
            ->where(function ($query) use ($userId, $resultPracticeIds, $lineupPracticeIds): void {
                $query->where('user_id', $userId)
                    ->orWhereIn('id', $resultPracticeIds->all())
                    ->orWhereIn('id', $lineupPracticeIds->all());
            })
            ->get();
    }

    private function battingBreakdown(Collection $rows): array
    {
        $swings = $rows->map(function ($row): array {
            $velocity = $this->positiveNumber($row->velocity);
            if (null !== $velocity && ($velocity < 10 || $velocity > 125)) {
                $velocity = null;
            }
            $quality = strtoupper(trim((string) ($row->quality_of_contact ?? '')));
            $typeOfHit = strtoupper(trim((string) ($row->type_of_hit ?? '')));

            return [
                'ev' => $velocity,
                'qoc' => $quality,
                'toh' => $typeOfHit,
                'dir' => strtoupper(trim((string) ($row->field_direction ?? ''))),
                'pitchMark' => (int) ($row->pitch_mark ?? 0),
                'isMiss' => in_array($quality, ['MF', 'F'], true) || 'SM' === $typeOfHit,
            ];
        })->values();

        $total = $swings->count();
        $evValues = $swings->pluck('ev')->filter(fn ($value) => null !== $value)->values();
        $hardCount = $evValues->filter(fn ($value) => $value >= 90)->count();
        $missCount = $swings->where('isMiss', true)->count();

        $sprayBalls = $swings->filter(fn ($swing) => '' !== $swing['dir'] && ! $swing['isMiss'])->values();
        $contactBalls = $swings->where('isMiss', false)->values();
        $trajectoryCounts = [
            'GB' => $contactBalls->where('toh', 'GB')->count(),
            'LD' => $contactBalls->where('toh', 'LD')->count(),
            'FB' => $contactBalls->where('toh', 'FB')->count(),
            'PF' => $contactBalls->where('toh', 'PF')->count(),
        ];
        $trajectoryTotal = array_sum($trajectoryCounts);

        $avgEv = $this->fmt($this->avg($evValues->all()));
        $missPct = $this->pct($missCount, $total);
        $damageScore = null;
        if ($total > 0) {
            $evScore = null !== $avgEv ? (min($avgEv, 110) / 110) * 100 : 0;
            $hardLineDriveRate = (($hardCount + $trajectoryCounts['LD']) / $total) * 100;
            $missAdjusted = 100 - (float) ($missPct ?? 0);
            $damageScore = $this->fmt($evScore * 0.4 + $hardLineDriveRate * 0.35 + $missAdjusted * 0.25);
        }

        $zonePerf = $this->battingZonePerformance($swings);
        $competitiveCount = $swings->filter(
            fn ($swing) => 'H' === $swing['qoc'] || ('A' === $swing['qoc'] && in_array($swing['toh'], ['LD', 'FB'], true))
        )->count();

        return [
            'swings' => $total,
            'maxEV' => $this->fmt($this->max($evValues->all())),
            'avgEV' => $avgEv,
            'hardPct' => $this->pct($hardCount, $evValues->count()),
            'missPct' => $missPct,
            'sprayTotal' => $sprayBalls->count(),
            'lfPct' => $this->pct($sprayBalls->where('dir', 'LF')->count(), $sprayBalls->count()),
            'cfPct' => $this->pct($sprayBalls->where('dir', 'CF')->count(), $sprayBalls->count()),
            'rfPct' => $this->pct($sprayBalls->where('dir', 'RF')->count(), $sprayBalls->count()),
            'gbPct' => $this->pct($trajectoryCounts['GB'], $trajectoryTotal),
            'ldPct' => $this->pct($trajectoryCounts['LD'], $trajectoryTotal),
            'fbPct' => $this->pct($trajectoryCounts['FB'], $trajectoryTotal),
            'pfPct' => $this->pct($trajectoryCounts['PF'], $trajectoryTotal),
            'trajTotal' => $trajectoryTotal,
            'damageScore' => $damageScore,
            'zonePerf' => $zonePerf,
            'compPct' => $this->pct($competitiveCount, $total),
            'consistency' => $this->battingConsistency($swings),
        ];
    }

    private function battingZonePerformance(Collection $swings): ?array
    {
        $pitchBalls = $swings->filter(
            fn ($swing) => $swing['pitchMark'] > 0 && in_array($swing['qoc'], ['H', 'A', 'W'], true)
        )->values();
        if ($pitchBalls->count() < 5) {
            return null;
        }

        $counters = ['upperH' => 0, 'lowerH' => 0, 'innerH' => 0, 'outerH' => 0, 'upperN' => 0, 'lowerN' => 0, 'innerN' => 0, 'outerN' => 0];
        foreach ($pitchBalls as $swing) {
            [$col, $row] = $this->markToColRow($swing['pitchMark']);
            $isHard = 'H' === $swing['qoc'];
            if ($row < 30) {
                $counters['upperN']++;
                $isHard && $counters['upperH']++;
            } else {
                $counters['lowerN']++;
                $isHard && $counters['lowerH']++;
            }
            if ($col > 30) {
                $counters['innerN']++;
                $isHard && $counters['innerH']++;
            } else {
                $counters['outerN']++;
                $isHard && $counters['outerH']++;
            }
        }

        return [
            'upperHardPct' => $this->pct($counters['upperH'], $counters['upperN']),
            'lowerHardPct' => $this->pct($counters['lowerH'], $counters['lowerN']),
            'innerHardPct' => $this->pct($counters['innerH'], $counters['innerN']),
            'outerHardPct' => $this->pct($counters['outerH'], $counters['outerN']),
        ];
    }

    private function battingConsistency(Collection $swings): ?array
    {
        if ($swings->count() < 20) {
            return null;
        }

        $firstTen = $swings->slice(0, 10)->values();
        $lastTen = $swings->slice(-10)->values();
        $firstAvg = $this->avg($firstTen->pluck('ev')->filter(fn ($value) => null !== $value)->all());
        $lastAvg = $this->avg($lastTen->pluck('ev')->filter(fn ($value) => null !== $value)->all());

        return [
            'hardDrop' => $firstTen->where('qoc', 'H')->count() - $lastTen->where('qoc', 'H')->count(),
            'missDiff' => $lastTen->where('isMiss', true)->count() - $firstTen->where('isMiss', true)->count(),
            'evDrop' => (null !== $firstAvg && null !== $lastAvg) ? $this->fmt($firstAvg - $lastAvg) : null,
        ];
    }

    private function bullpenBreakdown(Collection $rows): array
    {
        $pitches = $rows->map(function ($row): array {
            $locationMark = (int) ($row->pitch_mark ?? 0);
            $strike = (bool) $row->is_strike;
            if (! $strike && $locationMark > 0) {
                $strike = $this->isStrikeZoneMark($locationMark);
            }

            return [
                'mph' => $this->positiveNumber($row->miles_per_hour),
                'strike' => $strike,
                'typeId' => $this->pitchTypeId((string) ($row->type_throw ?? '')),
                'locMark' => $locationMark,
            ];
        })->values();

        $total = $pitches->count();
        $mphValues = $pitches->pluck('mph')->filter(fn ($value) => null !== $value)->values();
        $strikes = $pitches->where('strike', true)->count();
        $fastballVelocities = $pitches->where('typeId', 1)->pluck('mph')->filter(fn ($value) => null !== $value)->values();

        $pitchTypeStats = collect([1 => 'FB', 2 => 'CH', 3 => 'SL', 4 => 'CV', 5 => 'OTHER'])
            ->map(function (string $label, int $typeId) use ($pitches): array {
                $items = $pitches->filter(
                    fn ($pitch) => 5 === $typeId ? (! $pitch['typeId'] || 5 === $pitch['typeId']) : $pitch['typeId'] === $typeId
                )->values();
                $velocities = $items->pluck('mph')->filter(fn ($value) => null !== $value)->all();

                return [
                    'type' => $label,
                    'strikes' => $items->where('strike', true)->count(),
                    'strikePct' => $this->pct($items->where('strike', true)->count(), $items->count()),
                    'avgMph' => $this->fmt($this->avg($velocities)),
                    'count' => $items->count(),
                ];
            })
            ->filter(fn (array $stat) => $stat['count'] > 0)
            ->values()
            ->all();

        return [
            'total' => $total,
            'maxFB' => $this->fmt($this->max($mphValues->all())),
            'avgFB' => $this->fmt($this->avg($fastballVelocities->all()) ?? $this->avg($mphValues->all())),
            'strikePct' => $this->pct($strikes, $total),
            // Bullpen practices don't record ball/strike counts or throw
            // quality, so these report-only metrics stay null on the dashboard.
            'firstStrikePct' => null,
            'locationAccuracyPct' => null,
            'competitivePct' => $this->pct($strikes, $total),
            'qualityPct' => null,
            'pitchTypeStats' => $pitchTypeStats,
            'missPattern' => $this->bullpenMissPattern($pitches),
        ];
    }

    private function bullpenMissPattern(Collection $pitches): array
    {
        $misses = $pitches->filter(fn ($pitch) => ! $pitch['strike'] && $pitch['locMark'] > 0)->values();
        if ($misses->count() < 3) {
            return [];
        }

        $buckets = [
            'Arm-Side High' => 0, 'Arm-Side' => 0, 'Arm-Side Low' => 0,
            'Glove-Side High' => 0, 'Glove-Side' => 0, 'Glove-Side Low' => 0,
            'Straight Up' => 0, 'Straight Down' => 0,
        ];
        foreach ($misses as $pitch) {
            [$col, $row] = $this->markToColRow($pitch['locMark']);
            $isHigh = $row < self::ZONE_ROW_MIN;
            $isLow = $row > self::ZONE_ROW_MAX;
            $isArm = $col > self::ZONE_COL_MAX;
            $isGlove = $col < self::ZONE_COL_MIN;

            if ($isArm && $isHigh) {
                $buckets['Arm-Side High']++;
            } elseif ($isArm && $isLow) {
                $buckets['Arm-Side Low']++;
            } elseif ($isArm) {
                $buckets['Arm-Side']++;
            } elseif ($isGlove && $isHigh) {
                $buckets['Glove-Side High']++;
            } elseif ($isGlove && $isLow) {
                $buckets['Glove-Side Low']++;
            } elseif ($isGlove) {
                $buckets['Glove-Side']++;
            } elseif ($isHigh) {
                $buckets['Straight Up']++;
            } else {
                $buckets['Straight Down']++;
            }
        }

        return collect($buckets)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'pct' => $this->fmt(($count / $misses->count()) * 100, 0),
            ])
            ->filter(fn (array $bucket) => ($bucket['pct'] ?? 0) > 0)
            ->sortByDesc('pct')
            ->take(4)
            ->values()
            ->all();
    }

    private function cageBreakdown(Collection $rows): array
    {
        $swings = $rows->map(fn ($row): array => [
            'ev' => $this->positiveNumber($row->launch_angle_velocity),
            'la' => $this->anyNumber($row->launch_angle),
            'spray' => $this->anyNumber($row->spray_angle),
        ])->values();

        $total = $swings->count();
        $evValues = $swings->pluck('ev')->filter(fn ($value) => null !== $value)->values();
        $laValues = $swings->pluck('la')->filter(fn ($value) => null !== $value)->values();
        $sprayValues = $swings->pluck('spray')->filter(fn ($value) => null !== $value)->values();

        $hardCount = $evValues->filter(fn ($value) => $value >= 90)->count();
        $barrelCount = $swings->filter(
            fn ($swing) => null !== $swing['ev'] && null !== $swing['la'] && $swing['ev'] >= 85 && $swing['la'] >= 8 && $swing['la'] <= 30
        )->count();
        $sweetCount = $laValues->filter(fn ($value) => $value >= 8 && $value <= 32)->count();
        $qualityCount = $swings->filter(
            fn ($swing) => null !== $swing['ev'] && null !== $swing['la'] && $swing['ev'] >= 75 && $swing['la'] >= 5 && $swing['la'] <= 35
        )->count();

        $pullCount = $sprayValues->filter(fn ($value) => $value <= -18)->count();
        $centerCount = $sprayValues->filter(fn ($value) => $value > -18 && $value < 18)->count();
        $oppoCount = $sprayValues->filter(fn ($value) => $value >= 18)->count();

        $laConsistency = null;
        if ($laValues->count() >= 3) {
            $mean = (float) $this->avg($laValues->all());
            $variance = $this->avg($laValues->map(fn ($value) => ($value - $mean) ** 2)->all());
            $laConsistency = $this->fmt(max(0, 30 - sqrt((float) $variance)));
        }

        $avgEv = $this->avg($evValues->all());
        $hardPct = $this->pct($hardCount, $evValues->count());
        $sweetPct = $this->pct($sweetCount, $laValues->count());
        $damage = (null === $avgEv || null === $hardPct || null === $sweetPct)
            ? null
            : $this->fmt((min($avgEv, 110) / 110) * 100 * 0.4 + $sweetPct * 0.35 + $hardPct * 0.25);

        $angleDenominator = $laValues->count() > 0 ? $laValues->count() : $total;

        return [
            'swings' => $total,
            'avgEV' => $this->fmt($avgEv),
            'maxEV' => $this->fmt($this->max($evValues->all())),
            'hardPct' => $hardPct,
            'barrelPct' => $this->pct($barrelCount, $angleDenominator),
            'avgLA' => $this->fmt($this->avg($laValues->all())),
            'laConsistency' => $laConsistency,
            'sweetPct' => $sweetPct,
            'swingQualityPct' => $this->pct($qualityCount, $angleDenominator),
            'pullPct' => $this->pct($pullCount, $sprayValues->count()),
            'centerPct' => $this->pct($centerCount, $sprayValues->count()),
            'oppoPct' => $this->pct($oppoCount, $sprayValues->count()),
            'sprayTotal' => $sprayValues->count(),
            'damage' => $damage,
        ];
    }

    private function weightedBreakdown(Collection $rows): array
    {
        $throws = $rows->map(fn ($row): array => [
            'weight' => $this->positiveNumber($row->weight),
            'velocity' => $this->positiveNumber($row->velocity),
        ])->filter(fn ($throw) => null !== $throw['weight'] && null !== $throw['velocity'])->values();

        $byWeight = $throws->groupBy('weight')
            ->map(function (Collection $group, $weight): array {
                $velocities = $group->pluck('velocity')->all();

                return [
                    'weight' => (float) $weight,
                    'count' => $group->count(),
                    'avgVelo' => $this->fmt($this->avg($velocities)),
                    'maxVelo' => $this->fmt($this->max($velocities)),
                ];
            })
            ->sortBy('weight')
            ->values()
            ->all();

        $allVelocities = $throws->pluck('velocity')->all();

        return [
            'throws' => $rows->count(),
            'maxVelo' => $this->fmt($this->max($allVelocities)),
            'avgVelo' => $this->fmt($this->avg($allVelocities)),
            'byWeight' => $byWeight,
        ];
    }

    private function exitVelocityBreakdown(Collection $rows): array
    {
        $swings = $rows->map(fn ($row): array => [
            'velocity' => $this->positiveNumber($row->velocity),
            'trajectory' => $this->normalizeTrajectory((string) ($row->trajectory ?? '')),
        ])->values();

        $evValues = $swings->pluck('velocity')->filter(fn ($value) => null !== $value)->values();
        $classified = $swings->filter(fn ($swing) => null !== $swing['trajectory'])->values();
        $trajectoryAvg = fn (string $key): ?float => $this->fmt($this->avg(
            $classified->where('trajectory', $key)->pluck('velocity')->filter(fn ($value) => null !== $value)->all()
        ));

        $gbCount = $classified->where('trajectory', 'GB')->count();
        $ldCount = $classified->where('trajectory', 'LD')->count();
        $fbCount = $classified->where('trajectory', 'FB')->count();

        return [
            'swings' => $swings->count(),
            'maxEV' => $this->fmt($this->max($evValues->all())),
            'avgEV' => $this->fmt($this->avg($evValues->all())),
            'hardPct' => $this->pct($evValues->filter(fn ($value) => $value >= 90)->count(), $evValues->count()),
            'gbPct' => $this->pct($gbCount, $classified->count()),
            'ldPct' => $this->pct($ldCount, $classified->count()),
            'fbPct' => $this->pct($fbCount, $classified->count()),
            'gbAvgEV' => $trajectoryAvg('GB'),
            'ldAvgEV' => $trajectoryAvg('LD'),
            'fbAvgEV' => $trajectoryAvg('FB'),
            'gbCount' => $gbCount,
            'ldCount' => $ldCount,
            'fbCount' => $fbCount,
            'trajTotal' => $classified->count(),
        ];
    }

    private function longTossBreakdown(Collection $rows): array
    {
        $throws = $rows->map(fn ($row): array => [
            'distance' => $this->positiveNumber($row->distance),
            'hop' => is_numeric($row->hop) ? (int) $row->hop : null,
        ])->values();

        $distances = $throws->pluck('distance')->filter(fn ($value) => null !== $value)->all();
        $hopCounts = [];
        $hopAverages = [];
        foreach ([0, 1, 2, 3] as $hop) {
            $hopThrows = $throws->where('hop', $hop);
            $hopCounts[$hop] = $hopThrows->count();
            $hopAverages[$hop] = $this->fmt($this->avg(
                $hopThrows->pluck('distance')->filter(fn ($value) => null !== $value)->all()
            ));
        }
        $hopTotal = array_sum($hopCounts);

        return [
            'throws' => $rows->count(),
            'maxDist' => $this->fmt($this->max($distances)),
            'avgDist' => $this->fmt($this->avg($distances)),
            'hop0' => $hopAverages[0],
            'hop1' => $hopAverages[1],
            'hop2' => $hopAverages[2],
            'hop3' => $hopAverages[3],
            'hop0Count' => $hopCounts[0],
            'hop1Count' => $hopCounts[1],
            'hop2Count' => $hopCounts[2],
            'hop3Count' => $hopCounts[3],
            'hopTotal' => $hopTotal,
            'hop0Pct' => $this->pct($hopCounts[0], $hopTotal),
            'hop1Pct' => $this->pct($hopCounts[1], $hopTotal),
            'hop2Pct' => $this->pct($hopCounts[2], $hopTotal),
            'hop3Pct' => $this->pct($hopCounts[3], $hopTotal),
        ];
    }

    private function recentSessions(string $userId, Collection $practices, Collection $ballsByPractice): array
    {
        $freshCutoff = Carbon::now()->subSeconds(self::FRESH_SESSION_SECONDS);

        return $practices
            ->unique('id')
            ->map(fn (Practice $practice): array => [
                'id' => $practice->id,
                'type' => $practice->type,
                'mode' => $practice->modes,
                'date' => $practice->created_at?->toIso8601String(),
                'total_balls' => (int) ($ballsByPractice[$practice->id] ?? 0),
                'is_completed' => (bool) $practice->is_completed,
                'end_note' => $practice->end_note,
                'created_by_self' => (string) $practice->user_id === $userId,
                '_created_at' => $practice->created_at,
            ])
            ->filter(fn (array $session) => $session['total_balls'] > 0
                || $session['is_completed']
                || ($session['created_by_self'] && $session['_created_at'] && $session['_created_at']->greaterThan($freshCutoff)))
            ->sortByDesc('_created_at')
            ->take(self::RECENT_SESSIONS_LIMIT)
            ->map(function (array $session): array {
                unset($session['_created_at']);

                return $session;
            })
            ->values()
            ->all();
    }

    /** @return array{0: int, 1: int} column and row on the 60x60 location grid */
    private function markToColRow(int $mark): array
    {
        return [intdiv($mark - 1, 60) + 1, (($mark - 1) % 60) + 1];
    }

    private function isStrikeZoneMark(int $mark): bool
    {
        if ($mark <= 0) {
            return false;
        }
        [$col, $row] = $this->markToColRow($mark);

        return $col >= self::ZONE_COL_MIN && $col <= self::ZONE_COL_MAX
            && $row >= self::ZONE_ROW_MIN && $row <= self::ZONE_ROW_MAX;
    }

    private function pitchTypeId(string $typeThrow): int
    {
        return match (strtoupper(trim($typeThrow))) {
            'FB' => 1,
            'CH' => 2,
            'SL' => 3,
            'CB', 'CV' => 4,
            default => 5,
        };
    }

    private function normalizeTrajectory(string $trajectory): ?string
    {
        return match (strtoupper(trim($trajectory))) {
            'GB' => 'GB',
            'LD' => 'LD',
            'FB', 'PF', 'PU' => 'FB',
            default => null,
        };
    }

    private function positiveNumber(mixed $raw): ?float
    {
        if (! is_numeric($raw)) {
            return null;
        }
        $value = (float) $raw;

        return $value > 0 ? $value : null;
    }

    private function anyNumber(mixed $raw): ?float
    {
        return is_numeric($raw) ? (float) $raw : null;
    }

    private function pct(int $count, int $total): ?float
    {
        return $total > 0 ? round(($count / $total) * 100, 1) : null;
    }

    /** @param array<int|float> $values */
    private function avg(array $values): ?float
    {
        return [] === $values ? null : array_sum($values) / count($values);
    }

    /** @param array<int|float> $values */
    private function max(array $values): ?float
    {
        return [] === $values ? null : (float) max($values);
    }

    private function fmt(float|int|null $value, int $decimals = 1): ?float
    {
        return null === $value ? null : round((float) $value, $decimals);
    }
}
