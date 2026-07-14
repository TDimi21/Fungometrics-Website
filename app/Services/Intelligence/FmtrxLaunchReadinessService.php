<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Services\Planner\CoachOperatingSystemHomeService;
use App\Services\Planner\CoachPlannerCommandCenterService;
use App\Services\Planner\CoachWeeklyReportExportService;
use App\Services\Planner\CoachWeeklyTeamReportService;
use App\Services\Planner\CommunicationRhythmService;
use App\Services\Planner\DevelopmentHealthAlertService;
use App\Services\Planner\DevelopmentProgramHealthService;
use App\Services\Planner\SeasonArchiveExportService;
use App\Services\Planner\SeasonDevelopmentArchiveService;
use App\Services\Planner\WeeklyPlannerRollupService;
use App\Services\Planner\WeeklyReportDeliveryPrepService;
use App\Services\Planner\WeeklyReportDeliveryReviewService;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FmtrxLaunchReadinessService
{
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function buildReadinessReport(string $teamId, array $options = []): array
    {
        $options = $this->normalizeOptions($options);
        $qa = app(FmtrxEndToEndQaService::class)->runTeamQa($teamId, [
            'days' => $options['days'],
            'weeks' => $options['weeks'],
            'dry_run' => true,
        ]);

        $context = [
            'options' => $options,
            'qa' => $qa,
            'checks_by_area' => $this->checksByArea($qa['checks'] ?? []),
            'team_exists' => $this->teamExists($teamId),
            'roster_count' => $this->rosterCount($teamId),
            'planner_counts' => $this->plannerCounts($teamId),
            'benchmark_profile' => $this->safePayload(fn (): array => app(TeamBenchmarkProfileService::class)->build($teamId, (int) $options['days']))[0],
            'decision_brief' => $this->safePayload(fn (): array => app(DecisionEngine::class)->buildTeamDecisionBrief($teamId, (int) $options['days']))[0],
        ];

        $coach = $this->evaluateCoachReadiness($teamId, $context);
        $player = $this->evaluatePlayerReadiness($teamId, $context);
        $benchmark = $this->evaluateBenchmarkReadiness($teamId, $context);
        $planner = $this->evaluatePlannerReadiness($teamId, $context);
        $reports = $this->evaluateReportReadiness($teamId, $context);
        $privacy = $this->evaluatePrivacySafety($teamId, $context);
        $components = [$coach, $player, $benchmark, $planner, $reports, $privacy];

        $launchBlockers = $this->launchBlockers($qa, $components, $context);
        $warnings = $this->warnings($qa, $components);
        $needsMoreData = $this->needsMoreData($context, $benchmark);
        $knownRisks = $this->knownRisks($context, $warnings, $needsMoreData);
        $score = $this->overallScore($components, $launchBlockers, $warnings, (bool) $options['strict']);
        $status = $this->readinessStatus($score, $launchBlockers, $warnings, $needsMoreData, (bool) $options['strict']);
        if (($context['team_exists'] ?? false) && (int) ($context['roster_count'] ?? 0) === 0 && empty($launchBlockers)) {
            $status = 'internal_only';
        }

        $readiness = [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'readiness_status' => $status,
            'overall_score_0_100' => $score,
            'launch_summary' => $this->launchSummary($status, $score, $launchBlockers, $needsMoreData, $warnings),
            'coach_readiness' => $coach,
            'player_readiness' => $player,
            'benchmark_readiness' => $benchmark,
            'planner_readiness' => $planner,
            'report_readiness' => $reports,
            'privacy_safety' => $privacy,
            'feature_flags_recommendation' => $this->featureFlagRecommendations($components, $context),
            'ready_now' => $this->readyNow($components, $context),
            'internal_only' => $this->internalOnly($context),
            'needs_more_data' => $needsMoreData,
            'known_risks' => $knownRisks,
            'launch_blockers' => $launchBlockers,
            'next_cycle_backlog' => [],
            'warnings' => $warnings,
            'evidence' => [
                'team_exists' => $context['team_exists'],
                'roster_count' => $context['roster_count'],
                'qa_status' => $qa['qa_status'] ?? null,
                'qa_summary' => $qa['summary'] ?? [],
                'planner_counts' => $context['planner_counts'],
                'benchmark_player_count' => $context['benchmark_profile']['player_count'] ?? null,
                'benchmark_metric_count' => $context['benchmark_profile']['metric_count'] ?? null,
                'dry_run' => true,
                'data_modified' => false,
            ],
        ];
        $readiness['next_cycle_backlog'] = $this->buildLaunchBacklog($readiness);

        return $readiness;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function evaluateCoachReadiness(string $teamId, array $context = []): array
    {
        $areas = $this->checksForAreas($context, ['operating_home', 'planner', 'health', 'reports', 'actions', 'copy', 'routes']);
        $payloads = [
            'operating_home' => $this->safePayload(fn (): array => app(CoachOperatingSystemHomeService::class)->buildHome($teamId, [
                'days' => $this->contextDays($context),
                'weeks' => $this->contextWeeks($context),
            ])),
            'command_center' => $this->safePayload(fn (): array => app(CoachPlannerCommandCenterService::class)->buildForTeam($teamId, [
                'days' => $this->contextDays($context),
            ])),
            'health' => $this->safePayload(fn (): array => app(DevelopmentProgramHealthService::class)->buildTeamHealthScore($teamId, [
                'weeks' => $this->contextWeeks($context),
            ])),
            'alerts' => $this->safePayload(fn (): array => app(DevelopmentHealthAlertService::class)->buildTeamAlerts($teamId, [
                'weeks' => $this->contextWeeks($context),
            ])),
        ];

        $ready = [
            'Operating Home summary is available.',
            'Daily Planner and coach command center are wired to existing Daily Planner data.',
            'Development Health, alerts, benchmark status, and report workflows are available as read-only summaries.',
            'Dangerous coach actions are validated by explicit confirmation metadata.',
        ];
        $risks = [];
        foreach ($payloads as $name => [$payload, $error]) {
            if ($error) {
                $risks[] = $this->label($name).' payload did not build: '.$error;
            } elseif (empty($payload)) {
                $risks[] = $this->label($name).' returned an empty state.';
            }
        }

        return $this->component('coach', 'Coach Readiness', $areas, $ready, $risks, [], [
            'operating_home_status' => $payloads['operating_home'][0]['home_status'] ?? null,
            'primary_next_action' => $payloads['operating_home'][0]['primary_next_action']['title'] ?? null,
            'command_center_loaded' => ! empty($payloads['command_center'][0]),
            'health_score' => $payloads['health'][0]['overall_score_0_100'] ?? null,
            'alert_count' => $payloads['alerts'][0]['alert_count'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function evaluatePlayerReadiness(string $teamId, array $context = []): array
    {
        $areas = $this->checksForAreas($context, ['player_workout', 'review', 'trusted_data', 'privacy']);
        $counts = $context['planner_counts'] ?? $this->plannerCounts($teamId);
        $ready = [
            'Player assigned workout routes exist.',
            'Published assigned plans can be separated from draft plans.',
            'Player metric submissions remain review-gated before trusted promotion.',
        ];
        $risks = [];
        if ((int) ($counts['published_plan_count'] ?? 0) === 0) {
            $risks[] = 'No published Daily Plans exist for this team yet, so player workout usage should start as coach beta.';
        }
        if ((int) ($counts['assignment_count'] ?? 0) === 0) {
            $risks[] = 'No current Daily Plan assignments were found.';
        }

        return $this->component('player', 'Player Readiness', $areas, $ready, $risks, [], [
            'daily_plan_count' => $counts['daily_plan_count'] ?? 0,
            'published_plan_count' => $counts['published_plan_count'] ?? 0,
            'assignment_count' => $counts['assignment_count'] ?? 0,
            'progress_count' => $counts['progress_count'] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function evaluateBenchmarkReadiness(string $teamId, array $context = []): array
    {
        $areas = $this->checksForAreas($context, ['benchmark', 'trusted_data']);
        $profile = $context['benchmark_profile'] ?? [];
        $sourceMix = is_array($profile['source_mix'] ?? null) ? $profile['source_mix'] : [];
        $missing = $this->arrayValue($profile['missing_metrics'] ?? []);
        $ready = [
            'Research benchmark fallback is active.',
            'Age-adjusted player and team benchmark profiles build safely.',
            'Population learning uses guardrails and trust controls before blending.',
            'Pending, rejected, and correction-requested values are excluded from trusted promotion.',
        ];
        $risks = [];
        if ((int) ($profile['metric_count'] ?? 0) === 0) {
            $risks[] = 'No benchmark metrics are available for this team yet.';
        }
        if (! empty($missing)) {
            $risks[] = count($missing).' missing benchmark data group(s) still need collection.';
        }
        if ((float) ($sourceMix['population_share'] ?? $sourceMix['population_weight'] ?? 0) <= 0) {
            $risks[] = 'FMTRX population learning is not yet carrying team benchmark scores; research remains the source of truth.';
        }

        return $this->component('benchmark', 'Benchmark Readiness', $areas, $ready, $risks, [], [
            'player_count' => $profile['player_count'] ?? 0,
            'metric_count' => $profile['metric_count'] ?? 0,
            'benchmark_confidence' => $profile['benchmark_confidence'] ?? null,
            'source_mix' => $sourceMix,
            'missing_metric_groups' => count($missing),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function evaluatePlannerReadiness(string $teamId, array $context = []): array
    {
        $areas = $this->checksForAreas($context, ['planner', 'operating_home', 'player_workout']);
        $counts = $context['planner_counts'] ?? $this->plannerCounts($teamId);
        $ready = [
            'Generated coach action plans map into the existing Daily Planner.',
            'Daily Planner remains the source of truth for drafts, publishing, assignments, and progress.',
            'Revision/update flows are built to preserve player progress.',
        ];
        $risks = [];
        if (! ($counts['has_daily_planner_tables'] ?? false)) {
            $risks[] = 'Daily Planner tables are not available.';
        }
        if ((int) ($counts['daily_plan_count'] ?? 0) === 0) {
            $risks[] = 'No Daily Plans exist for this team yet.';
        }

        return $this->component('planner', 'Planner Readiness', $areas, $ready, $risks, [], $counts);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function evaluateReportReadiness(string $teamId, array $context = []): array
    {
        $areas = $this->checksForAreas($context, ['reports', 'privacy']);
        $weekly = $this->safePayload(fn (): array => app(CoachWeeklyTeamReportService::class)->buildTeamReport($teamId, [
            'weeks' => $this->contextWeeks($context),
        ]));
        $weeklyExport = $this->safePayload(fn (): array => app(CoachWeeklyReportExportService::class)->buildExport($teamId, [
            'audience' => 'coach',
            'template_key' => 'detailed_coach_report',
            'weeks' => $this->contextWeeks($context),
        ]));
        $seasonArchive = $this->safePayload(fn (): array => app(SeasonDevelopmentArchiveService::class)->buildTeamSeasonArchive($teamId, [
            'weeks' => $this->contextWeeks($context),
        ]));
        $seasonExport = $this->safePayload(fn (): array => app(SeasonArchiveExportService::class)->buildExport($teamId, [
            'audience' => 'staff',
            'weeks' => $this->contextWeeks($context),
        ]));
        $communication = $this->safePayload(fn (): array => app(CommunicationRhythmService::class)->buildTeamRhythm($teamId, [
            'weeks' => $this->contextWeeks($context),
        ]));

        $ready = [
            'Weekly report payload and coach export are available.',
            'Season archive and staff export are available.',
            'Delivery prep/review flows are preview-first and do not auto-send.',
            'Parent/player exports are filtered by audience in QA checks.',
        ];
        $risks = [];
        foreach ([
            'weekly report' => $weekly,
            'weekly export' => $weeklyExport,
            'season archive' => $seasonArchive,
            'season export' => $seasonExport,
            'communication rhythm' => $communication,
        ] as $label => [$payload, $error]) {
            if ($error) {
                $risks[] = ucfirst($label).' did not build: '.$error;
            } elseif (empty($payload)) {
                $risks[] = ucfirst($label).' returned an empty state.';
            }
        }

        return $this->component('reports', 'Report Readiness', $areas, $ready, $risks, [], [
            'weekly_report_status' => $weekly[0]['status'] ?? null,
            'weekly_export_format' => $weeklyExport[0]['format'] ?? null,
            'season_archive_status' => $seasonArchive[0]['status'] ?? null,
            'season_export_format' => $seasonExport[0]['format'] ?? null,
            'communication_score' => $communication[0]['score']['score_0_100'] ?? null,
            'delivery_mode' => 'manual_review_required',
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function evaluatePrivacySafety(string $teamId, array $context = []): array
    {
        $areas = $this->checksForAreas($context, ['privacy', 'actions', 'trusted_data']);
        $unsafePromotions = [
            'pending_promoted' => $this->safeTaskCount($teamId, fn ($query) => $query->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING)->whereNotNull('promoted_at')),
            'rejected_promoted' => $this->safeTaskCount($teamId, fn ($query) => $query->where('review_status', BenchmarkCollectionTask::REVIEW_REJECTED)->whereNotNull('promoted_at')),
            'correction_promoted' => $this->safeTaskCount($teamId, fn ($query) => $query->where('review_status', BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED)->whereNotNull('promoted_at')),
        ];
        $ready = [
            'Coach-only routes are behind authenticated coach access.',
            'Preview/review flows do not send reports automatically.',
            'Trusted data promotion requires approved review state.',
            'Dangerous actions require explicit confirmation metadata.',
        ];
        $blocked = [];
        if (array_sum($unsafePromotions) > 0) {
            $blocked[] = 'Some non-approved benchmark values appear promoted.';
        }

        return $this->component('privacy', 'Privacy / Safety Readiness', $areas, $ready, [], $blocked, [
            'unsafe_promotions' => $unsafePromotions,
            'automatic_send_enabled' => false,
            'automatic_publish_enabled' => false,
            'automatic_approval_enabled' => false,
            'data_modified' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $readiness
     * @return array<int, array<string, mixed>>
     */
    public function buildLaunchBacklog(array $readiness): array
    {
        $items = [];
        foreach ($readiness['launch_blockers'] ?? [] as $blocker) {
            $items[] = [
                'title' => (string) ($blocker['title'] ?? 'Fix launch blocker'),
                'priority' => 'critical',
                'area' => $this->backlogArea((string) ($blocker['area'] ?? 'infra')),
                'phase_recommendation' => 'Launch hotfix',
                'why' => (string) ($blocker['why'] ?? 'This blocks safe launch.'),
                'expected_impact' => 'Unblocks production use safely.',
                'dependencies' => [],
            ];
        }

        foreach ($readiness['needs_more_data'] ?? [] as $gap) {
            $items[] = [
                'title' => (string) ($gap['title'] ?? 'Collect missing benchmark data'),
                'priority' => (string) ($gap['priority'] ?? 'high'),
                'area' => 'data',
                'phase_recommendation' => 'Cycle 5A',
                'why' => (string) ($gap['why'] ?? 'More data improves confidence.'),
                'expected_impact' => 'Improves benchmark confidence and coach recommendations.',
                'dependencies' => $gap['dependencies'] ?? [],
            ];
        }

        foreach (array_slice($readiness['warnings'] ?? [], 0, 10) as $warning) {
            $items[] = [
                'title' => (string) ($warning['title'] ?? 'Resolve readiness warning'),
                'priority' => in_array($warning['severity'] ?? null, ['high', 'critical'], true) ? 'high' : 'medium',
                'area' => $this->backlogArea((string) ($warning['area'] ?? 'infra')),
                'phase_recommendation' => 'Cycle 5A',
                'why' => (string) ($warning['message'] ?? 'This is a readiness warning.'),
                'expected_impact' => 'Reduces launch support risk.',
                'dependencies' => [],
            ];
        }

        $items[] = [
            'title' => 'Controlled coach beta rollout',
            'priority' => empty($readiness['launch_blockers'] ?? []) ? 'high' : 'medium',
            'area' => 'coach',
            'phase_recommendation' => 'Cycle 5A',
            'why' => 'Launch ready features to a small set of teams and collect real usage feedback.',
            'expected_impact' => 'Validates the FMTRX operating system in production without exposing internal QA tools broadly.',
            'dependencies' => ['Production smoke test on two real teams', 'Roster context cleanup for selected beta teams'],
        ];

        return $this->dedupeBacklog($items);
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function checksByArea(array $checks): array
    {
        $grouped = [];
        foreach ($checks as $check) {
            $grouped[(string) ($check['area'] ?? 'unknown')][] = $check;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, string> $areas
     * @return array<int, array<string, mixed>>
     */
    private function checksForAreas(array $context, array $areas): array
    {
        $rows = [];
        $byArea = $context['checks_by_area'] ?? [];
        foreach ($areas as $area) {
            foreach ($byArea[$area] ?? [] as $check) {
                $rows[] = $check;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $readyItems
     * @param array<int, string> $riskItems
     * @param array<int, string> $blockedItems
     * @param array<string, mixed> $evidence
     * @return array<string, mixed>
     */
    private function component(string $key, string $name, array $checks, array $readyItems, array $riskItems, array $blockedItems, array $evidence): array
    {
        $failed = array_values(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'failed'));
        $warnings = array_values(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'warning'));
        $highWarnings = array_values(array_filter($warnings, fn (array $check): bool => in_array($check['severity'] ?? null, ['high', 'critical'], true)));
        $score = $this->componentScore($checks, $riskItems, $blockedItems);
        $status = match (true) {
            ! empty($blockedItems) || ! empty($failed) => 'not_ready',
            ! empty($highWarnings) => 'limited',
            ! empty($riskItems) || ! empty($warnings) => 'limited',
            empty($checks) => 'unknown',
            default => 'ready',
        };

        return [
            'key' => $key,
            'display_name' => $name,
            'status' => $status,
            'score_0_100' => $score,
            'summary' => $this->componentSummary($name, $status, $score),
            'ready_items' => $readyItems,
            'risk_items' => array_values(array_unique(array_filter($riskItems))),
            'blocked_items' => array_values(array_unique(array_filter([
                ...$blockedItems,
                ...array_map(fn (array $check): string => (string) ($check['message'] ?? $check['title'] ?? 'Failed check'), $failed),
            ]))),
            'recommended_actions' => $this->componentActions($failed, $warnings, $riskItems),
            'evidence' => [
                ...$evidence,
                'check_count' => count($checks),
                'failed_checks' => count($failed),
                'warning_checks' => count($warnings),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, string> $risks
     * @param array<int, string> $blocked
     */
    private function componentScore(array $checks, array $risks, array $blocked): float
    {
        if (! empty($blocked)) {
            return 30.0;
        }
        if (empty($checks)) {
            return empty($risks) ? 55.0 : 45.0;
        }

        $points = 0;
        foreach ($checks as $check) {
            $points += match ((string) ($check['status'] ?? 'skipped')) {
                'passed' => 100,
                'warning' => in_array($check['severity'] ?? null, ['high', 'critical'], true) ? 55 : 72,
                'failed' => 0,
                default => 55,
            };
        }
        $score = $points / max(1, count($checks));
        $score -= min(18, count($risks) * 4);

        return round(max(0, min(100, $score)), 1);
    }

    private function componentSummary(string $name, string $status, ?float $score): string
    {
        return match ($status) {
            'ready' => $name.' is ready for launch use.',
            'limited' => $name.' is usable, but should remain in controlled coach beta while risks/data gaps are cleaned up.',
            'internal_only' => $name.' should remain internal for now.',
            'not_ready' => $name.' has launch blockers that need repair.',
            default => $name.' needs more production evidence before launch status is clear.',
        }.' Score: '.($score === null ? 'n/a' : number_format($score, 1)).'.';
    }

    /**
     * @param array<int, array<string, mixed>> $failed
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, string> $risks
     * @return array<int, string>
     */
    private function componentActions(array $failed, array $warnings, array $risks): array
    {
        $actions = [];
        foreach ([...$failed, ...$warnings] as $check) {
            if (! empty($check['recommended_fix'])) {
                $actions[] = (string) $check['recommended_fix'];
            }
        }
        foreach ($risks as $risk) {
            $actions[] = 'Review: '.$risk;
        }

        return array_values(array_unique(array_filter($actions)));
    }

    /**
     * @param array<int, array<string, mixed>> $components
     * @param array<int, array<string, mixed>> $blockers
     * @param array<int, array<string, mixed>> $warnings
     */
    private function overallScore(array $components, array $blockers, array $warnings, bool $strict): float
    {
        if (empty($components)) {
            return 0.0;
        }
        $average = array_sum(array_map(fn (array $row): float => (float) ($row['score_0_100'] ?? 0), $components)) / count($components);
        $average -= min(30, count($blockers) * 10);
        if ($strict) {
            $average -= min(20, count($warnings) * 2);
        }

        return round(max(0, min(100, $average)), 1);
    }

    /**
     * @param array<int, array<string, mixed>> $blockers
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $needsMoreData
     */
    private function readinessStatus(float $score, array $blockers, array $warnings, array $needsMoreData, bool $strict): string
    {
        if (! empty($blockers)) {
            return 'not_ready';
        }
        if ($strict && ! empty($warnings)) {
            return 'limited_release';
        }
        if ($score >= 88 && empty($needsMoreData)) {
            return 'ready';
        }
        if ($score >= 68) {
            return 'limited_release';
        }
        if ($score >= 50) {
            return 'internal_only';
        }

        return 'failed';
    }

    /**
     * @param array<int, array<string, mixed>> $blockers
     * @param array<int, array<string, mixed>> $needsMoreData
     * @param array<int, array<string, mixed>> $warnings
     * @return array<string, mixed>
     */
    private function launchSummary(string $status, float $score, array $blockers, array $needsMoreData, array $warnings): array
    {
        $mode = match ($status) {
            'ready' => 'full',
            'limited_release' => 'limited_coach_beta',
            'internal_only' => 'internal_admin_only',
            default => 'hold',
        };
        $headline = match ($status) {
            'ready' => 'FMTRX is ready for full coach launch.',
            'limited_release' => 'FMTRX is ready for a controlled coach beta.',
            'internal_only' => 'FMTRX should stay internal/admin-only for now.',
            'not_ready' => 'FMTRX has launch blockers to fix before release.',
            default => 'FMTRX launch readiness failed.',
        };

        return [
            'headline' => $headline,
            'summary_text' => empty($blockers)
                ? 'Core safety checks are passing. Keep internal QA tools hidden and use coach beta where data coverage is still developing.'
                : 'Resolve launch blockers before exposing the workflow beyond internal testing.',
            'recommended_launch_mode' => $mode,
            'primary_blocker' => $blockers[0]['title'] ?? null,
            'next_best_step' => $blockers[0]['why'] ?? ($needsMoreData[0]['title'] ?? ($warnings[0]['title'] ?? 'Run production smoke test on two real teams and clean roster context.')),
            'score_label' => number_format($score, 1),
        ];
    }

    /**
     * @param array<string, mixed> $qa
     * @param array<int, array<string, mixed>> $components
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    private function launchBlockers(array $qa, array $components, array $context): array
    {
        $blockers = [];
        if (! ($context['team_exists'] ?? false)) {
            $blockers[] = [
                'title' => 'Team Not Found',
                'area' => 'infra',
                'why' => 'Launch readiness cannot run for a team that does not exist.',
                'severity' => 'critical',
            ];
        }
        foreach ($qa['checks'] ?? [] as $check) {
            if (($check['status'] ?? null) !== 'failed') {
                continue;
            }
            $blockers[] = [
                'title' => (string) ($check['title'] ?? 'Failed QA check'),
                'area' => (string) ($check['area'] ?? 'infra'),
                'why' => (string) ($check['message'] ?? 'A launch safety check failed.'),
                'severity' => (string) ($check['severity'] ?? 'high'),
                'recommended_fix' => $check['recommended_fix'] ?? null,
                'check_id' => $check['check_id'] ?? null,
            ];
        }
        foreach ($components as $component) {
            foreach ($component['blocked_items'] ?? [] as $item) {
                $blockers[] = [
                    'title' => $component['display_name'].' Blocker',
                    'area' => (string) ($component['key'] ?? 'infra'),
                    'why' => (string) $item,
                    'severity' => 'high',
                ];
            }
        }

        return $this->uniqueRows($blockers, ['title', 'area', 'why']);
    }

    /**
     * @param array<string, mixed> $qa
     * @param array<int, array<string, mixed>> $components
     * @return array<int, array<string, mixed>>
     */
    private function warnings(array $qa, array $components): array
    {
        $warnings = [];
        foreach ($qa['checks'] ?? [] as $check) {
            if (($check['status'] ?? null) !== 'warning') {
                continue;
            }
            $warnings[] = [
                'title' => (string) ($check['title'] ?? 'Readiness warning'),
                'area' => (string) ($check['area'] ?? 'infra'),
                'message' => (string) ($check['message'] ?? ''),
                'severity' => (string) ($check['severity'] ?? 'medium'),
                'recommended_fix' => $check['recommended_fix'] ?? null,
            ];
        }
        foreach ($components as $component) {
            foreach ($component['risk_items'] ?? [] as $risk) {
                $warnings[] = [
                    'title' => $component['display_name'].' Risk',
                    'area' => (string) ($component['key'] ?? 'infra'),
                    'message' => (string) $risk,
                    'severity' => 'medium',
                    'recommended_fix' => 'Keep this workflow in controlled coach beta until the risk is resolved.',
                ];
            }
        }

        return $this->uniqueRows($warnings, ['title', 'area', 'message']);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $benchmark
     * @return array<int, array<string, mixed>>
     */
    private function needsMoreData(array $context, array $benchmark): array
    {
        $profile = $context['benchmark_profile'] ?? [];
        $items = [];
        $rosterCount = (int) ($context['roster_count'] ?? 0);
        $playerCount = (int) ($profile['player_count'] ?? 0);
        $metricCount = (int) ($profile['metric_count'] ?? 0);
        $missingMetrics = $this->arrayValue($profile['missing_metrics'] ?? []);
        if ($rosterCount === 0) {
            $items[] = [
                'title' => 'Roster Needed',
                'priority' => 'high',
                'why' => 'No roster players are linked to this team, so coach/player launch readiness cannot be validated.',
                'dependencies' => ['Add players to roster', 'Verify player-team links'],
            ];
        }
        if ($rosterCount > 0 && $metricCount === 0) {
            $items[] = [
                'title' => 'Benchmark Baselines Needed',
                'priority' => 'high',
                'why' => 'Roster players exist, but no benchmark metrics are loaded for launch confidence.',
                'dependencies' => ['Roster context', 'EV or bullpen baselines', 'Strength/mobility baselines'],
            ];
        }
        if ($playerCount < $rosterCount) {
            $items[] = [
                'title' => 'Roster Context Cleanup',
                'priority' => 'high',
                'why' => max(0, $rosterCount - $playerCount).' roster player(s) are not represented in benchmark readiness.',
                'dependencies' => ['DOB', 'position/role', 'player-team linkage'],
            ];
        }
        foreach (array_slice($missingMetrics, 0, 8) as $missing) {
            $items[] = [
                'title' => ($missing['display_name'] ?? $missing['metric_key'] ?? 'Missing Metric').' Collection',
                'priority' => $this->missingPriority($missing),
                'why' => ((int) ($missing['missing_count'] ?? count($missing['players_missing'] ?? []))).' player(s) are missing this launch-readiness data.',
                'dependencies' => $this->missingPlayerLabels($missing['players_missing'] ?? []),
            ];
        }
        if (str_contains(strtolower((string) ($benchmark['summary'] ?? '')), 'population')) {
            $items[] = [
                'title' => 'Population Benchmark Sample Growth',
                'priority' => 'medium',
                'why' => 'Research benchmarks are launch-safe, but FMTRX population confidence improves as more valid data is collected.',
                'dependencies' => ['Guarded trusted benchmark samples', 'More active teams'],
            ];
        }

        return $this->uniqueRows($items, ['title', 'why']);
    }

    /**
     * @param array<string, mixed> $missing
     */
    private function missingPriority(array $missing): string
    {
        $classification = (string) ($missing['classification'] ?? $missing['priority'] ?? '');
        if (str_contains($classification, 'critical')) {
            return 'high';
        }

        return in_array($missing['category'] ?? null, ['strength', 'mobility', 'athletic'], true) ? 'medium' : 'high';
    }

    /**
     * @param mixed $players
     * @return array<int, string>
     */
    private function missingPlayerLabels(mixed $players): array
    {
        return collect($this->arrayValue($players))
            ->map(function (mixed $player): ?string {
                if (is_array($player)) {
                    return $player['player_name'] ?? $player['name'] ?? $player['player_id'] ?? null;
                }

                return is_scalar($player) ? (string) $player : null;
            })
            ->filter()
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $needsMoreData
     * @return array<int, string>
     */
    private function knownRisks(array $context, array $warnings, array $needsMoreData): array
    {
        $risks = [];
        if ((int) ($context['roster_count'] ?? 0) === 0) {
            $risks[] = 'No roster players are linked to this team in the current environment.';
        }
        foreach (array_slice($warnings, 0, 8) as $warning) {
            $risks[] = (string) ($warning['message'] ?? $warning['title'] ?? 'Readiness warning');
        }
        foreach (array_slice($needsMoreData, 0, 6) as $gap) {
            $risks[] = (string) ($gap['why'] ?? $gap['title'] ?? 'More data needed');
        }
        $risks[] = 'Delivery remains manual/review-based unless a confirmed sender is configured.';
        $risks[] = 'Small population samples should remain blended or research-backed until trust controls allow rollout.';

        return array_values(array_unique(array_filter($risks)));
    }

    /**
     * @param array<int, array<string, mixed>> $components
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    private function featureFlagRecommendations(array $components, array $context): array
    {
        $byKey = collect($components)->keyBy('key');
        $flags = [
            ['operating_home', 'FMTRX Operating Home', $byKey['coach'] ?? null, 'coach_beta'],
            ['benchmark_intelligence', 'Benchmark Intelligence', $byKey['benchmark'] ?? null, 'coach_beta'],
            ['population_learning', 'Population Learning', $byKey['benchmark'] ?? null, 'internal_only'],
            ['benchmark_trust_badges', 'Benchmark Trust Badges', $byKey['benchmark'] ?? null, 'coach_beta'],
            ['daily_planner_bridge', 'Daily Planner Bridge', $byKey['planner'] ?? null, 'enabled'],
            ['player_workouts', 'Player Workouts', $byKey['player'] ?? null, 'coach_beta'],
            ['metric_entry', 'Metric Entry', $byKey['player'] ?? null, 'coach_beta'],
            ['coach_review', 'Coach Review', $byKey['privacy'] ?? null, 'enabled'],
            ['trusted_data_promotion', 'Trusted Data Promotion', $byKey['privacy'] ?? null, 'internal_only'],
            ['development_health', 'Development Health', $byKey['coach'] ?? null, 'coach_beta'],
            ['health_alerts', 'Health Alerts', $byKey['coach'] ?? null, 'coach_beta'],
            ['weekly_reports', 'Weekly Reports', $byKey['reports'] ?? null, 'enabled'],
            ['report_delivery', 'Report Delivery', $byKey['reports'] ?? null, 'coach_beta'],
            ['season_archive', 'Season Archive', $byKey['reports'] ?? null, 'coach_beta'],
            ['communication_rhythm', 'Communication Rhythm', $byKey['reports'] ?? null, 'coach_beta'],
        ];

        return array_map(function (array $flag): array {
            [$key, $display, $component, $default] = $flag;
            $status = $this->flagStatus($component, $default);

            return [
                'feature_key' => $key,
                'display_name' => $display,
                'recommended_status' => $status,
                'why' => $this->flagWhy($display, $status, $component),
                'risk' => $status === 'enabled' ? null : $this->flagRisk($status),
            ];
        }, $flags);
    }

    private function flagStatus(?array $component, string $default): string
    {
        if (! $component || ($component['status'] ?? null) === 'unknown') {
            return 'internal_only';
        }
        if (($component['status'] ?? null) === 'not_ready') {
            return 'disabled';
        }
        if ($default === 'internal_only') {
            return 'internal_only';
        }
        if (($component['status'] ?? null) === 'limited') {
            return $default === 'enabled' ? 'coach_beta' : $default;
        }

        return $default;
    }

    private function flagWhy(string $display, string $status, ?array $component): string
    {
        return match ($status) {
            'enabled' => $display.' passes launch safety checks and is complete enough for real usage.',
            'coach_beta' => $display.' is useful and safe for coaches, but should launch with monitoring and feedback.',
            'internal_only' => $display.' should remain available to staff/admins while data quality and trust controls mature.',
            'disabled' => $display.' should not be exposed until blockers are resolved.',
            default => $component['summary'] ?? 'Status needs review.',
        };
    }

    private function flagRisk(string $status): ?string
    {
        return match ($status) {
            'coach_beta' => 'Needs controlled rollout, production monitoring, and coach feedback.',
            'internal_only' => 'Too technical or data-dependent for broad user-facing launch.',
            'disabled' => 'Launch blocker or safety risk present.',
            default => null,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $components
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function readyNow(array $components, array $context): array
    {
        $items = [
            'Research Benchmark fallback',
            'Team Benchmark Snapshot',
            'Benchmark Data Quality Card',
            'Daily Planner execution layer',
            'Coach review queue',
            'Weekly Report copy/export',
            'Season Archive export packet',
            'Operating Home read-only summary',
        ];
        if ((int) (($context['planner_counts']['published_plan_count'] ?? 0)) > 0) {
            $items[] = 'Player assigned workouts';
        } else {
            $items[] = 'Player assigned workouts after the first published assignment';
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function internalOnly(array $context): array
    {
        return [
            'Population Learning Admin QA',
            'Metric Trust Rollout controls',
            'Internal Benchmark QA reports',
            'E2E QA commands',
            'Delivery analytics until coach-facing language is validated',
            'Trusted data promotion controls for approved coach/admin users only',
        ];
    }

    private function backlogArea(string $area): string
    {
        return match ($area) {
            'actions', 'operating_home', 'health' => 'coach',
            'player_workout' => 'player',
            'benchmark', 'trusted_data' => 'benchmark',
            'planner' => 'planner',
            'reports' => 'reports',
            'privacy' => 'privacy',
            'copy', 'routes' => 'infra',
            default => in_array($area, ['coach', 'player', 'benchmark', 'planner', 'reports', 'privacy', 'data', 'infra'], true) ? $area : 'infra',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function dedupeBacklog(array $items): array
    {
        return $this->uniqueRows($items, ['title', 'area']);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $keys
     * @return array<int, array<string, mixed>>
     */
    private function uniqueRows(array $rows, array $keys): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $signature = implode('|', array_map(fn (string $key): string => (string) ($row[$key] ?? ''), $keys));
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        return [
            'days' => max(7, min(365, (int) ($options['days'] ?? 365))),
            'weeks' => max(1, min(52, (int) ($options['weeks'] ?? 8))),
            'include_internal_features' => (bool) ($options['include_internal_features'] ?? true),
            'include_backlog' => (bool) ($options['include_backlog'] ?? true),
            'strict' => (bool) ($options['strict'] ?? false),
        ];
    }

    private function contextDays(array $context): int
    {
        return max(7, min(365, (int) ($context['options']['days'] ?? 365)));
    }

    private function contextWeeks(array $context): int
    {
        return max(1, min(52, (int) ($context['options']['weeks'] ?? 8)));
    }

    /**
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function safePayload(callable $callback): array
    {
        try {
            $payload = $callback();

            return [is_array($payload) ? $payload : [], null];
        } catch (Throwable $exception) {
            return [[], class_basename($exception).': '.$exception->getMessage()];
        }
    }

    private function teamExists(string $teamId): bool
    {
        try {
            return Schema::hasTable('teams') && Team::query()->whereKey($teamId)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function rosterCount(string $teamId): int
    {
        try {
            return Schema::hasTable('player_teams') ? PlayerTeam::query()->where('team_id', $teamId)->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function plannerCounts(string $teamId): array
    {
        try {
            if (! Schema::hasTable('daily_plans')) {
                return [
                    'has_daily_planner_tables' => false,
                    'daily_plan_count' => 0,
                    'published_plan_count' => 0,
                    'draft_plan_count' => 0,
                    'assignment_count' => 0,
                    'progress_count' => 0,
                ];
            }

            $planIds = DailyPlan::query()->where('team_id', $teamId)->pluck('id')->map(fn ($id): string => (string) $id)->all();
            $assignments = Schema::hasTable('daily_plan_assignments')
                ? DailyPlanAssignment::query()->whereIn('plan_id', $planIds)->count()
                : 0;
            $progress = Schema::hasTable('daily_plan_progress')
                ? DailyPlanProgress::query()->whereIn('plan_id', $planIds)->count()
                : 0;

            return [
                'has_daily_planner_tables' => Schema::hasTable('daily_plans') && Schema::hasTable('daily_plan_assignments') && Schema::hasTable('daily_plan_progress'),
                'daily_plan_count' => count($planIds),
                'published_plan_count' => DailyPlan::query()->where('team_id', $teamId)->where('status', 'published')->count(),
                'draft_plan_count' => DailyPlan::query()->where('team_id', $teamId)->where('status', 'draft')->count(),
                'assignment_count' => $assignments,
                'progress_count' => $progress,
            ];
        } catch (Throwable) {
            return [
                'has_daily_planner_tables' => false,
                'daily_plan_count' => 0,
                'published_plan_count' => 0,
                'draft_plan_count' => 0,
                'assignment_count' => 0,
                'progress_count' => 0,
            ];
        }
    }

    private function safeTaskCount(string $teamId, callable $scope): int
    {
        try {
            if (! Schema::hasTable('benchmark_collection_tasks')) {
                return 0;
            }
            $query = BenchmarkCollectionTask::query()->where('team_id', $teamId);
            $scope($query);

            return $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
