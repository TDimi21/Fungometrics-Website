<?php

declare(strict_types=1);

namespace App\Services\Planner;

use Illuminate\Support\Arr;
use Throwable;

class DevelopmentHealthAlertActionService
{
    private const DANGEROUS_ACTIONS = [
        'publish_plan',
        'send_reminder',
        'promote_trusted_data',
    ];

    private const COMMAND_CENTER_ACTIONS = [
        'publish_plan',
        'send_reminder',
        'review_submissions',
        'promote_trusted_data',
        'refresh_intelligence',
        'generate_next_plan',
    ];

    public function __construct(
        private readonly DevelopmentHealthAlertService $alertService,
        private readonly CoachPlannerCommandCenterService $commandCenterService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildActionsForTeam(string $teamId, array $options = []): array
    {
        $warnings = [];
        $alertsPayload = $this->safe(
            fn (): array => $this->alertService->buildTeamAlerts($teamId, [
                'days' => $this->days($options['days'] ?? 30),
                'weeks' => $this->weeks($options['weeks'] ?? 8),
                'severity_threshold' => (string) ($options['severity_threshold'] ?? 'medium'),
                'include_population_learning' => $options['include_population_learning'] ?? true,
            ]),
            [],
            $warnings,
            'Development health alerts',
        );
        $commandCenter = $this->safeCommandCenter($teamId, $options, $warnings);

        $alertActions = collect(Arr::wrap($alertsPayload['alerts'] ?? []))
            ->map(fn (array $alert): array => $this->buildActionsForAlert($teamId, $alert, [
                ...$options,
                'command_center' => $commandCenter,
            ]))
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'alert_status' => $alertsPayload['alert_status'] ?? 'none',
            'alerts' => $alertActions,
            'action_count' => collect($alertActions)->sum(fn (array $row): int => count(Arr::wrap($row['actions'] ?? []))),
            'primary_actions' => collect($alertActions)->pluck('primary_action')->filter()->values()->all(),
            'warnings' => array_values(array_unique(array_filter([
                ...$warnings,
                ...Arr::wrap($alertsPayload['warnings'] ?? []),
            ]))),
            'evidence' => [
                'source' => 'development_health_alerts_command_center',
                'days' => $this->days($options['days'] ?? 30),
                'weeks' => $this->weeks($options['weeks'] ?? 8),
                'alert_count' => count(Arr::wrap($alertsPayload['alerts'] ?? [])),
                'daily_plan_id' => $commandCenter['daily_plan_id'] ?? null,
                'plan_status' => Arr::get($commandCenter, 'plan_status.status'),
                'no_automatic_send_approve_or_publish' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildActionsForAlert(string $teamId, array $alert, array $options = []): array
    {
        $commandCenter = Arr::wrap($options['command_center'] ?? []);
        if (empty($commandCenter)) {
            $warnings = [];
            $commandCenter = $this->safeCommandCenter($teamId, $options, $warnings);
        }

        $actionTypes = $this->actionTypesForAlert($alert, $commandCenter);
        $actions = collect($actionTypes)
            ->map(fn (array $definition): array => $this->buildAction($teamId, $alert, $definition, $commandCenter))
            ->values()
            ->all();

        return [
            'alert_id' => (string) ($alert['alert_id'] ?? $this->slug((string) ($alert['title'] ?? 'alert'))),
            'team_id' => $teamId,
            'alert_type' => (string) ($alert['type'] ?? 'unknown'),
            'actions' => $actions,
            'primary_action' => $actions[0] ?? [],
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function executeAlertAction(string $teamId, string $actionType, array $payload = [], ?string $userId = null): array
    {
        $actionType = $this->normalizeActionType($actionType);
        $days = $this->days($payload['days'] ?? 30);
        $warnings = [];
        $availability = $this->buildActionAvailability($teamId, $actionType, $payload);
        $dryRun = $this->bool($payload['dry_run'] ?? false);
        $confirmed = $this->bool($payload['confirm'] ?? false);

        if (! ($availability['enabled'] ?? false)) {
            return $this->executionResult($teamId, $actionType, 'skipped', (string) ($availability['disabled_reason'] ?? 'This action is not available yet.'), [
                'availability' => $availability,
            ], [], $days);
        }

        if ($dryRun) {
            return $this->executionResult($teamId, $actionType, 'skipped', 'Dry run: action was not executed.', [
                'would_execute' => $actionType,
                'availability' => $availability,
                'payload' => $payload,
            ], [], $days);
        }

        if (($availability['requires_confirmation'] ?? false) && ! $confirmed) {
            return $this->executionResult($teamId, $actionType, 'skipped', 'Confirmation is required.', [
                'availability' => $availability,
            ], ['This action requires explicit coach confirmation.'], $days);
        }

        if (! in_array($actionType, self::COMMAND_CENTER_ACTIONS, true)) {
            return $this->executionResult($teamId, $actionType, 'navigation_only', (string) ($availability['success_message'] ?? 'Open the related section to continue.'), [
                'target_route' => $availability['target_route'] ?? null,
                'target_section' => $availability['target_section'] ?? null,
                'payload' => $availability['payload'] ?? [],
            ], [], $days);
        }

        try {
            $result = $this->commandCenterService->runAction($teamId, $actionType, [
                ...(array) ($payload['payload'] ?? []),
                ...$payload,
                'days' => max(7, min(365, (int) ($payload['intelligence_days'] ?? 365))),
                'dry_run' => false,
            ], $userId);

            return [
                'team_id' => $teamId,
                'action_type' => $actionType,
                'status' => in_array((string) ($result['status'] ?? ''), ['completed', 'partial', 'skipped', 'failed'], true)
                    ? (string) $result['status']
                    : 'failed',
                'message' => (string) ($result['message'] ?? $availability['success_message'] ?? 'Action completed.'),
                'result' => Arr::wrap($result['result'] ?? []),
                'updated_alerts' => $this->refreshAlertsAfterAction($teamId, [
                    'days' => $days,
                    'weeks' => $payload['weeks'] ?? 8,
                    'severity_threshold' => $payload['severity_threshold'] ?? 'medium',
                ]),
                'updated_command_center' => Arr::wrap($result['updated_command_center'] ?? []),
                'warnings' => Arr::wrap($result['warnings'] ?? []),
            ];
        } catch (Throwable $exception) {
            return $this->executionResult($teamId, $actionType, 'failed', 'Could not complete this action.', [
                'exception' => class_basename($exception),
            ], [$exception->getMessage()], $days);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildActionAvailability(string $teamId, string $actionType, array $context = []): array
    {
        $actionType = $this->normalizeActionType($actionType);
        $warnings = [];
        $commandCenter = Arr::wrap($context['command_center'] ?? []);
        if (empty($commandCenter)) {
            $commandCenter = $this->safeCommandCenter($teamId, $context, $warnings);
        }

        $dailyPlanId = $this->nullableString($context['daily_plan_id'] ?? $commandCenter['daily_plan_id'] ?? null);
        $planStatus = (string) Arr::get($commandCenter, 'plan_status.status', 'unknown');
        $reviewCount = (int) Arr::get($commandCenter, 'review_queue_summary.pending_review_count', 0);
        $promotionCount = (int) Arr::get($commandCenter, 'trusted_data_summary.awaiting_promotion_count', 0);
        $notAcknowledged = (int) Arr::get($commandCenter, 'player_status_summary.not_acknowledged_count', 0);

        $enabled = true;
        $disabledReason = null;
        $targetSection = $this->targetSection($actionType);
        $targetRoute = $this->targetRoute($actionType, $teamId, $dailyPlanId);
        $apiEndpoint = in_array($actionType, self::COMMAND_CENTER_ACTIONS, true)
            ? "coach/teams/{$teamId}/development-health-alert-actions/execute"
            : null;

        if ($actionType === 'publish_plan' && (! $dailyPlanId || $planStatus !== 'draft')) {
            $enabled = false;
            $disabledReason = $dailyPlanId ? 'The current plan is not a draft.' : 'A saved draft Daily Plan is required.';
        }
        if ($actionType === 'send_reminder' && (! $dailyPlanId || $notAcknowledged < 1)) {
            $enabled = false;
            $disabledReason = $dailyPlanId ? 'No assigned players need a reminder.' : 'A saved Daily Plan is required.';
        }
        if ($actionType === 'review_submissions' && $reviewCount < 1) {
            $enabled = true;
            $disabledReason = null;
        }
        if ($actionType === 'promote_trusted_data' && $promotionCount < 1) {
            $enabled = false;
            $disabledReason = 'No approved unpromoted benchmark values are waiting.';
        }
        if ($actionType === 'none') {
            $enabled = false;
            $disabledReason = 'No action is required.';
        }
        if (! $this->isSupportedAction($actionType)) {
            $enabled = false;
            $disabledReason = 'This action is not available yet.';
            $apiEndpoint = null;
        }

        return [
            'action_type' => $actionType,
            'enabled' => $enabled,
            'requires_confirmation' => in_array($actionType, self::DANGEROUS_ACTIONS, true),
            'requires_selection' => in_array($actionType, ['approve_values', 'request_corrections'], true),
            'target_route' => $targetRoute,
            'target_section' => $targetSection,
            'api_endpoint' => $apiEndpoint,
            'method' => $apiEndpoint ? 'POST' : null,
            'payload' => array_filter([
                'daily_plan_id' => $dailyPlanId,
                'action_type' => $actionType,
                'player_ids' => $context['player_ids'] ?? null,
                'task_ids' => $context['task_ids'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== []),
            'success_message' => $this->successMessage($actionType),
            'disabled_reason' => $disabledReason,
            'safety_notes' => $this->safetyNotes($actionType),
            'evidence' => [
                'daily_plan_id' => $dailyPlanId,
                'plan_status' => $planStatus,
                'pending_review_count' => $reviewCount,
                'awaiting_promotion_count' => $promotionCount,
                'not_acknowledged_count' => $notAcknowledged,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshAlertsAfterAction(string $teamId, array $options = []): array
    {
        return $this->alertService->buildTeamAlerts($teamId, [
            'days' => $this->days($options['days'] ?? 30),
            'weeks' => $this->weeks($options['weeks'] ?? 8),
            'severity_threshold' => (string) ($options['severity_threshold'] ?? 'medium'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function actionTypesForAlert(array $alert, array $commandCenter): array
    {
        $type = (string) ($alert['type'] ?? '');
        $component = (string) ($alert['component'] ?? '');

        if ($type === 'health_drop') {
            return [
                ['type' => 'open_section', 'section' => 'development_health_trendline', 'title' => 'Review Health Trend', 'button' => 'View Trendline'],
                ['type' => 'refresh_intelligence'],
                ['type' => 'open_daily_planner'],
            ];
        }

        if ($type === 'component_decline') {
            return [$this->componentAction($component), ['type' => 'open_section', 'section' => 'development_health_trendline']];
        }

        return match ($type) {
            'planning_gap' => [
                ['type' => Arr::get($commandCenter, 'plan_status.status') === 'draft' ? 'publish_plan' : 'generate_next_plan'],
                ['type' => 'open_daily_planner'],
            ],
            'completion_drop', 'missed_work' => [
                ['type' => 'send_reminder'],
                ['type' => 'open_section', 'section' => 'player_plan_progress'],
            ],
            'review_queue' => [
                ['type' => 'review_submissions'],
                ['type' => 'open_section', 'section' => 'review_queue'],
            ],
            'benchmark_coverage', 'missing_baselines' => [
                ['type' => 'collect_baselines'],
                ['type' => 'generate_next_plan'],
            ],
            'trusted_data_stall' => [
                ['type' => 'promote_trusted_data'],
                ['type' => 'refresh_intelligence'],
            ],
            'communication_drop' => [
                ['type' => 'send_weekly_report'],
                ['type' => 'open_section', 'section' => 'weekly_report_delivery'],
            ],
            'population_learning_qa' => [
                ['type' => 'open_section', 'section' => 'population_learning_qa', 'title' => 'Open Population QA', 'button' => 'Open Population QA'],
                ['type' => 'refresh_intelligence'],
            ],
            default => [
                ['type' => 'open_section', 'section' => 'coach_command_center'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function componentAction(string $component): array
    {
        return match ($component) {
            'planning_consistency' => ['type' => 'publish_plan'],
            'player_completion' => ['type' => 'send_reminder'],
            'benchmark_coverage' => ['type' => 'collect_baselines'],
            'coach_review_flow' => ['type' => 'review_submissions'],
            'trusted_data_growth' => ['type' => 'promote_trusted_data'],
            'communication_rhythm' => ['type' => 'send_weekly_report'],
            default => ['type' => 'open_section', 'section' => 'coach_command_center'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAction(string $teamId, array $alert, array $definition, array $commandCenter): array
    {
        $actionType = $this->normalizeActionType((string) ($definition['type'] ?? 'none'));
        $availability = $this->buildActionAvailability($teamId, $actionType, [
            'command_center' => $commandCenter,
        ]);
        if (! empty($definition['section'])) {
            $availability['target_section'] = (string) $definition['section'];
        }

        $title = (string) ($definition['title'] ?? $this->actionTitle($actionType));
        $button = (string) ($definition['button'] ?? $this->buttonLabel($actionType));

        return [
            'action_id' => $this->slug(($alert['alert_id'] ?? 'alert').'-'.$actionType.'-'.$title),
            'action_type' => $actionType,
            'title' => $title,
            'button_label' => $button,
            'priority' => $this->priority((string) ($alert['severity'] ?? 'medium')),
            'why' => $this->whyCopy($actionType, $alert),
            'enabled' => (bool) ($availability['enabled'] ?? false),
            'requires_confirmation' => (bool) ($availability['requires_confirmation'] ?? false),
            'requires_selection' => (bool) ($availability['requires_selection'] ?? false),
            'target_route' => $availability['target_route'] ?? null,
            'target_section' => $availability['target_section'] ?? null,
            'api_endpoint' => $availability['api_endpoint'] ?? null,
            'method' => $availability['method'] ?? null,
            'payload' => [
                ...Arr::wrap($availability['payload'] ?? []),
                'alert_id' => $alert['alert_id'] ?? null,
            ],
            'success_message' => $availability['success_message'] ?? null,
            'disabled_reason' => $availability['disabled_reason'] ?? null,
            'safety_notes' => Arr::wrap($availability['safety_notes'] ?? []),
        ];
    }

    private function safeCommandCenter(string $teamId, array $options, array &$warnings): array
    {
        return $this->safe(fn (): array => $this->commandCenterService->buildForTeam($teamId, [
            'days' => 365,
            'include_benchmark_gaps' => false,
            'include_update_suggestions' => false,
            ...(array) ($options['command_center_options'] ?? []),
        ]), [], $warnings, 'Planner command center');
    }

    private function executionResult(string $teamId, string $actionType, string $status, string $message, array $result, array $warnings, int $days): array
    {
        return [
            'team_id' => $teamId,
            'action_type' => $actionType,
            'status' => $status,
            'message' => $message,
            'result' => $result,
            'updated_alerts' => $status === 'navigation_only' ? [] : $this->refreshAlertsAfterAction($teamId, ['days' => $days]),
            'updated_command_center' => [],
            'warnings' => array_values(array_unique(array_filter(array_map('strval', $warnings)))),
        ];
    }

    private function isSupportedAction(string $actionType): bool
    {
        return in_array($actionType, [
            ...self::COMMAND_CENTER_ACTIONS,
            'collect_baselines',
            'send_weekly_report',
            'open_daily_planner',
            'open_section',
            'none',
        ], true);
    }

    private function normalizeActionType(string $actionType): string
    {
        $actionType = trim($actionType);

        return $actionType !== '' ? $actionType : 'none';
    }

    private function actionTitle(string $actionType): string
    {
        return [
            'review_submissions' => 'Review Submitted Values',
            'publish_plan' => 'Publish Draft Plan',
            'send_reminder' => 'Send Player Reminder',
            'collect_baselines' => 'Create Baseline Plan',
            'promote_trusted_data' => 'Promote Approved Values',
            'refresh_intelligence' => 'Refresh Benchmark Intelligence',
            'send_weekly_report' => 'Prepare Weekly Update',
            'generate_next_plan' => 'Generate Next Plan',
            'open_daily_planner' => 'Open Daily Planner',
            'open_section' => 'Open Section',
        ][$actionType] ?? 'Open Workflow';
    }

    private function buttonLabel(string $actionType): string
    {
        return [
            'review_submissions' => 'Review Submitted Values',
            'publish_plan' => 'Publish Plan',
            'send_reminder' => 'Send Reminder',
            'collect_baselines' => 'Add Baseline Blocks',
            'promote_trusted_data' => 'Promote Approved Values',
            'refresh_intelligence' => 'Refresh Intelligence',
            'send_weekly_report' => 'Prepare Weekly Update',
            'generate_next_plan' => 'Generate Plan',
            'open_daily_planner' => 'Open Planner',
            'open_section' => 'Open Section',
        ][$actionType] ?? 'Open';
    }

    private function whyCopy(string $actionType, array $alert): string
    {
        return [
            'review_submissions' => 'Submitted benchmark values need coach review before they can become trusted data.',
            'publish_plan' => 'Players cannot act on a draft until the coach publishes it.',
            'send_reminder' => 'A reminder keeps assigned players moving without changing their workout.',
            'collect_baselines' => 'Baseline blocks improve benchmark confidence without changing scoring formulas.',
            'promote_trusted_data' => 'Only approved values can be promoted into trusted benchmark data.',
            'refresh_intelligence' => 'Refreshing rebuilds benchmark and action payloads from current trusted data.',
            'send_weekly_report' => 'Weekly prep opens the existing delivery review flow and does not send automatically.',
            'generate_next_plan' => 'FMTRX will generate a preview only. Nothing is published automatically.',
            'open_daily_planner' => 'Open the existing planner to edit, assign, or publish safely.',
            'open_section' => (string) ($alert['recommended_action'] ?? 'Open the related workflow.'),
        ][$actionType] ?? (string) ($alert['why_it_matters'] ?? 'Open the related workflow.');
    }

    private function targetSection(string $actionType): ?string
    {
        return [
            'review_submissions' => 'review_queue',
            'publish_plan' => 'saved_plans',
            'send_reminder' => 'player_plan_progress',
            'collect_baselines' => 'benchmark_collection_plan',
            'promote_trusted_data' => 'review_queue',
            'refresh_intelligence' => 'development_health_alerts',
            'send_weekly_report' => 'weekly_report_delivery',
            'generate_next_plan' => 'next_week_plan_draft',
            'open_daily_planner' => 'daily_planner',
            'open_section' => 'coach_command_center',
        ][$actionType] ?? null;
    }

    private function targetRoute(string $actionType, string $teamId, ?string $dailyPlanId): ?string
    {
        return match ($actionType) {
            'open_daily_planner', 'publish_plan' => '/practice-planner',
            'review_submissions', 'promote_trusted_data' => '/practice-planner#review-queue',
            'send_weekly_report' => '/practice-planner#weekly-report-delivery',
            'generate_next_plan', 'collect_baselines' => '/practice-planner#next-week-plan-draft',
            default => '/practice-planner',
        };
    }

    private function successMessage(string $actionType): ?string
    {
        return [
            'review_submissions' => 'Review queue opened.',
            'publish_plan' => 'Plan published.',
            'send_reminder' => 'Reminder prepared.',
            'collect_baselines' => 'Baseline plan workflow opened.',
            'promote_trusted_data' => 'Trusted data promoted.',
            'refresh_intelligence' => 'Benchmark intelligence refreshed.',
            'send_weekly_report' => 'Weekly report prep opened.',
            'generate_next_plan' => 'Next plan draft generated.',
            'open_daily_planner' => 'Daily Planner opened.',
            'open_section' => 'Related section opened.',
        ][$actionType] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function safetyNotes(string $actionType): array
    {
        return match ($actionType) {
            'publish_plan' => ['Requires coach confirmation. No generated plan is auto-published.'],
            'send_reminder' => ['Requires coach confirmation. Existing reminder service may return manual-copy fallback.'],
            'promote_trusted_data' => ['Requires coach confirmation. Pending, rejected, and unreviewed values are not promoted.'],
            'send_weekly_report' => ['Opens delivery prep/review only. Nothing is sent automatically.'],
            'collect_baselines' => ['Opens baseline planning only. Nothing is assigned automatically.'],
            default => ['Read-only or navigation action.'],
        };
    }

    private function days(mixed $value): int
    {
        return max(7, min(365, (int) $value));
    }

    private function weeks(mixed $value): int
    {
        return max(1, min(52, (int) $value));
    }

    private function priority(string $value): string
    {
        return in_array($value, ['critical', 'high', 'medium', 'low'], true) ? $value : 'medium';
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private function slug(string $value): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $value), '_')) ?: 'action';
    }

    private function safe(callable $callback, array $fallback, array &$warnings, string $label): array
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            $warnings[] = $label.' unavailable: '.$exception->getMessage();

            return $fallback;
        }
    }
}
