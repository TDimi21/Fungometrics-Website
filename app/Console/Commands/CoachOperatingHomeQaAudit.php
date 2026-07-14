<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Planner\CoachOperatingHomeActionService;
use App\Services\Planner\CoachOperatingSystemHomeService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

class CoachOperatingHomeQaAudit extends Command
{
    protected $signature = 'planner:operating-home-qa
        {teamId : Team id}
        {--date= : Optional date in YYYY-MM-DD format}
        {--days=30 : Lookback days}
        {--weeks=8 : Lookback weeks}
        {--scenario= : Optional scenario expectation}
        {--json : Output structured JSON}';

    protected $description = 'Run QA checks against the FMTRX coach Operating Home payload and action safety.';

    /**
     * @return int
     */
    public function handle(CoachOperatingSystemHomeService $homeService, CoachOperatingHomeActionService $actionService): int
    {
        $teamId = (string) $this->argument('teamId');
        $scenario = $this->nullableOption('scenario');
        $options = [
            'date' => $this->nullableOption('date'),
            'days' => (int) $this->option('days'),
            'weeks' => (int) $this->option('weeks'),
        ];

        $checks = [];
        $failures = [];
        $warnings = [];
        $payload = [];
        $actions = [];

        $this->recordCheck($checks, $failures, $warnings, 'team_exists', Team::query()->whereKey($teamId)->exists(), 'Team exists.', 'Team was not found.', 'failure');

        try {
            $payload = $homeService->buildHome($teamId, $options);
            $this->recordCheck($checks, $failures, $warnings, 'payload_builds', true, 'Operating Home payload builds.', '', 'failure');
        } catch (Throwable $exception) {
            $this->recordCheck($checks, $failures, $warnings, 'payload_builds', false, '', 'Operating Home payload failed: '.$exception->getMessage(), 'failure');
        }

        try {
            $actionPayload = $actionService->buildAvailableActions($teamId, $payload, $options);
            $actions = Arr::wrap($actionPayload['actions'] ?? []);
            foreach (Arr::wrap($actionPayload['warnings'] ?? []) as $warning) {
                if (is_string($warning) && trim($warning) !== '') {
                    $warnings[] = 'Action availability: '.$warning;
                }
            }
            $this->recordCheck($checks, $failures, $warnings, 'actions_build', true, 'Operating Home actions build.', '', 'failure');
        } catch (Throwable $exception) {
            $this->recordCheck($checks, $failures, $warnings, 'actions_build', false, '', 'Operating Home actions failed: '.$exception->getMessage(), 'failure');
        }

        if (! empty($payload)) {
            $this->checkPayloadSections($payload, $checks, $failures, $warnings);
            $this->checkPayloadSummary($payload, $checks, $failures, $warnings);
            $this->checkVisibleCopy($payload, $checks, $failures, $warnings);
            $this->checkScenario($scenario, $payload, $actions, $checks, $failures, $warnings);
        }

        if (! empty($actions)) {
            $this->checkActionSafety($actions, $checks, $failures, $warnings);
        }

        foreach (Arr::wrap($payload['warnings'] ?? []) as $warning) {
            if (is_string($warning) && trim($warning) !== '') {
                $warnings[] = 'Payload warning: '.$warning;
            }
        }

        $result = [
            'team_id' => $teamId,
            'qa_status' => ! empty($failures) ? 'failed' : (! empty($warnings) ? 'warning' : 'passed'),
            'scenario' => $scenario,
            'checks' => $checks,
            'failures' => array_values(array_unique($failures)),
            'warnings' => array_values(array_unique($warnings)),
            'payload_summary' => $this->payloadSummary($payload, $actions),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ! empty($failures) ? self::FAILURE : self::SUCCESS;
        }

        $this->printHumanOutput($result);

        return ! empty($failures) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $failures
     * @param array<int, string> $warnings
     */
    private function checkPayloadSections(array $payload, array &$checks, array &$failures, array &$warnings): void
    {
        $sections = [
            'home_status' => 'scalar',
            'operating_summary' => 'array',
            'today_plan' => 'array',
            'primary_next_action' => 'array',
            'next_action_stack' => 'array',
            'health_snapshot' => 'array',
            'alerts_snapshot' => 'array',
            'benchmark_snapshot' => 'array',
            'review_snapshot' => 'array',
            'planner_snapshot' => 'array',
            'communication_snapshot' => 'array',
            'player_attention' => 'array',
            'quick_links' => 'array',
        ];

        foreach ($sections as $section => $type) {
            $exists = array_key_exists($section, $payload);
            $validType = $type === 'array' ? is_array($payload[$section] ?? null) : ! is_array($payload[$section] ?? []);
            $this->recordCheck(
                $checks,
                $failures,
                $warnings,
                'section_'.$section,
                $exists && $validType,
                $section.' is present and safe.',
                $section.' is missing or not safely shaped.',
                'failure',
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $failures
     * @param array<int, string> $warnings
     */
    private function checkPayloadSummary(array $payload, array &$checks, array &$failures, array &$warnings): void
    {
        $summary = Arr::wrap($payload['operating_summary'] ?? []);
        $todayPlan = Arr::wrap($payload['today_plan'] ?? []);
        $primary = Arr::wrap($payload['primary_next_action'] ?? []);

        $this->recordCheck($checks, $failures, $warnings, 'home_status_present', $this->stringValue($payload['home_status'] ?? null) !== '', 'Home status is present.', 'Home status is missing.', 'failure');
        $this->recordCheck($checks, $failures, $warnings, 'summary_headline_present', $this->stringValue($summary['headline'] ?? null) !== '', 'Headline is present.', 'Operating summary headline is missing.', 'warning');
        $this->recordCheck($checks, $failures, $warnings, 'today_plan_empty_state_safe', $this->stringValue($todayPlan['status'] ?? null) !== '', 'Today plan has a status.', 'Today plan status is missing.', 'failure');
        $this->recordCheck($checks, $failures, $warnings, 'primary_action_safe', $this->stringValue($primary['title'] ?? null) !== '' || $this->stringValue($primary['action_type'] ?? null) === '', 'Primary action is safe.', 'Primary action is missing title/action safety copy.', 'warning');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $failures
     * @param array<int, string> $warnings
     */
    private function checkVisibleCopy(array $payload, array &$checks, array &$failures, array &$warnings): void
    {
        $forbidden = [
            'source_mix',
            'population_policy',
            'global_clean',
            'payload',
            'command_center',
            'primary_next_action',
            'benchmark_profile',
            'daily_plan_progress',
            'trusted_payload_only',
            'review_status',
        ];

        $visibleStrings = $this->visibleStrings($payload);
        $rawMatches = [];
        foreach ($visibleStrings as $string) {
            $lower = strtolower($string);
            foreach ($forbidden as $needle) {
                if (str_contains($lower, $needle)) {
                    $rawMatches[] = $needle.' in "'.$string.'"';
                }
            }
        }

        $snakeCaseMatches = array_values(array_unique(array_filter(array_map(
            fn (string $value): ?string => preg_match('/\b[a-z]+_[a-z0-9_]+\b/', $value) ? $value : null,
            $visibleStrings,
        ))));

        $this->recordCheck($checks, $failures, $warnings, 'no_forbidden_visible_copy', empty($rawMatches), 'No forbidden technical copy appears in visible strings.', 'Technical copy found: '.implode('; ', array_slice($rawMatches, 0, 6)), 'warning');
        $this->recordCheck($checks, $failures, $warnings, 'no_snake_case_visible_copy', empty($snakeCaseMatches), 'No snake_case appears in visible strings.', 'Snake-case visible copy found: '.implode('; ', array_slice($snakeCaseMatches, 0, 6)), 'warning');
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $failures
     * @param array<int, string> $warnings
     */
    private function checkActionSafety(array $actions, array &$checks, array &$failures, array &$warnings): void
    {
        $dangerous = [
            'publish_plan',
            'assign_plan',
            'send_reminder',
            'approve_selected_values',
            'request_corrections',
            'promote_trusted_data',
            'save_suggested_plan_draft',
        ];
        $navigationOnly = [
            'open_daily_planner',
            'view_alerts',
            'view_health_score',
            'view_benchmark_intelligence',
            'view_communication_rhythm',
            'open_weekly_calendar',
        ];

        $missingConfirm = [];
        $navigationMutates = [];
        $missingDisabledReasons = [];

        foreach ($actions as $action) {
            $type = (string) ($action['action_type'] ?? '');
            if (in_array($type, $dangerous, true) && ! (bool) ($action['requires_confirmation'] ?? false)) {
                $missingConfirm[] = $type;
            }
            if (in_array($type, $navigationOnly, true) && ($action['method'] ?? null) !== null) {
                $navigationMutates[] = $type;
            }
            if (($action['enabled'] ?? true) === false && $this->stringValue($action['disabled_reason'] ?? null) === '') {
                $missingDisabledReasons[] = $type ?: (string) ($action['title'] ?? 'unknown_action');
            }
        }

        $this->recordCheck($checks, $failures, $warnings, 'dangerous_actions_confirmed', empty($missingConfirm), 'Dangerous actions require confirmation.', 'Dangerous actions missing confirmation: '.implode(', ', array_unique($missingConfirm)), 'failure');
        $this->recordCheck($checks, $failures, $warnings, 'navigation_actions_read_only', empty($navigationMutates), 'Navigation actions do not mutate data.', 'Navigation actions have write methods: '.implode(', ', array_unique($navigationMutates)), 'failure');
        $this->recordCheck($checks, $failures, $warnings, 'disabled_actions_explain_why', empty($missingDisabledReasons), 'Disabled actions include reasons.', 'Disabled actions missing reason: '.implode(', ', array_unique($missingDisabledReasons)), 'warning');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $actions
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $failures
     * @param array<int, string> $warnings
     */
    private function checkScenario(?string $scenario, array $payload, array $actions, array &$checks, array &$failures, array &$warnings): void
    {
        if (! $scenario) {
            return;
        }

        $summary = Arr::wrap($payload['operating_summary'] ?? []);
        $plan = Arr::wrap($payload['today_plan'] ?? []);
        $alerts = Arr::wrap($payload['alerts_snapshot'] ?? []);
        $review = Arr::wrap($payload['review_snapshot'] ?? []);
        $communication = Arr::wrap($payload['communication_snapshot'] ?? []);
        $statusLabel = (string) ($summary['status_label'] ?? '');

        $expectation = match ($scenario) {
            'no_data' => [
                'passed' => in_array($statusLabel, ['no_data', 'no_plan'], true),
                'message' => 'Expected no-data/no-plan operating state.',
            ],
            'no_plan' => [
                'passed' => empty($plan['daily_plan_id']) || (string) ($plan['status'] ?? '') === 'missing',
                'message' => 'Expected no active Daily Plan.',
            ],
            'draft_plan' => [
                'passed' => (string) ($plan['status'] ?? '') === 'draft' || $this->hasAction($actions, 'publish_plan'),
                'message' => 'Expected draft plan or publish action.',
            ],
            'published_plan' => [
                'passed' => in_array((string) ($plan['status'] ?? ''), ['published', 'sent', 'in_progress'], true),
                'message' => 'Expected a published/sent/in-progress plan.',
            ],
            'unacknowledged' => [
                'passed' => $this->hasAction($actions, 'send_reminder') || $this->hasPlayerAttention($payload, 'missing_work'),
                'message' => 'Expected unacknowledged player attention or reminder action.',
            ],
            'pending_review' => [
                'passed' => (int) ($review['pending_review_count'] ?? 0) > 0 || $this->hasAction($actions, 'review_submissions'),
                'message' => 'Expected pending benchmark review.',
            ],
            'approved_unpromoted' => [
                'passed' => (int) ($review['approved_unpromoted_count'] ?? 0) > 0 || $this->hasAction($actions, 'promote_trusted_data'),
                'message' => 'Expected approved values waiting for trusted promotion.',
            ],
            'alert_heavy' => [
                'passed' => (int) ($alerts['active_alert_count'] ?? 0) > 0 || (int) ($alerts['critical_count'] ?? 0) > 0 || (int) ($alerts['high_count'] ?? 0) > 0,
                'message' => 'Expected active/high/critical alerts.',
            ],
            'report_due' => [
                'passed' => (bool) ($communication['weekly_report_due'] ?? false) || $this->hasAction($actions, 'prepare_weekly_report'),
                'message' => 'Expected weekly report due or report prep action.',
            ],
            'all_clear' => [
                'passed' => $statusLabel === 'on_track' && (int) ($alerts['active_alert_count'] ?? 0) === 0 && (int) ($review['pending_review_count'] ?? 0) === 0,
                'message' => 'Expected on-track state with no alerts or pending review.',
            ],
            default => [
                'passed' => false,
                'message' => 'Unknown scenario. Supported: no_data, no_plan, draft_plan, published_plan, unacknowledged, pending_review, approved_unpromoted, alert_heavy, report_due, all_clear.',
            ],
        };

        $this->recordCheck(
            $checks,
            $failures,
            $warnings,
            'scenario_'.$scenario,
            (bool) $expectation['passed'],
            'Scenario expectation matched: '.$scenario,
            (string) $expectation['message'],
            'warning',
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function visibleStrings(array $payload): array
    {
        $strings = [];
        $paths = [
            'operating_summary.headline',
            'operating_summary.summary_text',
            'operating_summary.primary_focus',
            'operating_summary.next_best_action',
            'today_plan.title',
            'today_plan.message',
            'health_snapshot.headline',
            'health_snapshot.primary_strength',
            'health_snapshot.primary_risk',
            'alerts_snapshot.highest_priority_alert.title',
            'alerts_snapshot.highest_priority_alert.message',
            'benchmark_snapshot.weakest_category',
            'benchmark_snapshot.weakest_metric',
            'review_snapshot.message',
            'communication_snapshot.message',
            'primary_next_action.title',
            'primary_next_action.why',
            'primary_next_action.action',
            'primary_next_action.button_label',
        ];

        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (is_string($value) && trim($value) !== '') {
                $strings[] = $value;
            }
        }

        foreach (Arr::wrap($payload['next_action_stack'] ?? []) as $action) {
            foreach (['title', 'why', 'action', 'button_label', 'disabled_reason'] as $key) {
                $value = is_array($action) ? ($action[$key] ?? null) : null;
                if (is_string($value) && trim($value) !== '') {
                    $strings[] = $value;
                }
            }
        }

        foreach (Arr::wrap($payload['quick_links'] ?? []) as $link) {
            $value = is_array($link) ? ($link['label'] ?? null) : null;
            if (is_string($value) && trim($value) !== '') {
                $strings[] = $value;
            }
        }

        foreach (Arr::wrap($payload['player_attention'] ?? []) as $row) {
            foreach (['reason', 'recommended_action'] as $key) {
                $value = is_array($row) ? ($row[$key] ?? null) : null;
                if (is_string($value) && trim($value) !== '') {
                    $strings[] = $value;
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function payloadSummary(array $payload, array $actions): array
    {
        $disabled = collect($actions)->filter(fn (array $action): bool => ($action['enabled'] ?? true) === false)->count();

        return [
            'team_name' => $payload['team_name'] ?? null,
            'home_status' => $payload['home_status'] ?? null,
            'status_label' => data_get($payload, 'operating_summary.status_label'),
            'today_plan_status' => data_get($payload, 'today_plan.status'),
            'today_plan_id' => data_get($payload, 'today_plan.daily_plan_id'),
            'primary_action' => data_get($payload, 'primary_next_action.title'),
            'primary_action_type' => data_get($payload, 'primary_next_action.action_type'),
            'next_action_count' => count(Arr::wrap($payload['next_action_stack'] ?? [])),
            'available_action_count' => count($actions),
            'disabled_action_count' => $disabled,
            'player_attention_count' => count(Arr::wrap($payload['player_attention'] ?? [])),
            'payload_warning_count' => count(Arr::wrap($payload['warnings'] ?? [])),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $failures
     * @param array<int, string> $warnings
     */
    private function recordCheck(array &$checks, array &$failures, array &$warnings, string $name, bool $passed, string $passedMessage, string $failedMessage, string $severity): void
    {
        $checks[] = [
            'name' => $name,
            'status' => $passed ? 'passed' : ($severity === 'warning' ? 'warning' : 'failed'),
            'message' => $passed ? $passedMessage : $failedMessage,
        ];

        if ($passed) {
            return;
        }

        if ($severity === 'warning') {
            $warnings[] = $failedMessage;
        } else {
            $failures[] = $failedMessage;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     */
    private function hasAction(array $actions, string $actionType): bool
    {
        return collect($actions)->contains(fn (array $action): bool => (string) ($action['action_type'] ?? '') === $actionType);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasPlayerAttention(array $payload, string $category): bool
    {
        return collect(Arr::wrap($payload['player_attention'] ?? []))->contains(fn (array $row): bool => (string) ($row['category'] ?? '') === $category);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function printHumanOutput(array $result): void
    {
        $this->info('FMTRX OPERATING HOME QA');
        $this->line('Team ID: '.$result['team_id']);
        $this->line('Scenario: '.$this->value($result['scenario'] ?? null));
        $this->line('QA status: '.strtoupper((string) $result['qa_status']));

        $this->section('PAYLOAD SUMMARY');
        foreach (Arr::wrap($result['payload_summary'] ?? []) as $key => $value) {
            $this->line($this->human($key).': '.$this->value($value));
        }

        $this->section('CHECKS');
        foreach (Arr::wrap($result['checks'] ?? []) as $check) {
            $status = strtoupper((string) ($check['status'] ?? 'unknown'));
            $this->line('- ['.$status.'] '.$this->human((string) ($check['name'] ?? 'check')).' — '.$this->value($check['message'] ?? null));
        }

        $this->section('FAILURES');
        if (empty($result['failures'])) {
            $this->line('- none');
        }
        foreach (Arr::wrap($result['failures'] ?? []) as $failure) {
            $this->line('- '.$failure);
        }

        $this->section('WARNINGS');
        if (empty($result['warnings'])) {
            $this->line('- none');
        }
        foreach (Arr::wrap($result['warnings'] ?? []) as $warning) {
            $this->line('- '.$warning);
        }
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function nullableOption(string $key): ?string
    {
        $value = trim((string) ($this->option($key) ?? ''));

        return $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }

    private function human(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?: 'unknown'));
    }
}
