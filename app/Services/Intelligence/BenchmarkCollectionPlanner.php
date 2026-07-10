<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Throwable;

class BenchmarkCollectionPlanner
{
    private const SESSION_DEFINITIONS = [
        'roster_cleanup' => [
            'title' => 'Roster Cleanup',
            'collection_type' => 'roster',
            'duration_minutes' => 10,
            'description' => 'Confirm DOB, position, height, weight, throws, bats, and level.',
            'why' => 'Player context improves age and peer-group benchmark accuracy.',
            'metrics' => ['player_context'],
            'coach_instructions' => [
                'Open each player profile.',
                'Confirm date of birth, position or role, height, weight, throws, bats, and level.',
                'Save the roster profile before running benchmark testing.',
            ],
        ],
        'exit_velocity_baseline' => [
            'title' => 'Exit Velocity Baseline',
            'collection_type' => 'hitting',
            'duration_minutes' => 25,
            'description' => 'Run a short EV session and record average EV, max EV, contact quality, line-drive rate, and swing/miss.',
            'why' => 'Hitting benchmark intelligence needs current power and contact-quality baselines.',
            'metrics' => ['average_exit_velocity', 'max_exit_velocity', 'hard_hit_percentage', 'line_drive_percentage', 'hitter_swing_miss_percentage'],
            'coach_instructions' => [
                'Run a controlled round before max-intent swings.',
                'Record EV and trajectory for every scored ball.',
                'Tag contact quality so the benchmark can separate power from usable contact.',
            ],
        ],
        'bullpen_baseline' => [
            'title' => 'Bullpen Baseline',
            'collection_type' => 'pitching',
            'duration_minutes' => 25,
            'description' => 'Run a fastball-focused bullpen and record average fastball velocity, max fastball velocity, and strike percentage.',
            'why' => 'Pitching benchmark intelligence needs a mound baseline before command or transfer recommendations are trusted.',
            'metrics' => ['average_fastball_velocity', 'max_fastball_velocity', 'strike_percentage'],
            'coach_instructions' => [
                'Use a consistent pitch count for each pitcher.',
                'Track velocity and strike percentage on every fastball.',
                'Record miss side notes when possible.',
            ],
        ],
        'throwing_baseline' => [
            'title' => 'Long Toss / Weighted Ball Baseline',
            'collection_type' => 'throwing',
            'duration_minutes' => 30,
            'description' => 'Collect long toss max distance and weighted-ball 5 oz velocity for pitchers and throwers.',
            'why' => 'Throwing development needs arm-strength context to understand mound transfer.',
            'metrics' => ['long_toss_max_distance', 'weighted_ball_5oz_velocity'],
            'coach_instructions' => [
                'Record long toss max distance after a normal throwing build-up.',
                'Record the latest 5 oz weighted-ball velocity.',
                'Do not turn baseline collection into extra high-intent volume.',
            ],
        ],
        'strength_baseline' => [
            'title' => 'Strength Baseline',
            'collection_type' => 'strength',
            'duration_minutes' => 35,
            'description' => 'Collect bench press, squat, deadlift, pull-ups, and pushups where appropriate.',
            'why' => 'Strength data explains whether force production may be limiting velocity, EV, or durability.',
            'metrics' => ['bench_press', 'squat', 'deadlift', 'pull_ups', 'pushups'],
            'coach_instructions' => [
                'Use safe testing loads and consistent standards.',
                'Record bodyweight alongside strength numbers when available.',
                'Do not force max testing for players who are not ready.',
            ],
        ],
        'athletic_testing' => [
            'title' => 'Athletic Testing',
            'collection_type' => 'athletic',
            'duration_minutes' => 25,
            'description' => 'Collect 40-yard dash, 60-yard dash, broad jump, and vertical jump.',
            'why' => 'Athletic data helps connect speed and power traits to baseball performance.',
            'metrics' => ['forty_yard_dash', 'sixty_yard_dash', 'broad_jump', 'vertical_jump'],
            'coach_instructions' => [
                'Run tests after a full dynamic warm-up.',
                'Use the same timing and measurement standard for every player.',
                'Record best valid attempt for each test.',
            ],
        ],
        'mobility_screen' => [
            'title' => 'Mobility Screen',
            'collection_type' => 'mobility',
            'duration_minutes' => 15,
            'description' => 'Collect mobility, shoulder mobility, hip mobility, and T-spine mobility scores.',
            'why' => 'Mobility data helps identify movement restrictions that may affect throwing, hitting, and recovery.',
            'metrics' => ['mobility_score', 'shoulder_mobility_score', 'hip_mobility_score', 't_spine_mobility_score'],
            'coach_instructions' => [
                'Screen shoulder, hip, and T-spine movement consistently.',
                'Record separate area scores when possible.',
                'Use the results to guide warm-up and arm-care blocks.',
            ],
        ],
    ];

    public function __construct(
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly DecisionEngine $decisionEngine,
        private readonly BenchmarkLibrary $benchmarkLibrary,
    ) {
    }

    public function buildTeamCollectionPlan(string $teamId, int $days = 365): array
    {
        $days = max(7, min(365, $days));
        $benchmarkProfile = $this->teamBenchmarkProfileService->build($teamId, $days);
        $decisionBrief = $this->decisionBrief($teamId, $days);

        return $this->buildTeamCollectionPlanFromData($teamId, $days, $benchmarkProfile, $decisionBrief);
    }

    public function buildTeamCollectionPlanFromData(string $teamId, int $days, array $benchmarkProfile, ?array $decisionBrief = null): array
    {
        $days = max(7, min(365, $days));
        $missingRows = $this->missingRows($benchmarkProfile, $decisionBrief);
        $collectionSessions = $this->collectionSessions($missingRows, $benchmarkProfile, $decisionBrief);
        $nextBestAction = $this->nextBestAction($collectionSessions, $decisionBrief);
        $playerTasks = $this->playerTasks($missingRows);
        $metricTasks = $this->metricTasks($missingRows);
        $priorityLevel = $this->priorityLevel($missingRows, $benchmarkProfile, $decisionBrief);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'priority_level' => $priorityLevel,
            'summary' => $this->summary($benchmarkProfile, $missingRows, $nextBestAction),
            'next_best_action' => $nextBestAction,
            'collection_sessions' => $collectionSessions,
            'player_tasks' => $playerTasks,
            'metric_tasks' => $metricTasks,
            'estimated_total_minutes' => array_sum(array_map(fn (array $session) => (int) ($session['duration_minutes'] ?? 0), $collectionSessions)),
            'completion_targets' => $this->completionTargets($collectionSessions, $benchmarkProfile, $nextBestAction),
            'evidence' => [
                'days' => $days,
                'player_count' => $benchmarkProfile['player_count'] ?? 0,
                'benchmark_metric_count' => $benchmarkProfile['metric_count'] ?? 0,
                'players_with_benchmark_metrics' => $benchmarkProfile['evidence']['players_with_benchmark_metrics'] ?? null,
                'players_without_benchmark_metrics' => $benchmarkProfile['evidence']['players_without_benchmark_metrics'] ?? null,
                'decision_primary_focus' => $decisionBrief['primary_focus']['title'] ?? null,
                'decision_data_collection_priority' => $decisionBrief['data_collection_priority']['level'] ?? null,
                'missing_metric_count' => count($missingRows),
                'collection_session_count' => count($collectionSessions),
            ],
        ];
    }

    private function decisionBrief(string $teamId, int $days): ?array
    {
        try {
            return $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);
        } catch (Throwable) {
            return null;
        }
    }

    private function missingRows(array $benchmarkProfile, ?array $decisionBrief): array
    {
        $rows = [];
        $dataPriority = is_array($decisionBrief['data_collection_priority'] ?? null)
            ? $decisionBrief['data_collection_priority']
            : [];

        foreach ([
            $benchmarkProfile['missing_metrics'] ?? [],
            $dataPriority['missing_critical'] ?? [],
            $dataPriority['missing_supporting'] ?? [],
            $dataPriority['missing_optional'] ?? [],
        ] as $rowGroup) {
            foreach (is_array($rowGroup) ? $rowGroup : [] as $row) {
                if (is_array($row)) {
                    $this->mergeMissingRow($rows, $row, (int) ($benchmarkProfile['player_count'] ?? 0));
                }
            }
        }

        return collect($rows)
            ->map(function (array $row) {
                $playersMissing = array_values($row['players_missing'] ?? []);
                $row['players_missing'] = $playersMissing;
                $row['players'] = collect($playersMissing)
                    ->map(fn (array $player) => [
                        'player_id' => $player['player_id'] ?? null,
                        'name' => $player['player_name'] ?? $player['name'] ?? 'Unknown Player',
                    ])
                    ->values()
                    ->all();
                $row['missing_count'] = ! empty($playersMissing)
                    ? count($playersMissing)
                    : (int) ($row['missing_count'] ?? 0);
                $row['recommended_session'] = $this->recommendedSessionForMetric((string) ($row['metric_key'] ?? 'unknown'));
                $row['priority'] = $this->priorityForMissingRow($row);

                return $row;
            })
            ->filter(fn (array $row) => ((int) ($row['missing_count'] ?? 0)) > 0)
            ->sort(function (array $a, array $b) {
                return ($this->priorityRank((string) ($b['priority'] ?? 'low')) <=> $this->priorityRank((string) ($a['priority'] ?? 'low')))
                    ?: ((int) ($b['missing_count'] ?? 0) <=> (int) ($a['missing_count'] ?? 0))
                    ?: strcmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
            })
            ->values()
            ->all();
    }

    private function mergeMissingRow(array &$rows, array $row, int $teamPlayerCount): void
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) ($row['metric_key'] ?? 'unknown'));
        $definition = $this->benchmarkLibrary->metric($metricKey);
        $rows[$metricKey] ??= [
            'metric_key' => $metricKey,
            'display_name' => $this->displayName($metricKey, $row['display_name'] ?? $definition['display_name'] ?? null),
            'category' => $row['category'] ?? $definition['category'] ?? 'unknown',
            'classification' => $row['classification'] ?? 'optional_missing_data',
            'missing_count' => 0,
            'eligible_count' => max(1, (int) ($row['player_count'] ?? $teamPlayerCount)),
            'player_count' => max(1, (int) ($row['player_count'] ?? $teamPlayerCount)),
            'players_missing' => [],
            'reason' => $row['reason'] ?? 'Metric value is missing.',
        ];

        $rows[$metricKey]['classification'] = $this->strongerClassification(
            (string) ($rows[$metricKey]['classification'] ?? 'optional_missing_data'),
            (string) ($row['classification'] ?? 'optional_missing_data'),
        );
        $rows[$metricKey]['eligible_count'] = max((int) ($rows[$metricKey]['eligible_count'] ?? 0), (int) ($row['player_count'] ?? 0));
        $rows[$metricKey]['player_count'] = max((int) ($rows[$metricKey]['player_count'] ?? 0), (int) ($row['player_count'] ?? 0));
        $rows[$metricKey]['missing_count'] = max((int) ($rows[$metricKey]['missing_count'] ?? 0), (int) ($row['missing_count'] ?? 0));

        foreach ($this->playersMissing($row) as $player) {
            $playerKey = (string) ($player['player_id'] ?? $player['player_name'] ?? $player['name'] ?? 'unknown');
            $existing = $rows[$metricKey]['players_missing'][$playerKey] ?? [];
            $existingFields = is_array($existing) ? ($existing['missing_fields'] ?? []) : [];
            $missingFields = array_values(array_unique(array_filter([...$existingFields, ...($player['missing_fields'] ?? [])])));

            $rows[$metricKey]['players_missing'][$playerKey] = [
                'player_id' => $player['player_id'] ?? null,
                'player_name' => $player['player_name'] ?? $player['name'] ?? 'Unknown Player',
                'name' => $player['name'] ?? $player['player_name'] ?? 'Unknown Player',
                'missing_fields' => $missingFields,
            ];
        }
    }

    private function collectionSessions(array $missingRows, array $benchmarkProfile, ?array $decisionBrief): array
    {
        $sessions = [];

        foreach (self::SESSION_DEFINITIONS as $sessionKey => $definition) {
            $rows = $this->rowsForSession($missingRows, $sessionKey, $definition);
            if (empty($rows)) {
                continue;
            }

            $players = $this->playersForRows($rows);
            $metrics = $sessionKey === 'roster_cleanup'
                ? $this->rosterFieldsForRows($rows)
                : array_values(array_unique(array_map(fn (array $row) => (string) $row['metric_key'], $rows)));

            $sessions[] = [
                'title' => $definition['title'],
                'priority' => $this->sessionPriority($rows, $benchmarkProfile, $decisionBrief),
                'duration_minutes' => $definition['duration_minutes'],
                'description' => $definition['description'],
                'why' => $definition['why'],
                'players' => $players,
                'metrics' => $metrics,
                'metric_keys' => array_values(array_unique(array_map(fn (array $row) => (string) $row['metric_key'], $rows))),
                'metric_display_names' => array_values(array_unique(array_map(fn (array $row) => (string) ($row['display_name'] ?? $row['metric_key']), $rows))),
                'collection_type' => $definition['collection_type'],
                'coach_instructions' => $definition['coach_instructions'],
                'missing_count' => array_sum(array_map(fn (array $row) => (int) ($row['missing_count'] ?? 0), $rows)),
            ];
        }

        return $this->orderedSessions($sessions, $benchmarkProfile, $decisionBrief);
    }

    private function rowsForSession(array $missingRows, string $sessionKey, array $definition): array
    {
        $metricKeys = array_map(
            fn (string $metricKey) => BenchmarkDefinitions::normalizeMetricKey($metricKey),
            $definition['metrics']
        );

        return collect($missingRows)
            ->filter(function (array $row) use ($sessionKey, $metricKeys) {
                $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) ($row['metric_key'] ?? 'unknown'));
                if ($sessionKey === 'roster_cleanup') {
                    return $metricKey === 'player_context';
                }

                return in_array($metricKey, $metricKeys, true);
            })
            ->values()
            ->all();
    }

    private function orderedSessions(array $sessions, array $benchmarkProfile, ?array $decisionBrief): array
    {
        $primaryFocus = strtolower((string) ($decisionBrief['primary_focus']['title'] ?? ''));
        $weakestCategory = strtolower((string) (($benchmarkProfile['weakest_categories'][0]['category'] ?? '') ?: ''));

        $weighted = collect($sessions)
            ->map(function (array $session) use ($primaryFocus, $weakestCategory) {
                $type = (string) ($session['collection_type'] ?? '');
                $score = $this->priorityRank((string) ($session['priority'] ?? 'low')) * 10;

                if ($type === 'roster') {
                    $score += 100;
                }

                if ($type === 'hitting' && str_contains($primaryFocus, 'exit velocity')) {
                    $score += 60;
                }

                if ($type === 'pitching' && (str_contains($primaryFocus, 'fastball') || str_contains($primaryFocus, 'command'))) {
                    $score += 60;
                }

                if ($type === 'throwing' && (str_contains($primaryFocus, 'long toss') || str_contains($primaryFocus, 'transfer'))) {
                    $score += 55;
                }

                if ($type === 'strength' && $weakestCategory === 'strength') {
                    $score += 45;
                }

                if ($type === 'mobility' && $weakestCategory === 'mobility') {
                    $score += 45;
                }

                if ($type === 'athletic' && $weakestCategory === 'athletic') {
                    $score += 40;
                }

                $session['_sort_score'] = $score;

                return $session;
            })
            ->sortByDesc('_sort_score')
            ->values()
            ->all();

        $nextSessionMinutes = 0;
        $thisWeekMinutes = 0;

        return array_values(array_map(function (array $session, int $index) use (&$nextSessionMinutes, &$thisWeekMinutes) {
            unset($session['_sort_score']);

            $minutes = (int) ($session['duration_minutes'] ?? 0);
            if ($index < 2 && ($nextSessionMinutes + $minutes) <= 90) {
                $window = 'next_session';
                $nextSessionMinutes += $minutes;
            } elseif (($thisWeekMinutes + $minutes) <= 90) {
                $window = 'this_week';
                $thisWeekMinutes += $minutes;
            } else {
                $window = 'this_month';
            }

            $session['sequence'] = $index + 1;
            $session['schedule_window'] = $window;

            return $session;
        }, $weighted, array_keys($weighted)));
    }

    private function nextBestAction(array $sessions, ?array $decisionBrief): array
    {
        if (empty($sessions)) {
            return [
                'title' => 'No Benchmark Collection Needed',
                'priority' => 'low',
                'duration_minutes' => 0,
                'why' => 'No missing benchmark collection sessions are currently recommended.',
                'players' => [],
                'metrics' => [],
                'coach_instructions' => [],
            ];
        }

        $nextSessions = collect($sessions)
            ->filter(fn (array $session) => ($session['schedule_window'] ?? null) === 'next_session')
            ->values();

        if ($nextSessions->isEmpty()) {
            $nextSessions = collect(array_slice($sessions, 0, 1));
        }

        $titles = $nextSessions->pluck('title')->values()->all();
        $players = $this->uniquePlayers($nextSessions->flatMap(fn (array $session) => $session['players'] ?? [])->values()->all());
        $metrics = $nextSessions->flatMap(fn (array $session) => $session['metric_keys'] ?? $session['metrics'] ?? [])->unique()->values()->all();
        $instructions = $nextSessions->flatMap(fn (array $session) => $session['coach_instructions'] ?? [])->unique()->values()->all();
        $duration = (int) $nextSessions->sum(fn (array $session) => (int) ($session['duration_minutes'] ?? 0));

        return [
            'title' => implode(' + ', $titles),
            'priority' => $this->highestPriority($nextSessions->pluck('priority')->all()),
            'duration_minutes' => $duration,
            'why' => $this->nextBestActionWhy($nextSessions->all(), $decisionBrief),
            'players' => $players,
            'metrics' => $metrics,
            'coach_instructions' => $instructions,
        ];
    }

    private function playerTasks(array $missingRows): array
    {
        $players = [];

        foreach ($missingRows as $row) {
            foreach ($this->playersMissing($row) as $player) {
                $playerKey = (string) ($player['player_id'] ?? $player['player_name'] ?? $player['name'] ?? 'unknown');
                $players[$playerKey] ??= [
                    'player_id' => $player['player_id'] ?? null,
                    'player_name' => $player['player_name'] ?? $player['name'] ?? 'Unknown Player',
                    'priority' => 'low',
                    'missing_context' => [],
                    'missing_metrics' => [],
                    'recommended_sessions' => [],
                    'next_action' => '',
                ];

                $priority = $this->priorityForMissingRow($row);
                $players[$playerKey]['priority'] = $this->highestPriority([$players[$playerKey]['priority'], $priority]);

                if (($row['metric_key'] ?? null) === 'player_context') {
                    $players[$playerKey]['missing_context'] = array_values(array_unique([
                        ...$players[$playerKey]['missing_context'],
                        ...($player['missing_fields'] ?? []),
                    ]));
                } else {
                    $players[$playerKey]['missing_metrics'][] = [
                        'metric_key' => $row['metric_key'] ?? 'unknown',
                        'display_name' => $row['display_name'] ?? $row['metric_key'] ?? 'Metric',
                        'category' => $row['category'] ?? 'unknown',
                        'priority' => $priority,
                    ];
                }

                $players[$playerKey]['recommended_sessions'][] = $row['recommended_session'] ?? $this->recommendedSessionForMetric((string) ($row['metric_key'] ?? 'unknown'));
            }
        }

        return collect($players)
            ->map(function (array $player) {
                $player['missing_metrics'] = collect($player['missing_metrics'])
                    ->unique('metric_key')
                    ->values()
                    ->all();
                $player['recommended_sessions'] = array_values(array_unique(array_filter($player['recommended_sessions'])));
                $player['next_action'] = $this->playerNextAction($player);

                return $player;
            })
            ->sortByDesc(fn (array $player) => $this->priorityRank((string) ($player['priority'] ?? 'low')))
            ->values()
            ->all();
    }

    private function metricTasks(array $missingRows): array
    {
        return collect($missingRows)
            ->map(fn (array $row) => [
                'metric_key' => $row['metric_key'] ?? 'unknown',
                'display_name' => $row['display_name'] ?? $row['metric_key'] ?? 'Metric',
                'category' => $row['category'] ?? 'unknown',
                'priority' => $row['priority'] ?? $this->priorityForMissingRow($row),
                'missing_count' => $row['missing_count'] ?? 0,
                'eligible_count' => $row['eligible_count'] ?? $row['player_count'] ?? 0,
                'players' => $row['players'] ?? [],
                'recommended_session' => $row['recommended_session'] ?? $this->recommendedSessionForMetric((string) ($row['metric_key'] ?? 'unknown')),
            ])
            ->values()
            ->all();
    }

    private function completionTargets(array $sessions, array $benchmarkProfile, array $nextBestAction): array
    {
        $playerCount = (int) ($benchmarkProfile['player_count'] ?? 0);
        $withoutData = (int) ($benchmarkProfile['evidence']['players_without_benchmark_metrics'] ?? 0);
        $thisWeekSessions = collect($sessions)->filter(fn (array $session) => in_array($session['schedule_window'] ?? '', ['next_session', 'this_week'], true));

        return [
            'next_session' => [
                'target' => $nextBestAction['title'] === 'No Benchmark Collection Needed'
                    ? 'Keep benchmark baselines current as new sessions are logged.'
                    : 'Collect benchmark baselines for at least '.max(1, min(3, max($withoutData, $playerCount))).' players.',
                'minutes' => $nextBestAction['duration_minutes'] ?? 0,
            ],
            'this_week' => [
                'target' => 'Complete roster cleanup and priority baseball baselines for active players.',
                'minutes' => (int) $thisWeekSessions->sum(fn (array $session) => (int) ($session['duration_minutes'] ?? 0)),
            ],
            'this_month' => [
                'target' => 'Complete strength, athletic, mobility, long toss, and bullpen baselines.',
                'minutes' => array_sum(array_map(fn (array $session) => (int) ($session['duration_minutes'] ?? 0), $sessions)),
            ],
        ];
    }

    private function summary(array $benchmarkProfile, array $missingRows, array $nextBestAction): string
    {
        $playerCount = (int) ($benchmarkProfile['player_count'] ?? 0);
        $playersWithData = (int) ($benchmarkProfile['evidence']['players_with_benchmark_metrics'] ?? 0);
        $playersWithoutData = (int) ($benchmarkProfile['evidence']['players_without_benchmark_metrics'] ?? max(0, $playerCount - $playersWithData));
        $rosterCleanup = $this->rosterCleanupCount($missingRows);

        return sprintf(
            '%d of %d players have benchmark data. %d players need benchmark baselines. %d players need roster cleanup. Next action: %s.',
            $playersWithData,
            $playerCount,
            $playersWithoutData,
            $rosterCleanup,
            $nextBestAction['title'] ?? 'Benchmark collection'
        );
    }

    private function priorityLevel(array $missingRows, array $benchmarkProfile, ?array $decisionBrief): string
    {
        $decisionLevel = (string) ($decisionBrief['data_collection_priority']['level'] ?? 'none');
        $rawPlayerCount = (int) ($benchmarkProfile['player_count'] ?? 0);
        if ($rawPlayerCount <= 0 && empty($missingRows)) {
            return 'low';
        }

        $playerCount = max(1, $rawPlayerCount);
        $playersWithData = (int) ($benchmarkProfile['evidence']['players_with_benchmark_metrics'] ?? 0);
        $coverage = $playerCount > 0 ? $playersWithData / $playerCount : 0.0;
        $criticalRows = collect($missingRows)->filter(fn (array $row) => ($row['classification'] ?? null) === 'critical_missing_data');
        $sameCriticalBaseline = $criticalRows->contains(fn (array $row) => (int) ($row['missing_count'] ?? 0) >= 3);

        $levels = [$decisionLevel];

        if ($playerCount > 0 && $playersWithData === 0) {
            $levels[] = 'critical';
        } elseif ($coverage < 0.5 || $sameCriticalBaseline || in_array($decisionLevel, ['high', 'critical'], true)) {
            $levels[] = 'high';
        } elseif (collect($missingRows)->where('classification', 'supporting_missing_data')->count() >= 3) {
            $levels[] = 'medium';
        } elseif (! empty($missingRows)) {
            $levels[] = 'low';
        } else {
            $levels[] = 'none';
        }

        return $this->highestPriority($levels);
    }

    private function nextBestActionWhy(array $sessions, ?array $decisionBrief): string
    {
        $primaryFocus = $decisionBrief['primary_focus']['title'] ?? null;
        $sessionTitles = collect($sessions)->pluck('title')->implode(' and ');

        if ($primaryFocus && str_contains(strtolower((string) $primaryFocus), 'exit velocity')) {
            return $sessionTitles.' supports today\'s Exit Velocity / Power focus and improves benchmark confidence.';
        }

        if ($primaryFocus && (str_contains(strtolower((string) $primaryFocus), 'fastball') || str_contains(strtolower((string) $primaryFocus), 'long toss'))) {
            return $sessionTitles.' supports today\'s throwing focus and improves pitcher benchmark confidence.';
        }

        return $sessionTitles.' closes the highest-priority benchmark gaps first.';
    }

    private function sessionPriority(array $rows, array $benchmarkProfile, ?array $decisionBrief): string
    {
        $priorities = array_map(fn (array $row) => $this->priorityForMissingRow($row), $rows);
        $decisionLevel = (string) ($decisionBrief['data_collection_priority']['level'] ?? 'none');
        if (in_array($decisionLevel, ['high', 'critical'], true)) {
            $priorities[] = 'high';
        }

        if ((int) ($benchmarkProfile['metric_count'] ?? 0) === 0) {
            $priorities[] = 'critical';
        }

        return $this->highestPriority($priorities);
    }

    private function priorityForMissingRow(array $row): string
    {
        $classification = (string) ($row['classification'] ?? 'optional_missing_data');
        $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) ($row['metric_key'] ?? 'unknown'));
        $missingCount = (int) ($row['missing_count'] ?? 0);

        if (in_array($metricKey, ['player_context', 'player_benchmark_metrics'], true)) {
            return 'critical';
        }

        if ($classification === 'critical_missing_data') {
            return $missingCount >= 3 ? 'high' : 'medium';
        }

        if ($classification === 'supporting_missing_data') {
            return $missingCount >= 3 ? 'medium' : 'low';
        }

        return 'low';
    }

    private function recommendedSessionForMetric(string $metricKey): string
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);

        foreach (self::SESSION_DEFINITIONS as $definition) {
            if (in_array($metricKey, $definition['metrics'], true)) {
                return $definition['title'];
            }
        }

        if ($metricKey === 'player_benchmark_metrics') {
            return 'Benchmark Baseline';
        }

        return 'Benchmark Collection';
    }

    private function displayName(string $metricKey, ?string $fallback = null): string
    {
        return match ($metricKey) {
            'player_context' => 'Player Context',
            'player_benchmark_metrics' => 'Player Benchmark Metrics',
            'average_fastball_velocity' => 'Average Fastball Velocity',
            'max_fastball_velocity' => 'Max Fastball Velocity',
            'strike_percentage' => 'Strike Percentage',
            'average_exit_velocity' => 'Average Exit Velocity',
            'max_exit_velocity' => 'Max Exit Velocity',
            default => $fallback ?: ucwords(str_replace('_', ' ', $metricKey)),
        };
    }

    private function rosterFieldsForRows(array $rows): array
    {
        $fields = collect($rows)
            ->flatMap(fn (array $row) => collect($this->playersMissing($row))->flatMap(fn (array $player) => $player['missing_fields'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ! empty($fields)
            ? $fields
            : ['dob', 'position', 'height', 'weight', 'throws', 'bats', 'level'];
    }

    private function playersForRows(array $rows): array
    {
        return $this->uniquePlayers(collect($rows)->flatMap(fn (array $row) => $this->playersMissing($row))->values()->all());
    }

    private function uniquePlayers(array $players): array
    {
        $unique = [];

        foreach ($players as $player) {
            if (! is_array($player)) {
                continue;
            }

            $key = (string) ($player['player_id'] ?? $player['player_name'] ?? $player['name'] ?? 'unknown');
            $unique[$key] = [
                'player_id' => $player['player_id'] ?? null,
                'player_name' => $player['player_name'] ?? $player['name'] ?? 'Unknown Player',
                'name' => $player['name'] ?? $player['player_name'] ?? 'Unknown Player',
                'missing_fields' => array_values(array_unique($player['missing_fields'] ?? [])),
            ];
        }

        return array_values($unique);
    }

    private function playersMissing(array $row): array
    {
        return collect($row['players_missing'] ?? $row['players'] ?? [])
            ->map(fn ($player) => is_array($player) ? $player : [])
            ->filter(fn (array $player) => ! empty($player))
            ->values()
            ->all();
    }

    private function playerNextAction(array $player): string
    {
        if (! empty($player['missing_context'])) {
            return 'Complete roster cleanup first.';
        }

        $sessions = $player['recommended_sessions'] ?? [];

        return ! empty($sessions)
            ? 'Complete '.$sessions[0].'.'
            : 'Collect the next benchmark baseline.';
    }

    private function rosterCleanupCount(array $missingRows): int
    {
        $row = collect($missingRows)->first(fn (array $row) => ($row['metric_key'] ?? null) === 'player_context');

        return is_array($row) ? (int) ($row['missing_count'] ?? 0) : 0;
    }

    private function strongestClassification(string $current, string $incoming): string
    {
        return $this->strongerClassification($current, $incoming);
    }

    private function strongerClassification(string $current, string $incoming): string
    {
        $rank = [
            'optional_missing_data' => 1,
            'supporting_missing_data' => 2,
            'critical_missing_data' => 3,
        ];

        return ($rank[$incoming] ?? 1) > ($rank[$current] ?? 1) ? $incoming : $current;
    }

    private function highestPriority(array $priorities): string
    {
        $best = 'none';

        foreach ($priorities as $priority) {
            $priority = $this->normalizePriority((string) $priority);
            if ($this->priorityRank($priority) > $this->priorityRank($best)) {
                $best = $priority;
            }
        }

        return $best;
    }

    private function priorityRank(string $priority): int
    {
        return [
            'none' => 0,
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
        ][$this->normalizePriority($priority)] ?? 0;
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        return in_array($priority, ['none', 'low', 'medium', 'high', 'critical'], true) ? $priority : 'low';
    }
}
