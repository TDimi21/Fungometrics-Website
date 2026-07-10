<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class BenchmarkTaskAssignmentService
{
    private const TASK_DEFINITIONS = [
        'roster_cleanup' => [
            'title' => 'Complete Roster Profile',
            'description' => 'Add DOB, position, height, weight, throws, bats, and level.',
            'estimated_minutes' => 5,
            'metric_keys' => ['player_context'],
            'instructions' => [
                'Confirm date of birth.',
                'Confirm position or role.',
                'Confirm height, weight, throws, bats, and level when available.',
            ],
        ],
        'exit_velocity_baseline' => [
            'title' => 'Complete Exit Velocity Baseline',
            'description' => 'Score an exit velocity baseline so FMTRX can evaluate power and usable contact.',
            'estimated_minutes' => 12,
            'metric_keys' => ['average_exit_velocity', 'max_exit_velocity', 'hard_hit_percentage', 'line_drive_percentage', 'hitter_swing_miss_percentage'],
            'instructions' => [
                'Run a controlled round before max intent.',
                'Record EV for every scored swing.',
                'Tag contact quality, trajectory, and swing/miss.',
            ],
        ],
        'bullpen_baseline' => [
            'title' => 'Complete Bullpen Baseline',
            'description' => 'Score a fastball-focused bullpen baseline so FMTRX can evaluate velocity and command.',
            'estimated_minutes' => 12,
            'metric_keys' => ['average_fastball_velocity', 'max_fastball_velocity', 'strike_percentage'],
            'instructions' => [
                'Use a consistent pitch count.',
                'Record fastball velocity.',
                'Track strike percentage and miss side.',
            ],
        ],
        'long_toss_weighted_ball' => [
            'title' => 'Complete Long Toss / Weighted Ball Baseline',
            'description' => 'Collect long toss distance and 5 oz weighted-ball velocity for throwing development context.',
            'estimated_minutes' => 15,
            'metric_keys' => ['long_toss_max_distance', 'weighted_ball_5oz_velocity'],
            'instructions' => [
                'Record long toss max distance after normal throwing build-up.',
                'Record latest 5 oz weighted-ball velocity.',
                'Avoid adding extra high-intent throws just to collect data.',
            ],
        ],
        'strength_baseline' => [
            'title' => 'Complete Strength Baseline',
            'description' => 'Collect strength baselines so FMTRX can connect force production to baseball performance.',
            'estimated_minutes' => 20,
            'metric_keys' => ['bench_press', 'squat', 'deadlift', 'pull_ups', 'pushups'],
            'instructions' => [
                'Use safe testing standards.',
                'Record best valid score for each lift or movement.',
                'Do not force max testing if the player is not ready.',
            ],
        ],
        'athletic_testing' => [
            'title' => 'Complete Athletic Testing',
            'description' => 'Collect speed and jump baselines for athletic benchmark context.',
            'estimated_minutes' => 15,
            'metric_keys' => ['forty_yard_dash', 'sixty_yard_dash', 'broad_jump', 'vertical_jump'],
            'instructions' => [
                'Warm up fully before testing.',
                'Use the same timing and measurement standard for each player.',
                'Record best valid attempt.',
            ],
        ],
        'mobility_screen' => [
            'title' => 'Complete Mobility Screen',
            'description' => 'Collect mobility baselines so FMTRX can identify movement restrictions.',
            'estimated_minutes' => 10,
            'metric_keys' => ['mobility_score', 'shoulder_mobility_score', 'hip_mobility_score', 't_spine_mobility_score'],
            'instructions' => [
                'Screen shoulder, hip, and T-spine mobility.',
                'Record area-specific scores when possible.',
                'Use results to guide warm-up and arm-care blocks.',
            ],
        ],
    ];

    public function __construct(
        private readonly BenchmarkCollectionPlanner $collectionPlanner,
    ) {
    }

    public function buildAssignableTasks(string $teamId, int $days = 365): array
    {
        $days = max(7, min(365, $days));
        $plan = $this->collectionPlanner->buildTeamCollectionPlan($teamId, $days);
        $teamTasks = $this->teamTasks($teamId, $plan);
        $playerTaskGroups = $this->playerTaskGroups($teamId, $plan);
        $assignableTasks = collect($playerTaskGroups)
            ->flatMap(fn (array $player) => $player['tasks'])
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'source' => 'benchmark_collection_plan',
            'task_count' => count($assignableTasks) + count($teamTasks),
            'player_task_count' => count($assignableTasks),
            'team_task_count' => count($teamTasks),
            'priority_level' => $this->highestPriority([
                $plan['priority_level'] ?? 'low',
                ...array_column($assignableTasks, 'priority'),
                ...array_column($teamTasks, 'priority'),
            ]),
            'assignable_tasks' => $assignableTasks,
            'team_tasks' => $teamTasks,
            'player_tasks' => $playerTaskGroups,
            'evidence' => [
                'days' => $days,
                'collection_plan_priority' => $plan['priority_level'] ?? null,
                'collection_session_count' => count($plan['collection_sessions'] ?? []),
                'collection_player_task_count' => count($plan['player_tasks'] ?? []),
                'collection_metric_task_count' => count($plan['metric_tasks'] ?? []),
                'persistence' => 'dry_run_payload_only',
                'database_records_created' => false,
            ],
        ];
    }

    private function teamTasks(string $teamId, array $plan): array
    {
        return collect($plan['collection_sessions'] ?? [])
            ->map(function (array $session, int $index) use ($teamId) {
                $type = $this->taskTypeFromSession($session);

                return [
                    'task_id' => null,
                    'temporary_key' => 'benchmark_team_'.$type.'_'.($index + 1),
                    'title' => 'Run '.$this->string($session['title'] ?? 'Benchmark Collection'),
                    'description' => $this->string($session['description'] ?? 'Collect benchmark data for the team.'),
                    'priority' => $this->normalizePriority((string) ($session['priority'] ?? 'low')),
                    'task_type' => $type,
                    'assigned_to_player_id' => null,
                    'assigned_to_player_name' => null,
                    'team_id' => $teamId,
                    'estimated_minutes' => (int) ($session['duration_minutes'] ?? 0),
                    'metrics' => array_values(array_unique($session['metric_keys'] ?? $session['metrics'] ?? [])),
                    'missing_fields' => $type === 'roster_cleanup' ? array_values(array_unique($session['metrics'] ?? [])) : [],
                    'instructions' => array_values($session['coach_instructions'] ?? []),
                    'coach_notes' => $this->string($session['why'] ?? ''),
                    'status' => 'draft',
                    'due_window' => $this->dueWindow((string) ($session['schedule_window'] ?? 'this_week')),
                    'players' => $session['players'] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    private function playerTaskGroups(string $teamId, array $plan): array
    {
        return collect($plan['player_tasks'] ?? [])
            ->map(function (array $playerTask) use ($teamId, $plan) {
                $tasks = [];
                $playerId = (string) ($playerTask['player_id'] ?? '');
                $playerName = $this->string($playerTask['player_name'] ?? $playerTask['name'] ?? 'Unknown Player');

                if (! empty($playerTask['missing_context'])) {
                    $tasks[] = $this->makePlayerTask(
                        $teamId,
                        $playerId,
                        $playerName,
                        'roster_cleanup',
                        [],
                        array_values(array_unique($playerTask['missing_context'] ?? [])),
                        'critical',
                        $plan,
                    );
                }

                $metricsByType = [];
                foreach ($playerTask['missing_metrics'] ?? [] as $metric) {
                    if (! is_array($metric)) {
                        continue;
                    }

                    $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) ($metric['metric_key'] ?? 'unknown'));
                    $type = $this->taskTypeForMetric($metricKey);
                    if ($type === null) {
                        continue;
                    }

                    $metricsByType[$type][] = [
                        'metric_key' => $metricKey,
                        'display_name' => $metric['display_name'] ?? $this->displayName($metricKey),
                        'category' => $metric['category'] ?? 'unknown',
                        'priority' => $this->normalizePriority((string) ($metric['priority'] ?? $playerTask['priority'] ?? 'low')),
                    ];
                }

                foreach ($this->orderedTaskTypes(array_keys($metricsByType), $plan) as $type) {
                    $metrics = collect($metricsByType[$type] ?? [])
                        ->unique('metric_key')
                        ->values()
                        ->all();
                    if (empty($metrics)) {
                        continue;
                    }

                    $tasks[] = $this->makePlayerTask(
                        $teamId,
                        $playerId,
                        $playerName,
                        $type,
                        $metrics,
                        [],
                        $this->highestPriority(array_column($metrics, 'priority')),
                        $plan,
                    );
                }

                return [
                    'player_id' => $playerId !== '' ? $playerId : null,
                    'player_name' => $playerName,
                    'priority' => $this->highestPriority(array_column($tasks, 'priority')),
                    'task_count' => count($tasks),
                    'tasks' => $tasks,
                ];
            })
            ->filter(fn (array $player) => ($player['task_count'] ?? 0) > 0)
            ->sort(function (array $a, array $b) {
                return ($this->priorityRank((string) ($b['priority'] ?? 'low')) <=> $this->priorityRank((string) ($a['priority'] ?? 'low')))
                    ?: strcmp((string) ($a['player_name'] ?? ''), (string) ($b['player_name'] ?? ''));
            })
            ->values()
            ->all();
    }

    private function makePlayerTask(
        string $teamId,
        string $playerId,
        string $playerName,
        string $type,
        array $metrics,
        array $missingFields,
        string $priority,
        array $plan,
    ): array {
        $definition = self::TASK_DEFINITIONS[$type];
        $priority = $this->normalizePriority($priority);

        if ($type === 'roster_cleanup') {
            $priority = 'critical';
        }

        return [
            'task_id' => null,
            'temporary_key' => $this->temporaryKey($type, $playerId, $playerName),
            'title' => $definition['title'],
            'description' => $definition['description'],
            'priority' => $priority,
            'task_type' => $type,
            'assigned_to_player_id' => $playerId !== '' ? $playerId : null,
            'assigned_to_player_name' => $playerName,
            'team_id' => $teamId,
            'estimated_minutes' => (int) $definition['estimated_minutes'],
            'metrics' => $metrics,
            'missing_fields' => array_values(array_unique($missingFields)),
            'instructions' => $definition['instructions'],
            'coach_notes' => $this->coachNotes($type, $plan),
            'status' => 'draft',
            'due_window' => $this->dueWindowForType($type, $plan),
        ];
    }

    private function orderedTaskTypes(array $types, array $plan): array
    {
        $primaryFocus = strtolower((string) ($plan['evidence']['decision_primary_focus'] ?? ''));
        $rank = [
            'roster_cleanup' => 0,
            'exit_velocity_baseline' => str_contains($primaryFocus, 'exit velocity') ? 1 : 4,
            'bullpen_baseline' => str_contains($primaryFocus, 'fastball') || str_contains($primaryFocus, 'command') ? 1 : 4,
            'long_toss_weighted_ball' => str_contains($primaryFocus, 'long toss') || str_contains($primaryFocus, 'transfer') ? 1 : 4,
            'strength_baseline' => 5,
            'athletic_testing' => 6,
            'mobility_screen' => 7,
        ];

        usort($types, fn (string $a, string $b) => ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99));

        return $types;
    }

    private function taskTypeForMetric(string $metricKey): ?string
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);

        if ($metricKey === 'player_context') {
            return 'roster_cleanup';
        }

        if ($metricKey === 'player_benchmark_metrics') {
            return null;
        }

        foreach (self::TASK_DEFINITIONS as $type => $definition) {
            if (in_array($metricKey, $definition['metric_keys'], true)) {
                return $type;
            }
        }

        return null;
    }

    private function taskTypeFromSession(array $session): string
    {
        $type = strtolower((string) ($session['collection_type'] ?? ''));
        $title = strtolower((string) ($session['title'] ?? ''));

        return match (true) {
            $type === 'roster' || str_contains($title, 'roster') => 'roster_cleanup',
            $type === 'hitting' || str_contains($title, 'exit velocity') => 'exit_velocity_baseline',
            $type === 'pitching' || str_contains($title, 'bullpen') => 'bullpen_baseline',
            $type === 'throwing' || str_contains($title, 'long toss') || str_contains($title, 'weighted') => 'long_toss_weighted_ball',
            $type === 'strength' || str_contains($title, 'strength') => 'strength_baseline',
            $type === 'athletic' || str_contains($title, 'athletic') => 'athletic_testing',
            $type === 'mobility' || str_contains($title, 'mobility') => 'mobility_screen',
            default => 'roster_cleanup',
        };
    }

    private function dueWindowForType(string $type, array $plan): string
    {
        foreach ($plan['collection_sessions'] ?? [] as $session) {
            if ($this->taskTypeFromSession($session) === $type) {
                return $this->dueWindow((string) ($session['schedule_window'] ?? 'this_week'));
            }
        }

        return $type === 'roster_cleanup' ? 'next_session' : 'this_week';
    }

    private function dueWindow(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['next_session', 'this_week', 'this_month'], true) ? $value : 'this_week';
    }

    private function coachNotes(string $type, array $plan): string
    {
        $primaryFocus = $plan['evidence']['decision_primary_focus'] ?? null;
        $base = match ($type) {
            'roster_cleanup' => 'Complete player context before trusting peer-group benchmarks.',
            'exit_velocity_baseline' => 'Use this to improve hitting benchmark confidence.',
            'bullpen_baseline' => 'Use this to improve pitcher command and velocity benchmark confidence.',
            'long_toss_weighted_ball' => 'Use this to connect throwing capacity to mound transfer.',
            'strength_baseline' => 'Use this to explain whether force production is limiting performance.',
            'athletic_testing' => 'Use this to connect speed and power traits to baseball output.',
            'mobility_screen' => 'Use this to identify movement restrictions affecting skill work.',
            default => 'Use this to improve benchmark confidence.',
        };

        return $primaryFocus ? $base.' Current team focus: '.$primaryFocus.'.' : $base;
    }

    private function temporaryKey(string $type, string $playerId, string $playerName): string
    {
        $playerKey = $playerId !== '' ? $playerId : strtolower(preg_replace('/[^a-z0-9]+/i', '_', $playerName) ?: 'unknown_player');

        return 'benchmark_'.$type.'_'.$playerKey;
    }

    private function displayName(string $metricKey): string
    {
        return ucwords(str_replace('_', ' ', BenchmarkDefinitions::normalizeMetricKey($metricKey)));
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

        return in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : 'low';
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }
}
