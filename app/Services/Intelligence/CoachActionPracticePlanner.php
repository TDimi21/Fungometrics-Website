<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Throwable;

class CoachActionPracticePlanner
{
    private const BLOCK_DEFINITIONS = [
        'roster_cleanup_block' => [
            'title' => 'Roster Cleanup',
            'category' => 'roster',
            'priority' => 'critical',
            'duration_minutes' => 10,
            'description' => 'Confirm player DOB, position, height, weight, throws, bats, and level.',
            'why' => 'Clean player context improves age benchmarks, peer buckets, and coach recommendations.',
            'instructions' => [
                'Open each flagged player profile.',
                'Confirm DOB and position or role first.',
                'Add height, weight, throws, bats, and level when available.',
            ],
            'metrics_to_collect' => ['player_context'],
            'coach_role' => 'Coach confirms roster context and saves each profile.',
            'player_role' => 'Player confirms personal details if the coach needs help.',
        ],
        'exit_velocity_baseline_block' => [
            'title' => 'Exit Velocity Baseline',
            'category' => 'hitting',
            'priority' => 'high',
            'duration_minutes' => 18,
            'description' => 'Run a controlled EV baseline before higher-intent power work.',
            'why' => 'FMTRX needs average EV, max EV, and contact quality to evaluate hitter power.',
            'instructions' => [
                'Start with controlled barrel rounds.',
                'Record exit velocity for every scored swing.',
                'Tag contact quality, trajectory, and swing/miss.',
            ],
            'metrics_to_collect' => ['average_exit_velocity', 'max_exit_velocity', 'hard_hit_percentage', 'line_drive_percentage', 'hitter_swing_miss_percentage'],
            'coach_role' => 'Coach runs rounds and verifies each scored swing.',
            'player_role' => 'Players rotate through controlled and max-intent swings.',
        ],
        'power_development_block' => [
            'title' => 'Power Development',
            'category' => 'hitting',
            'priority' => 'high',
            'duration_minutes' => 25,
            'description' => 'Run intent-based power rounds after a controlled barrel baseline.',
            'why' => 'This trains exit velocity without separating power from usable contact.',
            'instructions' => [
                'Use short high-intent rounds with full recovery.',
                'Track average EV, top EV, and line-drive quality.',
                'Stop the round if contact quality collapses.',
            ],
            'metrics_to_collect' => ['average_exit_velocity', 'max_exit_velocity', 'line_drive_percentage'],
            'coach_role' => 'Coach controls intent, rest, and scoring quality.',
            'player_role' => 'Players swing with intent while keeping line-drive targets.',
        ],
        'bullpen_baseline_block' => [
            'title' => 'Bullpen Baseline',
            'category' => 'pitching',
            'priority' => 'high',
            'duration_minutes' => 22,
            'description' => 'Run a fastball-focused bullpen and record velocity plus strike percentage.',
            'why' => 'Pitching intelligence needs a mound baseline before command or transfer recommendations are trusted.',
            'instructions' => [
                'Use a consistent pitch count for each pitcher.',
                'Track average fastball velocity, max fastball velocity, and strike percentage.',
                'Record miss side when possible.',
            ],
            'metrics_to_collect' => ['average_fastball_velocity', 'max_fastball_velocity', 'strike_percentage'],
            'coach_role' => 'Coach calls targets and records velocity plus strike results.',
            'player_role' => 'Pitchers throw a consistent tracked fastball set.',
        ],
        'fastball_command_block' => [
            'title' => 'Fastball Command',
            'category' => 'pitching',
            'priority' => 'high',
            'duration_minutes' => 24,
            'description' => 'Run a fastball-only edge command block.',
            'why' => 'Command work targets strike percentage and miss patterns without hiding velocity loss.',
            'instructions' => [
                'Use edge targets and record strike percentage.',
                'Track miss side and execution quality.',
                'Keep velocity visible so command does not come from backing off too much.',
            ],
            'metrics_to_collect' => ['strike_percentage', 'average_fastball_velocity', 'max_fastball_velocity'],
            'coach_role' => 'Coach sets edge targets and reviews miss patterns.',
            'player_role' => 'Pitchers compete through a fastball-only command set.',
        ],
        'throwing_capacity_block' => [
            'title' => 'Long Toss / Weighted Ball',
            'category' => 'pitching',
            'priority' => 'medium',
            'duration_minutes' => 18,
            'description' => 'Collect throwing capacity without turning baseline work into extra volume.',
            'why' => 'Long toss and 5 oz velocity explain whether arm strength is transferring to the mound.',
            'instructions' => [
                'Record max long toss distance after a normal throwing build-up.',
                'Record latest 5 oz weighted-ball velocity when appropriate.',
                'Avoid adding extra high-intent throws only for testing.',
            ],
            'metrics_to_collect' => ['long_toss_max_distance', 'weighted_ball_5oz_velocity'],
            'coach_role' => 'Coach manages volume and records clean throwing baselines.',
            'player_role' => 'Players complete normal throwing build-up and report clean max efforts.',
        ],
        'strength_baseline_block' => [
            'title' => 'Strength Baseline',
            'category' => 'strength',
            'priority' => 'medium',
            'duration_minutes' => 30,
            'description' => 'Collect strength baselines that explain force production.',
            'why' => 'Strength data helps FMTRX explain whether physical output may be limiting velocity, EV, or durability.',
            'instructions' => [
                'Use safe testing loads and consistent standards.',
                'Record best valid scores only.',
                'Do not force max testing for players who are not ready.',
            ],
            'metrics_to_collect' => ['bench_press', 'squat', 'deadlift', 'pull_ups', 'pushups'],
            'coach_role' => 'Coach controls standards and safety for each test.',
            'player_role' => 'Players complete only appropriate tests with clean form.',
        ],
        'athletic_testing_block' => [
            'title' => 'Athletic Testing',
            'category' => 'athletic',
            'priority' => 'medium',
            'duration_minutes' => 22,
            'description' => 'Collect speed and jump baselines.',
            'why' => 'Athletic testing connects speed and explosiveness traits to baseball output.',
            'instructions' => [
                'Warm up fully before timing or jumping.',
                'Use the same measurement standard for every player.',
                'Record best valid attempt.',
            ],
            'metrics_to_collect' => ['forty_yard_dash', 'sixty_yard_dash', 'broad_jump', 'vertical_jump'],
            'coach_role' => 'Coach times, measures, and verifies valid attempts.',
            'player_role' => 'Players complete prepared sprint and jump attempts.',
        ],
        'mobility_screen_block' => [
            'title' => 'Mobility Screen',
            'category' => 'mobility',
            'priority' => 'medium',
            'duration_minutes' => 15,
            'description' => 'Screen shoulder, hip, and T-spine mobility.',
            'why' => 'Mobility data helps identify movement restrictions that can affect throwing, hitting, and recovery.',
            'instructions' => [
                'Screen shoulder, hip, and T-spine movement consistently.',
                'Record separate area scores when possible.',
                'Use results to guide warm-up and arm-care work.',
            ],
            'metrics_to_collect' => ['mobility_score', 'shoulder_mobility_score', 'hip_mobility_score', 't_spine_mobility_score'],
            'coach_role' => 'Coach records screen results and notes restrictions.',
            'player_role' => 'Players complete the screen at low intensity.',
        ],
        'review_debrief_block' => [
            'title' => 'Review / Coach Debrief',
            'category' => 'data_collection',
            'priority' => 'low',
            'duration_minutes' => 6,
            'description' => 'Review the collected data and set the next target.',
            'why' => 'The plan becomes more useful when the coach closes the loop after practice.',
            'instructions' => [
                'Review the top metric from the day.',
                'Note players who need follow-up work.',
                'Confirm which overflow block should happen next session.',
            ],
            'metrics_to_collect' => [],
            'coach_role' => 'Coach reviews results and records the next focus.',
            'player_role' => 'Players leave with one clear next target.',
        ],
    ];

    public function __construct(
        private readonly TeamIntelligenceService $teamIntelligenceService,
        private readonly DecisionEngine $decisionEngine,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
        private readonly BenchmarkTaskAssignmentService $benchmarkTaskAssignmentService,
    ) {
    }

    public function buildPracticePlanFromCoachActions(string $teamId, int $days = 365, array $options = []): array
    {
        $days = max(7, min(365, $days));
        $maxMinutes = max(30, min(180, (int) ($options['max_minutes'] ?? 90)));
        $teamSnapshot = $this->safeTeamSnapshot($teamId, $days);
        $decisionBrief = $this->safeDecisionBrief($teamId, $days, $teamSnapshot);
        $collectionPlan = $this->safeCollectionPlan($teamId, $days, $teamSnapshot, $decisionBrief);
        $taskAssignments = ($options['include_task_assignments'] ?? false) === true
            ? $this->safeTaskAssignments($teamId, $days)
            : null;
        $sourceActions = $this->sourceActions($decisionBrief, $collectionPlan, $taskAssignments);
        $candidateBlocks = $this->candidateBlocks($decisionBrief, $collectionPlan, $taskAssignments);
        $scheduled = $this->scheduleBlocks($candidateBlocks, $maxMinutes);
        $practiceBlocks = $scheduled['practice_blocks'];
        $nextSessionBlocks = $scheduled['next_session_blocks'];

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'plan_title' => $this->planTitle($decisionBrief, $practiceBlocks),
            'priority_focus' => $this->priorityFocus($decisionBrief),
            'estimated_total_minutes' => array_sum(array_map(fn (array $block) => (int) ($block['duration_minutes'] ?? 0), $practiceBlocks)),
            'max_minutes' => $maxMinutes,
            'practice_blocks' => $practiceBlocks,
            'next_session_blocks' => $nextSessionBlocks,
            'player_assignments' => $this->playerAssignments($practiceBlocks, $nextSessionBlocks, $taskAssignments),
            'coach_notes' => $this->coachNotes($decisionBrief, $collectionPlan, $practiceBlocks, $nextSessionBlocks),
            'data_collection_blocks' => $this->dataCollectionBlocks($practiceBlocks),
            'source_actions' => $sourceActions,
            'evidence' => [
                'days' => $days,
                'max_minutes' => $maxMinutes,
                'decision_brief_available' => $decisionBrief !== null,
                'collection_plan_available' => $collectionPlan !== null,
                'task_assignments_available' => $taskAssignments !== null,
                'team_snapshot_reused' => $teamSnapshot !== null,
                'source_action_count' => count($sourceActions),
                'candidate_block_count' => count($candidateBlocks),
                'scheduled_block_count' => count($practiceBlocks),
                'overflow_block_count' => count($nextSessionBlocks),
                'database_records_created' => false,
                'persistence' => 'read_only_payload_only',
            ],
        ];
    }

    private function safeTeamSnapshot(string $teamId, int $days): ?array
    {
        try {
            return $this->teamIntelligenceService->build($teamId, $days);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeDecisionBrief(string $teamId, int $days, ?array $teamSnapshot = null): ?array
    {
        try {
            if ($teamSnapshot !== null) {
                return $this->decisionEngine->buildTeamDecisionBriefFromSnapshot($teamId, $teamSnapshot, $days);
            }

            return $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeCollectionPlan(string $teamId, int $days, ?array $teamSnapshot = null, ?array $decisionBrief = null): ?array
    {
        try {
            $benchmarkProfile = $teamSnapshot['benchmark_profile'] ?? null;
            if (is_array($benchmarkProfile)) {
                return $this->benchmarkCollectionPlanner->buildTeamCollectionPlanFromData(
                    $teamId,
                    $days,
                    $benchmarkProfile,
                    $decisionBrief,
                );
            }

            return $this->benchmarkCollectionPlanner->buildTeamCollectionPlan($teamId, $days);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeTaskAssignments(string $teamId, int $days): ?array
    {
        try {
            return $this->benchmarkTaskAssignmentService->buildAssignableTasks($teamId, $days);
        } catch (Throwable) {
            return null;
        }
    }

    private function candidateBlocks(?array $decisionBrief, ?array $collectionPlan, ?array $taskAssignments): array
    {
        $blocks = [];
        $primaryFocus = strtolower((string) ($decisionBrief['primary_focus']['title'] ?? ''));

        foreach ($collectionPlan['collection_sessions'] ?? [] as $session) {
            if (! is_array($session)) {
                continue;
            }

            $this->addBlock($blocks, $this->blockFromCollectionSession($session));
        }

        foreach ([
            ...($taskAssignments['team_tasks'] ?? []),
            ...($taskAssignments['assignable_tasks'] ?? []),
        ] as $task) {
            if (! is_array($task)) {
                continue;
            }

            $this->addBlock($blocks, $this->blockFromBenchmarkTask($task));
        }

        if (str_contains($primaryFocus, 'exit velocity') || str_contains($primaryFocus, 'power')) {
            $this->addBlock($blocks, $this->makeBlock('exit_velocity_baseline_block', [
                'priority' => 'high',
                'source' => 'decision_brief',
            ]));
            $this->addBlock($blocks, $this->makeBlock('power_development_block', [
                'priority' => 'high',
                'source' => 'coach_action',
            ]));
        } elseif (str_contains($primaryFocus, 'barrel')) {
            $this->addBlock($blocks, $this->makeBlock('exit_velocity_baseline_block', [
                'priority' => 'high',
                'source' => 'decision_brief',
                'description' => 'Run a line-drive baseline before barrel-control rounds.',
                'why' => 'Barrel control needs contact quality and trajectory data, not only EV.',
            ]));
            $this->addBlock($blocks, $this->makeBlock('power_development_block', [
                'priority' => 'medium',
                'source' => 'coach_action',
                'title' => 'Barrel Control Rounds',
                'description' => 'Run middle-middle line-drive rounds with contact quality scoring.',
                'why' => 'This turns the barrel-control recommendation into a scored practice block.',
            ]));
        } elseif (str_contains($primaryFocus, 'fastball') || str_contains($primaryFocus, 'command')) {
            $this->addBlock($blocks, $this->makeBlock('bullpen_baseline_block', [
                'priority' => 'high',
                'source' => 'decision_brief',
            ]));
            $this->addBlock($blocks, $this->makeBlock('fastball_command_block', [
                'priority' => 'high',
                'source' => 'coach_action',
            ]));
        } elseif (str_contains($primaryFocus, 'long toss') || str_contains($primaryFocus, 'transfer')) {
            $this->addBlock($blocks, $this->makeBlock('throwing_capacity_block', [
                'priority' => 'high',
                'source' => 'decision_brief',
            ]));
        } elseif (str_contains($primaryFocus, 'strength') || str_contains($primaryFocus, 'lower body')) {
            $this->addBlock($blocks, $this->makeBlock('strength_baseline_block', [
                'priority' => 'high',
                'source' => 'decision_brief',
            ]));
            $this->addBlock($blocks, $this->makeBlock('power_development_block', [
                'priority' => 'medium',
                'source' => 'coach_action',
                'description' => 'Use medicine ball throws, jumps, and rotational power work.',
                'why' => 'Lower-body power should transfer into throwing and hitting outputs.',
            ]));
        } elseif (str_contains($primaryFocus, 'mobility') || str_contains($primaryFocus, 'arm care')) {
            $this->addBlock($blocks, $this->makeBlock('mobility_screen_block', [
                'priority' => 'high',
                'source' => 'decision_brief',
            ]));
        }

        if (empty($blocks) && $collectionPlan !== null && ($collectionPlan['next_best_action']['title'] ?? '') !== 'No Benchmark Collection Needed') {
            $this->addBlock($blocks, $this->blockFromNextBestAction($collectionPlan['next_best_action'] ?? []));
        }

        if (! empty($blocks)) {
            $this->addBlock($blocks, $this->makeBlock('review_debrief_block', ['source' => 'coach_action']));
        }

        return $this->orderedBlocks(array_values($blocks));
    }

    private function blockFromCollectionSession(array $session): array
    {
        $type = strtolower((string) ($session['collection_type'] ?? ''));
        $title = strtolower((string) ($session['title'] ?? ''));
        $key = match (true) {
            $type === 'roster' || str_contains($title, 'roster') => 'roster_cleanup_block',
            $type === 'hitting' || str_contains($title, 'exit velocity') => 'exit_velocity_baseline_block',
            $type === 'pitching' || str_contains($title, 'bullpen') => 'bullpen_baseline_block',
            $type === 'throwing' || str_contains($title, 'long toss') || str_contains($title, 'weighted') => 'throwing_capacity_block',
            $type === 'strength' || str_contains($title, 'strength') => 'strength_baseline_block',
            $type === 'athletic' || str_contains($title, 'athletic') => 'athletic_testing_block',
            $type === 'mobility' || str_contains($title, 'mobility') => 'mobility_screen_block',
            default => 'roster_cleanup_block',
        };

        return $this->makeBlock($key, [
            'priority' => $session['priority'] ?? null,
            'duration_minutes' => $session['duration_minutes'] ?? null,
            'description' => $session['description'] ?? null,
            'why' => $session['why'] ?? null,
            'instructions' => $session['coach_instructions'] ?? null,
            'metrics_to_collect' => $session['metric_keys'] ?? $session['metrics'] ?? null,
            'players' => $session['players'] ?? null,
            'source' => 'collection_plan',
        ]);
    }

    private function blockFromBenchmarkTask(array $task): array
    {
        $taskType = (string) ($task['task_type'] ?? '');
        $key = match ($taskType) {
            'roster_cleanup' => 'roster_cleanup_block',
            'exit_velocity_baseline' => 'exit_velocity_baseline_block',
            'bullpen_baseline' => 'bullpen_baseline_block',
            'long_toss_weighted_ball' => 'throwing_capacity_block',
            'strength_baseline' => 'strength_baseline_block',
            'athletic_testing' => 'athletic_testing_block',
            'mobility_screen' => 'mobility_screen_block',
            default => 'review_debrief_block',
        };

        $players = [];
        if (! empty($task['assigned_to_player_id']) || ! empty($task['assigned_to_player_name'])) {
            $players[] = [
                'player_id' => $task['assigned_to_player_id'] ?? null,
                'player_name' => $task['assigned_to_player_name'] ?? $task['assigned_to_player_id'] ?? 'Unknown Player',
            ];
        }

        return $this->makeBlock($key, [
            'priority' => $task['priority'] ?? null,
            'duration_minutes' => $task['estimated_minutes'] ?? null,
            'description' => $task['description'] ?? null,
            'why' => $task['coach_notes'] ?? null,
            'instructions' => $task['instructions'] ?? null,
            'metrics_to_collect' => $this->metricKeys($task['metrics'] ?? []),
            'players' => ! empty($players) ? $players : ($task['players'] ?? null),
            'source' => 'benchmark_task',
        ]);
    }

    private function blockFromNextBestAction(array $action): array
    {
        $title = strtolower((string) ($action['title'] ?? ''));
        $key = match (true) {
            str_contains($title, 'roster') => 'roster_cleanup_block',
            str_contains($title, 'exit velocity') => 'exit_velocity_baseline_block',
            str_contains($title, 'bullpen') => 'bullpen_baseline_block',
            str_contains($title, 'long toss') || str_contains($title, 'weighted') => 'throwing_capacity_block',
            str_contains($title, 'strength') => 'strength_baseline_block',
            str_contains($title, 'athletic') => 'athletic_testing_block',
            str_contains($title, 'mobility') => 'mobility_screen_block',
            default => 'review_debrief_block',
        };

        return $this->makeBlock($key, [
            'priority' => $action['priority'] ?? null,
            'duration_minutes' => $action['duration_minutes'] ?? null,
            'why' => $action['why'] ?? null,
            'instructions' => $action['coach_instructions'] ?? null,
            'metrics_to_collect' => $action['metrics'] ?? null,
            'players' => $action['players'] ?? null,
            'source' => 'collection_plan',
        ]);
    }

    private function makeBlock(string $key, array $overrides = []): array
    {
        $definition = self::BLOCK_DEFINITIONS[$key] ?? self::BLOCK_DEFINITIONS['review_debrief_block'];
        $metrics = $overrides['metrics_to_collect'] ?? $definition['metrics_to_collect'];

        return [
            'block_id' => null,
            'temporary_key' => $key,
            'title' => (string) ($overrides['title'] ?? $definition['title']),
            'category' => (string) ($overrides['category'] ?? $definition['category']),
            'priority' => $this->normalizePriority((string) ($overrides['priority'] ?? $definition['priority'])),
            'duration_minutes' => (int) ($overrides['duration_minutes'] ?? $definition['duration_minutes']),
            'description' => (string) ($overrides['description'] ?? $definition['description']),
            'why' => (string) ($overrides['why'] ?? $definition['why']),
            'instructions' => array_values(array_filter($overrides['instructions'] ?? $definition['instructions'])),
            'metrics_to_collect' => $this->metricKeys($metrics),
            'players' => $this->players($overrides['players'] ?? []),
            'coach_role' => (string) ($overrides['coach_role'] ?? $definition['coach_role']),
            'player_role' => (string) ($overrides['player_role'] ?? $definition['player_role']),
            'source' => (string) ($overrides['source'] ?? 'coach_action'),
            'sources' => array_values(array_unique([(string) ($overrides['source'] ?? 'coach_action')])),
        ];
    }

    private function addBlock(array &$blocks, ?array $block): void
    {
        if (! is_array($block) || empty($block['temporary_key'])) {
            return;
        }

        $key = (string) $block['temporary_key'];
        if (! isset($blocks[$key])) {
            $blocks[$key] = $block;

            return;
        }

        $existing = $blocks[$key];
        $existing['priority'] = $this->highestPriority([$existing['priority'] ?? 'low', $block['priority'] ?? 'low']);
        $existing['duration_minutes'] = max((int) ($existing['duration_minutes'] ?? 0), (int) ($block['duration_minutes'] ?? 0));
        $existing['metrics_to_collect'] = array_values(array_unique([
            ...($existing['metrics_to_collect'] ?? []),
            ...($block['metrics_to_collect'] ?? []),
        ]));
        $existing['instructions'] = array_values(array_unique([
            ...($existing['instructions'] ?? []),
            ...($block['instructions'] ?? []),
        ]));
        $existing['players'] = $this->uniquePlayers([
            ...($existing['players'] ?? []),
            ...($block['players'] ?? []),
        ]);
        $existing['sources'] = array_values(array_unique([
            ...($existing['sources'] ?? [$existing['source'] ?? 'coach_action']),
            ...($block['sources'] ?? [$block['source'] ?? 'coach_action']),
        ]));
        $existing['source'] = in_array('coach_action', $existing['sources'], true)
            ? 'coach_action'
            : (string) ($existing['source'] ?? $block['source'] ?? 'coach_action');

        $blocks[$key] = $existing;
    }

    private function orderedBlocks(array $blocks): array
    {
        $rank = [
            'roster_cleanup_block' => 0,
            'exit_velocity_baseline_block' => 1,
            'bullpen_baseline_block' => 1,
            'fastball_command_block' => 2,
            'power_development_block' => 2,
            'throwing_capacity_block' => 3,
            'mobility_screen_block' => 4,
            'strength_baseline_block' => 5,
            'athletic_testing_block' => 6,
            'review_debrief_block' => 99,
        ];

        usort($blocks, function (array $a, array $b) use ($rank) {
            $aKey = (string) ($a['temporary_key'] ?? '');
            $bKey = (string) ($b['temporary_key'] ?? '');

            return ($rank[$aKey] ?? 50) <=> ($rank[$bKey] ?? 50)
                ?: ($this->priorityRank((string) ($b['priority'] ?? 'low')) <=> $this->priorityRank((string) ($a['priority'] ?? 'low')))
                ?: strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return array_values(array_map(function (array $block, int $index) {
            $block['sequence'] = $index + 1;

            return $block;
        }, $blocks, array_keys($blocks)));
    }

    private function scheduleBlocks(array $blocks, int $maxMinutes): array
    {
        $practiceBlocks = [];
        $nextSessionBlocks = [];
        $minutes = 0;

        foreach ($blocks as $block) {
            $duration = (int) ($block['duration_minutes'] ?? 0);
            if ($duration <= 0) {
                continue;
            }

            if (($minutes + $duration) <= $maxMinutes || empty($practiceBlocks)) {
                $block['schedule_window'] = 'today';
                $practiceBlocks[] = $block;
                $minutes += $duration;
            } else {
                $block['schedule_window'] = 'next_session';
                $nextSessionBlocks[] = $block;
            }
        }

        return [
            'practice_blocks' => array_values($practiceBlocks),
            'next_session_blocks' => array_values(array_map(function (array $block, int $index) {
                $block['sequence'] = $index + 1;

                return $block;
            }, $nextSessionBlocks, array_keys($nextSessionBlocks))),
        ];
    }

    private function sourceActions(?array $decisionBrief, ?array $collectionPlan, ?array $taskAssignments): array
    {
        $actions = [];
        $primary = $decisionBrief['primary_focus'] ?? null;
        if (is_array($primary) && ! empty($primary)) {
            $actions[] = [
                'source' => 'decision_brief',
                'title' => $primary['title'] ?? 'Primary Focus',
                'priority' => $decisionBrief['confidence'] ?? 'medium',
                'category' => $primary['category'] ?? 'practice',
                'why' => $primary['why'] ?? null,
                'action' => $primary['action'] ?? null,
                'expected_gain' => $decisionBrief['expected_gain'] ?? $primary['expected_gain'] ?? null,
            ];
        }

        $next = $collectionPlan['next_best_action'] ?? null;
        if (is_array($next) && ($next['title'] ?? '') !== 'No Benchmark Collection Needed') {
            $actions[] = [
                'source' => 'collection_plan',
                'title' => $next['title'] ?? 'Benchmark Collection',
                'priority' => $next['priority'] ?? $collectionPlan['priority_level'] ?? 'low',
                'category' => 'data_collection',
                'why' => $next['why'] ?? null,
                'action' => implode(' ', $next['coach_instructions'] ?? []),
                'metrics' => $next['metrics'] ?? [],
                'players' => $this->players($next['players'] ?? []),
            ];
        }

        foreach (array_slice($collectionPlan['collection_sessions'] ?? [], 0, 6) as $session) {
            if (! is_array($session)) {
                continue;
            }

            $actions[] = [
                'source' => 'collection_plan',
                'title' => $session['title'] ?? 'Collection Session',
                'priority' => $session['priority'] ?? 'low',
                'category' => $session['collection_type'] ?? 'data_collection',
                'why' => $session['why'] ?? null,
                'action' => $session['description'] ?? null,
                'metrics' => $session['metric_keys'] ?? $session['metrics'] ?? [],
                'players' => $this->players($session['players'] ?? []),
            ];
        }

        foreach (array_slice($taskAssignments['team_tasks'] ?? [], 0, 4) as $task) {
            if (! is_array($task)) {
                continue;
            }

            $actions[] = [
                'source' => 'benchmark_task',
                'title' => $task['title'] ?? 'Benchmark Task',
                'priority' => $task['priority'] ?? 'low',
                'category' => $task['task_type'] ?? 'data_collection',
                'why' => $task['coach_notes'] ?? null,
                'action' => $task['description'] ?? null,
                'metrics' => $this->metricKeys($task['metrics'] ?? []),
                'players' => $this->players($task['players'] ?? []),
            ];
        }

        return collect($actions)
            ->filter(fn (array $action) => ! empty($action['title']))
            ->unique(fn (array $action) => ($action['source'] ?? '').':'.($action['title'] ?? ''))
            ->values()
            ->all();
    }

    private function playerAssignments(array $practiceBlocks, array $nextSessionBlocks, ?array $taskAssignments): array
    {
        $assignments = [];

        foreach ([...$practiceBlocks, ...$nextSessionBlocks] as $block) {
            foreach ($block['players'] ?? [] as $player) {
                $key = (string) ($player['player_id'] ?? $player['player_name'] ?? $player['name'] ?? 'unknown');
                if ($key === 'unknown') {
                    continue;
                }

                $assignments[$key] ??= [
                    'player_id' => $player['player_id'] ?? null,
                    'player_name' => $player['player_name'] ?? $player['name'] ?? $key,
                    'blocks' => [],
                    'metrics' => [],
                    'estimated_minutes' => 0,
                ];
                $assignments[$key]['blocks'][] = $block['title'] ?? 'Practice Block';
                $assignments[$key]['metrics'] = array_values(array_unique([
                    ...$assignments[$key]['metrics'],
                    ...($block['metrics_to_collect'] ?? []),
                ]));
                $assignments[$key]['estimated_minutes'] += (int) ($block['duration_minutes'] ?? 0);
            }
        }

        foreach ($taskAssignments['player_tasks'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }

            $key = (string) ($group['player_id'] ?? $group['player_name'] ?? 'unknown');
            if ($key === 'unknown') {
                continue;
            }

            $assignments[$key] ??= [
                'player_id' => $group['player_id'] ?? null,
                'player_name' => $group['player_name'] ?? $key,
                'blocks' => [],
                'metrics' => [],
                'estimated_minutes' => 0,
            ];
            foreach ($group['tasks'] ?? [] as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $assignments[$key]['blocks'][] = $task['title'] ?? 'Benchmark Task';
                $assignments[$key]['metrics'] = array_values(array_unique([
                    ...$assignments[$key]['metrics'],
                    ...$this->metricKeys($task['metrics'] ?? []),
                ]));
                $assignments[$key]['estimated_minutes'] += (int) ($task['estimated_minutes'] ?? 0);
            }
        }

        return collect($assignments)
            ->map(function (array $assignment) {
                $assignment['blocks'] = array_values(array_unique($assignment['blocks']));
                $assignment['metrics'] = array_values(array_unique($assignment['metrics']));

                return $assignment;
            })
            ->values()
            ->all();
    }

    private function coachNotes(?array $decisionBrief, ?array $collectionPlan, array $practiceBlocks, array $nextSessionBlocks): array
    {
        $notes = [];
        $focus = $this->priorityFocus($decisionBrief);
        if ($focus !== '') {
            $notes[] = 'Primary focus: '.$focus.'.';
        }

        if (! empty($practiceBlocks)) {
            $notes[] = 'This is a read-only suggested plan; no practice planner records were created.';
        }

        if (! empty($nextSessionBlocks)) {
            $notes[] = count($nextSessionBlocks).' lower-priority block(s) were moved to the next session to respect the time cap.';
        }

        $priority = $collectionPlan['priority_level'] ?? null;
        if ($priority && $priority !== 'none') {
            $notes[] = 'Benchmark collection priority: '.$this->human($priority).'.';
        }

        return array_values(array_unique($notes));
    }

    private function dataCollectionBlocks(array $practiceBlocks): array
    {
        return collect($practiceBlocks)
            ->filter(fn (array $block) => in_array($block['source'] ?? '', ['collection_plan', 'benchmark_task'], true)
                || in_array($block['temporary_key'] ?? '', [
                    'roster_cleanup_block',
                    'exit_velocity_baseline_block',
                    'bullpen_baseline_block',
                    'throwing_capacity_block',
                    'strength_baseline_block',
                    'athletic_testing_block',
                    'mobility_screen_block',
                ], true))
            ->values()
            ->all();
    }

    private function planTitle(?array $decisionBrief, array $practiceBlocks): string
    {
        $focus = $this->priorityFocus($decisionBrief);
        if ($focus !== '') {
            return $focus.' Practice Plan';
        }

        if (! empty($practiceBlocks)) {
            return 'FMTRX Coach Action Practice Plan';
        }

        return 'No Practice Plan Needed';
    }

    private function priorityFocus(?array $decisionBrief): string
    {
        return trim((string) ($decisionBrief['primary_focus']['title'] ?? ''));
    }

    private function metricKeys(mixed $metrics): array
    {
        return collect(is_array($metrics) ? $metrics : [])
            ->map(function ($metric) {
                if (is_array($metric)) {
                    $metric = $metric['metric_key'] ?? $metric['display_name'] ?? null;
                }

                return $metric ? BenchmarkDefinitions::normalizeMetricKey((string) $metric) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function players(mixed $players): array
    {
        return $this->uniquePlayers(collect(is_array($players) ? $players : [])
            ->map(function ($player) {
                if (! is_array($player)) {
                    return null;
                }

                $name = $player['player_name'] ?? $player['name'] ?? $player['assigned_to_player_name'] ?? $player['player_id'] ?? null;
                if (! $name && empty($player['player_id'])) {
                    return null;
                }

                return [
                    'player_id' => $player['player_id'] ?? $player['id'] ?? $player['assigned_to_player_id'] ?? null,
                    'player_name' => $name ?? 'Unknown Player',
                    'name' => $name ?? 'Unknown Player',
                ];
            })
            ->filter()
            ->values()
            ->all());
    }

    private function uniquePlayers(array $players): array
    {
        $unique = [];

        foreach ($players as $player) {
            if (! is_array($player)) {
                continue;
            }

            $key = (string) ($player['player_id'] ?? $player['player_name'] ?? $player['name'] ?? '');
            if ($key === '' || isset($unique[$key])) {
                continue;
            }

            $unique[$key] = [
                'player_id' => $player['player_id'] ?? null,
                'player_name' => $player['player_name'] ?? $player['name'] ?? $key,
                'name' => $player['name'] ?? $player['player_name'] ?? $key,
            ];
        }

        return array_values($unique);
    }

    private function highestPriority(array $priorities): string
    {
        $best = 'low';

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
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
        ][$this->normalizePriority($priority)] ?? 1;
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));
        if ($priority === 'moderate') {
            return 'medium';
        }

        return in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : 'low';
    }

    private function human(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
