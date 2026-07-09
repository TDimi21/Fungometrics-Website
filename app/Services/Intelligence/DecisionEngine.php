<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class DecisionEngine
{
    private const FOCUS_TEMPLATES = [
        'fastball_command' => [
            'title' => 'Fastball Command',
            'category' => 'pitching',
            'why' => 'Multiple players show command risk or below-target strike percentage.',
            'action' => 'Fastball-only edge command bullpen.',
            'expected_gain' => '+5-8% strike percentage',
        ],
        'mobility_arm_care' => [
            'title' => 'Mobility / Arm Care',
            'category' => 'physical',
            'why' => 'Mobility or arm-care restrictions are limiting movement quality.',
            'action' => 'Hip, shoulder, and T-spine mobility circuit before throwing.',
            'expected_gain' => 'Better movement quality and lower compensation risk',
        ],
        'long_toss_transfer' => [
            'title' => 'Long Toss to Mound Transfer',
            'category' => 'throwing',
            'why' => 'Arm strength indicators are not fully transferring to mound velocity.',
            'action' => 'Pulldown-to-bullpen progression with lower-half sequencing work.',
            'expected_gain' => '+1-2 mph mound velocity',
        ],
        'barrel_control' => [
            'title' => 'Barrel Control',
            'category' => 'hitting',
            'why' => 'Exit velocity is present, but contact or launch quality is lagging.',
            'action' => 'Middle-middle line-drive rounds with contact quality tracking.',
            'expected_gain' => '+5-10% line-drive or quality-contact rate',
        ],
        'exit_velocity_power' => [
            'title' => 'Exit Velocity / Power',
            'category' => 'hitting',
            'why' => 'Power output is flat or below the current player profile.',
            'action' => 'Intent-based power rounds after a controlled barrel round.',
            'expected_gain' => '+1-3 mph exit velocity',
        ],
        'recovery_workload' => [
            'title' => 'Recovery / Workload',
            'category' => 'recovery',
            'why' => 'Workload is rising while recovery markers are low or declining.',
            'action' => 'Reduce high-intent volume and require recovery check-ins.',
            'expected_gain' => 'Improved readiness before the next high-intent session',
        ],
        'strength_lower_body' => [
            'title' => 'Strength / Lower Body Power',
            'category' => 'physical',
            'why' => 'Strength reserve or lower-body power may be limiting velocity transfer.',
            'action' => 'Lower-body power block paired with low-volume overload throws.',
            'expected_gain' => 'Improved force transfer and weighted-ball spectrum',
        ],
        'data_collection' => [
            'title' => 'Data Collection',
            'category' => 'workflow',
            'why' => 'Critical player data is missing, limiting decision confidence.',
            'action' => 'Collect bullpen, EV, long toss, weighted ball, and fitness baselines.',
            'expected_gain' => null,
        ],
    ];

    public function __construct(
        private readonly TeamIntelligenceService $teamIntelligence,
    ) {}

    public function buildTeamDecisionBrief(string $teamId, int $days = 365): array
    {
        $days = max(7, min(365, $days));
        $teamSnapshot = $this->teamIntelligence->build($teamId, $days);
        $players = is_array($teamSnapshot['players'] ?? null) ? $teamSnapshot['players'] : [];

        $candidates = $this->rankCandidates($this->buildCandidates($teamSnapshot, $players));
        $primary = $candidates[0] ?? $this->fallbackDataCollectionCandidate($teamSnapshot, $players);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'primary_focus' => $this->focusSummary($primary),
            'biggest_opportunity' => $this->biggestOpportunity($candidates, $primary),
            'biggest_concern' => $this->biggestConcern($candidates, $primary),
            'players_needing_attention' => $this->playersNeedingAttention($primary),
            'practice_focus' => $this->practiceFocus($primary),
            'expected_gain' => $primary['expected_gain'] ?? null,
            'confidence' => $this->candidateConfidence($primary),
            'evidence' => $this->flattenEvidence($primary),
            'recommended_practice_plan' => $this->practicePlanFor($primary['focus_key'] ?? 'data_collection'),
        ];
    }

    private function buildCandidates(array $teamSnapshot, array $players): array
    {
        $candidates = [];

        foreach ($players as $playerSnapshot) {
            $this->addLimiterCandidates($candidates, $playerSnapshot);
            $this->addRecommendationCandidates($candidates, $playerSnapshot);
            $this->addTrendCandidates($candidates, $playerSnapshot);
            $this->addBenchmarkCandidates($candidates, $playerSnapshot);
            $this->addDataGapCandidates($candidates, $playerSnapshot);
        }

        if (empty($players) || ! empty($teamSnapshot['data_gaps'])) {
            $this->addCandidate(
                $candidates,
                'data_collection',
                null,
                empty($players) ? 'No player snapshots were available for this team.' : 'Team-level data gaps were found.',
                'team_data_gap',
                'medium',
                'medium',
                null,
                [
                    'team_data_gaps' => $teamSnapshot['data_gaps'] ?? [],
                    'player_snapshot_count' => count($players),
                ]
            );
        }

        return $candidates;
    }

    private function addLimiterCandidates(array &$candidates, array $playerSnapshot): void
    {
        foreach ($playerSnapshot['limiters'] ?? [] as $limiter) {
            $focusKey = $this->focusForLimiter((string) ($limiter['id'] ?? ''), (string) ($limiter['title'] ?? ''));
            if ($focusKey === null) {
                continue;
            }

            $this->addCandidate(
                $candidates,
                $focusKey,
                $playerSnapshot,
                (string) ($limiter['why'] ?? $limiter['title'] ?? 'Limiter detected.'),
                'limiter:' . (string) ($limiter['id'] ?? 'unknown'),
                (string) ($limiter['priority'] ?? 'medium'),
                (string) ($limiter['confidence'] ?? 'medium'),
                null,
                $limiter['evidence'] ?? []
            );
        }
    }

    private function addRecommendationCandidates(array &$candidates, array $playerSnapshot): void
    {
        foreach ($playerSnapshot['recommendations'] ?? [] as $recommendation) {
            $focusKey = $this->focusForRecommendation((string) ($recommendation['id'] ?? ''), (string) ($recommendation['title'] ?? ''), (string) ($recommendation['category'] ?? ''));
            if ($focusKey === null) {
                continue;
            }

            $this->addCandidate(
                $candidates,
                $focusKey,
                $playerSnapshot,
                (string) ($recommendation['why'] ?? $recommendation['title'] ?? 'Recommendation detected.'),
                'recommendation:' . (string) ($recommendation['id'] ?? 'unknown'),
                (string) ($recommendation['priority'] ?? 'medium'),
                (string) ($recommendation['confidence'] ?? 'medium'),
                $recommendation['expected_gain'] ?? null,
                $recommendation['evidence'] ?? []
            );
        }
    }

    private function addTrendCandidates(array &$candidates, array $playerSnapshot): void
    {
        $trends = $playerSnapshot['trend_blocks'] ?? [];
        $strike = $trends['strike_percentage'] ?? [];
        $bullpenVelo = $trends['bullpen_avg_velocity'] ?? [];
        $longToss = $trends['long_toss_avg_distance'] ?? [];
        $exitVelo = $trends['exit_velocity_avg'] ?? [];
        $mobility = $trends['mobility_score'] ?? [];
        $recovery = $trends['recovery_score'] ?? [];

        if ($this->numberOrNull($strike['current'] ?? null) !== null && (float) $strike['current'] < 65) {
            $this->addCandidate($candidates, 'fastball_command', $playerSnapshot, 'Strike percentage is below the 65% target.', 'trend:strike_percentage', 'high', (string) ($strike['confidence'] ?? 'low'), null, $strike);
        } elseif (($strike['direction'] ?? null) === 'declining') {
            $this->addCandidate($candidates, 'fastball_command', $playerSnapshot, 'Strike percentage is declining.', 'trend:strike_percentage', 'medium', (string) ($strike['confidence'] ?? 'low'), null, $strike);
        }

        if (($longToss['direction'] ?? null) === 'improving' && in_array($bullpenVelo['direction'] ?? null, ['stable', 'declining', 'no_data'], true)) {
            $this->addCandidate($candidates, 'long_toss_transfer', $playerSnapshot, 'Long toss is improving while bullpen velocity is not improving at the same rate.', 'trend:long_toss_transfer', 'medium', $this->lowestConfidence($longToss, $bullpenVelo), null, ['long_toss' => $longToss, 'bullpen_avg_velocity' => $bullpenVelo]);
        }

        if (($exitVelo['direction'] ?? null) === 'declining') {
            $this->addCandidate($candidates, 'exit_velocity_power', $playerSnapshot, 'Exit velocity trend is declining.', 'trend:exit_velocity_avg', 'medium', (string) ($exitVelo['confidence'] ?? 'low'), null, $exitVelo);
        }

        if (($mobility['direction'] ?? null) === 'declining' || ($this->numberOrNull($mobility['current'] ?? null) !== null && (float) $mobility['current'] < 65)) {
            $this->addCandidate($candidates, 'mobility_arm_care', $playerSnapshot, 'Mobility score is low or declining.', 'trend:mobility_score', 'medium', (string) ($mobility['confidence'] ?? 'low'), null, $mobility);
        }

        if (($recovery['direction'] ?? null) === 'declining' || ($this->numberOrNull($recovery['current'] ?? null) !== null && (float) $recovery['current'] < 60)) {
            $this->addCandidate($candidates, 'recovery_workload', $playerSnapshot, 'Recovery score is low or declining.', 'trend:recovery_score', 'high', (string) ($recovery['confidence'] ?? 'low'), null, $recovery);
        }
    }

    private function addDataGapCandidates(array &$candidates, array $playerSnapshot): void
    {
        $gaps = is_array($playerSnapshot['data_gaps'] ?? null) ? $playerSnapshot['data_gaps'] : [];
        if (count($gaps) < 3) {
            return;
        }

        $this->addCandidate(
            $candidates,
            'data_collection',
            $playerSnapshot,
            'Player has multiple missing data sources, reducing decision confidence.',
            'data_gap',
            count($gaps) >= 6 ? 'high' : 'medium',
            'medium',
            null,
            ['data_gap_count' => count($gaps), 'data_gaps' => $gaps]
        );
    }

    private function addBenchmarkCandidates(array &$candidates, array $playerSnapshot): void
    {
        $benchmarks = $playerSnapshot['age_benchmarks']['metrics'] ?? [];
        if (! is_array($benchmarks)) {
            return;
        }

        foreach ($benchmarks['pitching'] ?? [] as $metric => $benchmark) {
            if (! $this->isBelowAverageBenchmark($benchmark)) {
                continue;
            }

            $focusKey = match ($metric) {
                'strike_percentage' => 'fastball_command',
                'long_toss_max_distance', 'weighted_ball_5oz_velocity', 'average_fastball_velocity', 'max_fastball_velocity' => 'long_toss_transfer',
                default => null,
            };

            if ($focusKey) {
                $this->addCandidate($candidates, $focusKey, $playerSnapshot, $this->benchmarkReason($metric, $benchmark), 'age_benchmark:pitching:'.$metric, $this->benchmarkPriority($benchmark), (string) ($benchmark['confidence'] ?? 'low'), null, $benchmark);
            }
        }

        foreach ($benchmarks['hitting'] ?? [] as $metric => $benchmark) {
            if (! $this->isBelowAverageBenchmark($benchmark)) {
                continue;
            }

            $focusKey = in_array($metric, ['average_exit_velocity', 'max_exit_velocity'], true) ? 'exit_velocity_power' : 'barrel_control';
            $this->addCandidate($candidates, $focusKey, $playerSnapshot, $this->benchmarkReason($metric, $benchmark), 'age_benchmark:hitting:'.$metric, $this->benchmarkPriority($benchmark), (string) ($benchmark['confidence'] ?? 'low'), null, $benchmark);
        }

        foreach ($benchmarks['strength'] ?? [] as $metric => $benchmark) {
            if ($this->isBelowAverageBenchmark($benchmark)) {
                $this->addCandidate($candidates, 'strength_lower_body', $playerSnapshot, $this->benchmarkReason($metric, $benchmark), 'age_benchmark:strength:'.$metric, $this->benchmarkPriority($benchmark), (string) ($benchmark['confidence'] ?? 'low'), null, $benchmark);
            }
        }

        foreach ($benchmarks['mobility'] ?? [] as $metric => $benchmark) {
            if ($this->isBelowAverageBenchmark($benchmark)) {
                $this->addCandidate($candidates, 'mobility_arm_care', $playerSnapshot, $this->benchmarkReason($metric, $benchmark), 'age_benchmark:mobility:'.$metric, $this->benchmarkPriority($benchmark), (string) ($benchmark['confidence'] ?? 'low'), null, $benchmark);
            }
        }
    }

    private function addCandidate(
        array &$candidates,
        string $focusKey,
        ?array $playerSnapshot,
        string $reason,
        string $source,
        string $priority,
        string $confidence,
        mixed $expectedGain,
        array $evidence
    ): void {
        $template = self::FOCUS_TEMPLATES[$focusKey] ?? self::FOCUS_TEMPLATES['data_collection'];
        $candidate = $candidates[$focusKey] ?? [
            'focus_key' => $focusKey,
            'title' => $template['title'],
            'category' => $template['category'],
            'why' => $template['why'],
            'action' => $template['action'],
            'expected_gain' => $expectedGain ?? $template['expected_gain'],
            'score' => 0.0,
            'priority_counts' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'confidence_counts' => ['high' => 0, 'medium' => 0, 'low' => 0],
            'affected_players' => [],
            'evidence' => [],
            'sources' => [],
        ];

        $priority = $this->normalizePriority($priority);
        $confidence = $this->normalizeConfidence($confidence);
        $playerId = $playerSnapshot['player_id'] ?? null;
        $playerName = $playerSnapshot ? $this->playerName($playerSnapshot) : 'Team';
        $playerKey = $playerId ? (string) $playerId : 'team';

        $candidate['priority_counts'][$priority]++;
        $candidate['confidence_counts'][$confidence]++;
        $candidate['sources'][] = $source;
        $candidate['score'] += 10 + $this->priorityScore($priority) + $this->confidenceScore($confidence) + $this->expectedGainScore($expectedGain ?? $candidate['expected_gain']);

        if (! isset($candidate['affected_players'][$playerKey])) {
            $candidate['affected_players'][$playerKey] = [
                'player_id' => $playerId,
                'name' => $playerName,
                'reason' => $reason,
                'priority' => $priority,
                'confidence' => $confidence,
                'evidence' => $this->compactEvidence($evidence),
            ];
        }

        $candidate['evidence'][] = [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'source' => $source,
            'reason' => $reason,
            'priority' => $priority,
            'confidence' => $confidence,
            'evidence' => $this->compactEvidence($evidence),
        ];

        $candidates[$focusKey] = $candidate;
    }

    private function rankCandidates(array $candidates): array
    {
        $ranked = array_values(array_map(function (array $candidate): array {
            $candidate['affected_players'] = array_values($candidate['affected_players']);
            $candidate['score'] = round($candidate['score'] + (count($candidate['affected_players']) * 6), 1);
            $candidate['sources'] = array_values(array_unique($candidate['sources']));

            return $candidate;
        }, $candidates));

        usort($ranked, fn (array $a, array $b) => ($b['score'] <=> $a['score']) ?: (count($b['affected_players']) <=> count($a['affected_players'])));

        return $ranked;
    }

    private function focusSummary(array $candidate): array
    {
        return [
            'title' => $candidate['title'] ?? 'Data Collection',
            'category' => $candidate['category'] ?? 'workflow',
            'why' => $candidate['why'] ?? 'Not enough reliable data is available to choose a baseball focus.',
            'action' => $candidate['action'] ?? self::FOCUS_TEMPLATES['data_collection']['action'],
            'affected_player_count' => count($this->playersNeedingAttention($candidate)),
            'score' => $candidate['score'] ?? null,
            'confidence' => $this->candidateConfidence($candidate),
        ];
    }

    private function biggestOpportunity(array $candidates, array $primary): array
    {
        foreach ($candidates as $candidate) {
            if (($candidate['focus_key'] ?? null) !== 'data_collection' && ! empty($candidate['expected_gain'])) {
                return $this->focusSummary($candidate) + ['expected_gain' => $candidate['expected_gain']];
            }
        }

        return $this->focusSummary($primary) + ['expected_gain' => $primary['expected_gain'] ?? null];
    }

    private function biggestConcern(array $candidates, array $primary): array
    {
        foreach ($candidates as $candidate) {
            if (($candidate['priority_counts']['high'] ?? 0) > 0) {
                return $this->focusSummary($candidate);
            }
        }

        return $this->focusSummary($primary);
    }

    private function playersNeedingAttention(array $candidate): array
    {
        return array_values(array_filter(
            $candidate['affected_players'] ?? [],
            fn (array $player) => ! empty($player['player_id'])
        ));
    }

    private function practiceFocus(array $candidate): array
    {
        return [
            'title' => $candidate['title'] ?? 'Data Collection',
            'category' => $candidate['category'] ?? 'workflow',
            'what' => $candidate['title'] ?? 'Data Collection',
            'why' => $candidate['why'] ?? self::FOCUS_TEMPLATES['data_collection']['why'],
            'next_action' => $candidate['action'] ?? self::FOCUS_TEMPLATES['data_collection']['action'],
        ];
    }

    private function fallbackDataCollectionCandidate(array $teamSnapshot, array $players): array
    {
        $candidate = [
            'focus_key' => 'data_collection',
            'title' => self::FOCUS_TEMPLATES['data_collection']['title'],
            'category' => self::FOCUS_TEMPLATES['data_collection']['category'],
            'why' => self::FOCUS_TEMPLATES['data_collection']['why'],
            'action' => self::FOCUS_TEMPLATES['data_collection']['action'],
            'expected_gain' => null,
            'score' => 10.0,
            'priority_counts' => ['high' => 0, 'medium' => 1, 'low' => 0],
            'confidence_counts' => ['high' => 0, 'medium' => 1, 'low' => 0],
            'affected_players' => [],
            'sources' => ['fallback'],
            'evidence' => [[
                'source' => 'fallback',
                'reason' => 'No stronger team decision focus was found.',
                'player_snapshot_count' => count($players),
                'team_data_gaps' => $teamSnapshot['data_gaps'] ?? [],
            ]],
        ];

        return $candidate;
    }

    private function practicePlanFor(string $focusKey): array
    {
        return match ($focusKey) {
            'fastball_command' => [
                'title' => 'Fastball Command Day',
                'duration_minutes' => 75,
                'blocks' => [
                    ['name' => 'Prep', 'duration_minutes' => 12, 'description' => 'Hip mobility, shoulder prep, band series.', 'why' => 'Prepare arms and reduce throwing risk.'],
                    ['name' => 'Throwing', 'duration_minutes' => 20, 'description' => 'Fastball-only edge command catch play.', 'why' => 'Build strike-throwing accuracy before mound work.'],
                    ['name' => 'Bullpen', 'duration_minutes' => 25, 'description' => '24-pitch command bullpen. Track strike %, miss side, and velocity.', 'why' => 'Target command without losing velocity.'],
                    ['name' => 'Review', 'duration_minutes' => 8, 'description' => 'Review strike %, miss pattern, and next target.', 'why' => 'Make the next command block specific.'],
                ],
            ],
            'long_toss_transfer' => [
                'title' => 'Long Toss Transfer Day',
                'duration_minutes' => 80,
                'blocks' => [
                    ['name' => 'Prep', 'duration_minutes' => 12, 'description' => 'Mobility, band work, and low-intent catch.', 'why' => 'Prepare for high-output throwing.'],
                    ['name' => 'Pulldown Progression', 'duration_minutes' => 20, 'description' => 'Controlled pulldowns with radar and intent notes.', 'why' => 'Connect arm speed to the game-ball throw.'],
                    ['name' => 'Mound Transfer', 'duration_minutes' => 25, 'description' => 'Short fastball mound set after pulldowns. Track velo, strike %, and miss side.', 'why' => 'Measure whether arm strength transfers to mound velocity.'],
                    ['name' => 'Cooldown', 'duration_minutes' => 8, 'description' => 'Arm-care circuit and recovery check-in.', 'why' => 'Control workload after high-intent throws.'],
                ],
            ],
            'mobility_arm_care' => [
                'title' => 'Mobility / Arm Care Day',
                'duration_minutes' => 60,
                'blocks' => [
                    ['name' => 'Mobility Screen', 'duration_minutes' => 10, 'description' => 'Check shoulder, hip, T-spine, and trunk rotation.', 'why' => 'Find the movement limit before training around it.'],
                    ['name' => 'Mobility Circuit', 'duration_minutes' => 20, 'description' => 'Hip flow, T-spine rotation, shoulder ER/IR work.', 'why' => 'Improve positions that support throwing and hitting.'],
                    ['name' => 'Arm Care', 'duration_minutes' => 18, 'description' => 'Band series, cuff activation, scap control.', 'why' => 'Build arm durability before the next high-intent day.'],
                    ['name' => 'Low Intent Skill', 'duration_minutes' => 12, 'description' => 'Low-intent catch or tee work with clean movement.', 'why' => 'Reinforce better movement without fatigue.'],
                ],
            ],
            'barrel_control' => [
                'title' => 'Barrel Control Day',
                'duration_minutes' => 75,
                'blocks' => [
                    ['name' => 'Prep', 'duration_minutes' => 10, 'description' => 'Movement prep and bat-path feel work.', 'why' => 'Prepare hitters to control the barrel.'],
                    ['name' => 'Constraint Rounds', 'duration_minutes' => 20, 'description' => 'Middle-middle line-drive rounds with quality contact scoring.', 'why' => 'Improve line-drive rate before adding intent.'],
                    ['name' => 'Decision Rounds', 'duration_minutes' => 20, 'description' => 'Mixed pitch or location rounds. Track contact quality, trajectory, and direction.', 'why' => 'Transfer barrel control to game-like swings.'],
                    ['name' => 'Review', 'duration_minutes' => 8, 'description' => 'Review hard-contact rate and line-drive percentage.', 'why' => 'Give the next hitting block a measurable target.'],
                ],
            ],
            'exit_velocity_power' => [
                'title' => 'Exit Velocity / Power Day',
                'duration_minutes' => 70,
                'blocks' => [
                    ['name' => 'Prep', 'duration_minutes' => 10, 'description' => 'Movement prep and controlled bat-speed buildup.', 'why' => 'Prepare for higher-intent swings.'],
                    ['name' => 'Barrel Baseline', 'duration_minutes' => 15, 'description' => 'Controlled line-drive round before max intent.', 'why' => 'Keep power work connected to usable contact.'],
                    ['name' => 'Power Rounds', 'duration_minutes' => 25, 'description' => 'Intent rounds with EV, trajectory, and miss tracking.', 'why' => 'Raise power output while watching contact quality.'],
                    ['name' => 'Review', 'duration_minutes' => 8, 'description' => 'Compare average EV, top EV, and line-drive rate.', 'why' => 'Separate real power gains from empty max-effort swings.'],
                ],
            ],
            'recovery_workload' => [
                'title' => 'Recovery / Workload Day',
                'duration_minutes' => 45,
                'blocks' => [
                    ['name' => 'Recovery Check', 'duration_minutes' => 8, 'description' => 'Collect soreness, fatigue, readiness, and sleep.', 'why' => 'Confirm whether players should train or recover.'],
                    ['name' => 'Low-Intensity Movement', 'duration_minutes' => 15, 'description' => 'Mobility, breathing, and light movement circuit.', 'why' => 'Promote recovery without adding throwing stress.'],
                    ['name' => 'Arm Care', 'duration_minutes' => 15, 'description' => 'Band, cuff, scap, and forearm work.', 'why' => 'Maintain arm health after workload spikes.'],
                    ['name' => 'Plan Next Load', 'duration_minutes' => 7, 'description' => 'Set next session volume from readiness and recent workload.', 'why' => 'Avoid stacking high-intent days blindly.'],
                ],
            ],
            'strength_lower_body' => [
                'title' => 'Strength / Lower Body Power Day',
                'duration_minutes' => 70,
                'blocks' => [
                    ['name' => 'Prep', 'duration_minutes' => 10, 'description' => 'Hip mobility, trunk activation, and landing mechanics.', 'why' => 'Prepare the lower half to produce and accept force.'],
                    ['name' => 'Power Block', 'duration_minutes' => 20, 'description' => 'Medicine ball throws, jumps, and rotational power work.', 'why' => 'Build force production that can transfer to throwing and hitting.'],
                    ['name' => 'Skill Transfer', 'duration_minutes' => 20, 'description' => 'Low-volume overload throws or high-intent swing transfer rounds.', 'why' => 'Connect physical output to baseball movement.'],
                    ['name' => 'Review', 'duration_minutes' => 8, 'description' => 'Review force drop-off, velo transfer, or EV response.', 'why' => 'Measure whether strength is showing up in sport data.'],
                ],
            ],
            default => [
                'title' => 'Data Collection Day',
                'duration_minutes' => 60,
                'blocks' => [
                    ['name' => 'Roster Check', 'duration_minutes' => 8, 'description' => 'Confirm active players, handedness, height, weight, and position.', 'why' => 'Clean player context improves all downstream intelligence.'],
                    ['name' => 'Throwing Baseline', 'duration_minutes' => 18, 'description' => 'Collect bullpen velocity, strike %, long toss distance, and weighted-ball 5 oz velocity where appropriate.', 'why' => 'Pitching and throwing recommendations need a current baseline.'],
                    ['name' => 'Hitting Baseline', 'duration_minutes' => 15, 'description' => 'Collect exit velocity, contact quality, trajectory, and direction.', 'why' => 'Hitting recommendations need contact and power context.'],
                    ['name' => 'Physical Baseline', 'duration_minutes' => 12, 'description' => 'Collect mobility, strength, soreness, sleep, and readiness.', 'why' => 'Physical and recovery data explain why performance is moving.'],
                ],
            ],
        };
    }

    private function focusForLimiter(string $id, string $title): ?string
    {
        $haystack = strtolower($id . ' ' . $title);

        return match (true) {
            str_contains($haystack, 'command') => 'fastball_command',
            str_contains($haystack, 'mobility') || str_contains($haystack, 'arm care') => 'mobility_arm_care',
            str_contains($haystack, 'long-toss') || str_contains($haystack, 'long toss') || str_contains($haystack, 'mound-transfer') || str_contains($haystack, 'weighted-ball-to-mound') || str_contains($haystack, 'five-oz') || str_contains($haystack, '5 oz') || str_contains($haystack, 'underload-speed') => 'long_toss_transfer',
            str_contains($haystack, 'barrel') => 'barrel_control',
            str_contains($haystack, 'recovery') || str_contains($haystack, 'workload') => 'recovery_workload',
            str_contains($haystack, 'overload-strength') || str_contains($haystack, 'strength') || str_contains($haystack, 'spectrum') => 'strength_lower_body',
            default => null,
        };
    }

    private function focusForRecommendation(string $id, string $title, string $category): ?string
    {
        $haystack = strtolower($id . ' ' . $title . ' ' . $category);

        return match (true) {
            str_contains($haystack, 'command') => 'fastball_command',
            str_contains($haystack, 'mobility') || str_contains($haystack, 'arm care') => 'mobility_arm_care',
            str_contains($haystack, 'mound') || str_contains($haystack, 'weighted-ball') || str_contains($haystack, 'underload') || str_contains($haystack, 'five-oz') => 'long_toss_transfer',
            str_contains($haystack, 'barrel') || str_contains($haystack, 'line-drive') => 'barrel_control',
            str_contains($haystack, 'exit velocity') || str_contains($haystack, 'power') => 'exit_velocity_power',
            str_contains($haystack, 'recovery') || str_contains($haystack, 'workload') => 'recovery_workload',
            str_contains($haystack, 'strength') || str_contains($haystack, 'spectrum') => 'strength_lower_body',
            default => null,
        };
    }

    private function playerName(array $playerSnapshot): string
    {
        $player = $playerSnapshot['summary']['player'] ?? [];
        $candidates = [
            $player['name'] ?? null,
            $player['full_name'] ?? null,
            trim((string) ($player['first_name'] ?? '') . ' ' . (string) ($player['last_name'] ?? '')),
            $playerSnapshot['player_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return 'Unknown Player';
    }

    private function candidateConfidence(array $candidate): string
    {
        $affected = count($candidate['affected_players'] ?? []);
        $counts = $candidate['confidence_counts'] ?? [];

        if (($counts['high'] ?? 0) > 0 && $affected >= 2) {
            return 'high';
        }

        if (($counts['medium'] ?? 0) > 0 || $affected >= 2) {
            return 'medium';
        }

        return 'low';
    }

    private function flattenEvidence(array $candidate): array
    {
        return array_slice($candidate['evidence'] ?? [], 0, 8);
    }

    private function compactEvidence(array $evidence): array
    {
        if (empty($evidence)) {
            return [];
        }

        return array_slice($evidence, 0, 8, true);
    }

    private function priorityScore(string $priority): int
    {
        return match ($this->normalizePriority($priority)) {
            'high' => 30,
            'medium' => 18,
            default => 8,
        };
    }

    private function confidenceScore(string $confidence): int
    {
        return match ($this->normalizeConfidence($confidence)) {
            'high' => 15,
            'medium' => 9,
            default => 3,
        };
    }

    private function expectedGainScore(mixed $expectedGain): int
    {
        return empty($expectedGain) ? 0 : 8;
    }

    private function normalizePriority(string $priority): string
    {
        return in_array($priority, ['high', 'medium', 'low'], true) ? $priority : 'medium';
    }

    private function normalizeConfidence(string $confidence): string
    {
        return in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low';
    }

    private function lowestConfidence(array ...$trends): string
    {
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2];
        $lowest = 'high';

        foreach ($trends as $trend) {
            $confidence = $this->normalizeConfidence((string) ($trend['confidence'] ?? 'low'));
            if ($rank[$confidence] < $rank[$lowest]) {
                $lowest = $confidence;
            }
        }

        return $lowest;
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function isBelowAverageBenchmark(mixed $benchmark): bool
    {
        return is_array($benchmark) && in_array(strtolower((string) ($benchmark['benchmark_label'] ?? '')), ['critical', 'below_average', 'below average'], true);
    }

    private function benchmarkPriority(array $benchmark): string
    {
        return strtolower((string) ($benchmark['benchmark_label'] ?? '')) === 'critical' ? 'high' : 'medium';
    }

    private function benchmarkReason(string $metric, array $benchmark): string
    {
        $label = $benchmark['benchmark_label'] ?? 'Needs Data';
        $ageGroup = $benchmark['age_group'] ?? 'UNKNOWN';
        $raw = $benchmark['raw_value'] ?? null;

        return str_replace('_', ' ', $metric).' is '.$label.' for age group '.$ageGroup.($raw !== null ? ' at '.$raw.'.' : '.');
    }
}
