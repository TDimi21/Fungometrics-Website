<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Services\Planner\CoachOperatingHomeActionService;
use App\Services\Planner\CoachOperatingSystemHomeService;
use App\Services\Planner\CoachPlannerCommandCenterService;
use App\Services\Planner\CoachWeeklyReportExportService;
use App\Services\Planner\CoachWeeklyTeamReportService;
use App\Services\Planner\CommunicationRhythmService;
use App\Services\Planner\DevelopmentHealthAlertActionService;
use App\Services\Planner\DevelopmentHealthAlertService;
use App\Services\Planner\DevelopmentHealthTrendService;
use App\Services\Planner\DevelopmentProgramHealthService;
use App\Services\Planner\SeasonArchiveDeliveryPrepService;
use App\Services\Planner\SeasonArchiveDeliveryReviewService;
use App\Services\Planner\SeasonArchiveExportService;
use App\Services\Planner\SeasonCommunicationRhythmService;
use App\Services\Planner\SeasonDevelopmentArchiveService;
use App\Services\Planner\WeeklyPlannerRollupService;
use App\Services\Planner\WeeklyReportDeliveryPrepService;
use App\Services\Planner\WeeklyReportDeliveryReviewService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FmtrxEndToEndQaService
{
    private const ALL_AREAS = [
        'benchmark',
        'planner',
        'player_workout',
        'review',
        'trusted_data',
        'reports',
        'health',
        'operating_home',
        'privacy',
        'actions',
        'copy',
        'routes',
    ];

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function runTeamQa(string $teamId, array $options = []): array
    {
        $options = $this->normalizeOptions($options);
        $area = $this->nullableString($options['area'] ?? null);
        $checks = [];
        $evidence = [
            'options' => $options,
            'team_exists' => $this->teamExists($teamId),
            'roster_count' => $this->rosterCount($teamId),
            'dry_run' => true,
        ];

        foreach ([
            $this->checkBenchmarkIntelligence($teamId, $options),
            $this->checkPlannerIntegration($teamId, $options),
            $this->checkPlayerWorkoutFlow($teamId, $options),
            $this->checkReviewAndTrustedDataFlow($teamId, $options),
            $this->checkDevelopmentHealth($teamId, $options),
            $this->checkReportsAndCommunication($teamId, $options),
            $this->checkOperatingHome($teamId, $options),
            $this->checkPrivacy($teamId, $options),
            $this->checkActionSafety($teamId, $options),
            $this->checkCopyAndRoutes($teamId, $options),
        ] as $areaReport) {
            foreach ($areaReport['checks'] ?? [] as $check) {
                if ($area && ($check['area'] ?? null) !== $area) {
                    continue;
                }
                $checks[] = $check;
            }
            $evidence[$areaReport['area'] ?? 'unknown'] = $areaReport['evidence'] ?? [];
        }

        $summary = $this->buildQaSummary($checks);
        $failures = array_values(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'failed'));
        $warnings = array_values(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'warning'));

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'qa_status' => $summary['qa_status'],
            'summary' => $summary,
            'checks' => $checks,
            'failures' => $failures,
            'warnings' => $warnings,
            'recommended_fixes' => $this->recommendedFixes($checks),
            'evidence' => $evidence,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function checkBenchmarkIntelligence(string $teamId, array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);
        $checks = [];
        $evidence = [];

        [$teamIntelligence, $error] = $this->safePayload(fn (): array => app(TeamIntelligenceService::class)->build($teamId, $days));
        $checks[] = $this->payloadCheck(
            'benchmark.team_intelligence',
            'benchmark',
            'Team intelligence payload builds',
            $teamIntelligence,
            $error,
            'TeamIntelligenceService should return a safe payload or empty state.',
        );
        $evidence['team_intelligence_keys'] = array_keys($teamIntelligence);

        [$benchmarkProfile, $error] = $this->safePayload(fn (): array => app(TeamBenchmarkProfileService::class)->build($teamId, $days));
        $checks[] = $this->payloadCheck(
            'benchmark.team_profile',
            'benchmark',
            'Team benchmark profile builds',
            $benchmarkProfile,
            $error,
            'TeamBenchmarkProfileService should return benchmark_profile safely.',
        );
        $evidence['benchmark_player_count'] = $benchmarkProfile['player_count'] ?? null;
        $evidence['benchmark_metric_count'] = $benchmarkProfile['metric_count'] ?? null;

        [$decisionBrief, $error] = $this->safePayload(fn (): array => app(DecisionEngine::class)->buildTeamDecisionBrief($teamId, $days));
        $checks[] = $this->payloadCheck(
            'benchmark.decision_brief',
            'benchmark',
            'Decision brief builds',
            $decisionBrief,
            $error,
            'DecisionEngine should return a safe coach-facing decision brief.',
        );
        $evidence['primary_focus'] = $decisionBrief['primary_focus']['title'] ?? null;

        $rosterCount = $this->rosterCount($teamId);
        $playerCount = (int) ($benchmarkProfile['player_count'] ?? 0);
        $checks[] = $this->check(
            'benchmark.player_count',
            'benchmark',
            'Benchmark profile reflects roster when data exists',
            $rosterCount === 0 || $playerCount > 0 ? 'passed' : 'warning',
            $rosterCount === 0
                ? 'No roster players found; benchmark profile can be empty.'
                : ($playerCount > 0 ? 'Benchmark profile includes team players.' : 'Roster exists but benchmark profile reports no players.'),
            ['roster_count' => $rosterCount, 'benchmark_player_count' => $playerCount],
            $rosterCount > 0 && $playerCount === 0 ? 'Verify PlayerTeam joins and player intelligence snapshots for this team.' : null,
            'medium',
        );

        $sourceMix = $benchmarkProfile['source_mix'] ?? [];
        $checks[] = $this->check(
            'benchmark.source_mix',
            'benchmark',
            'Benchmark source mix is safe',
            is_array($sourceMix) ? 'passed' : 'warning',
            is_array($sourceMix) ? 'Source mix is present or safely empty.' : 'Source mix is not an array.',
            ['source_mix' => $sourceMix],
            is_array($sourceMix) ? null : 'Return source_mix as a structured array even when population learning is inactive.',
            'low',
        );

        $missingRows = $this->arrayValue($benchmarkProfile['missing_metrics'] ?? []);
        $duplicateMissing = $this->duplicateMissingRows($missingRows);
        $checks[] = $this->check(
            'benchmark.role_aware_missing',
            'benchmark',
            'Missing benchmark data is grouped safely',
            empty($duplicateMissing) ? 'passed' : 'warning',
            empty($duplicateMissing)
                ? 'Missing benchmark rows are deduplicated.'
                : 'Duplicate missing benchmark rows were found.',
            ['duplicate_rows' => $duplicateMissing, 'missing_metric_count' => count($missingRows)],
            empty($duplicateMissing) ? null : 'Deduplicate missing benchmark rows by metric and classification.',
            'medium',
        );

        $populationSourceActive = (float) ($sourceMix['population_share'] ?? $sourceMix['population_weight'] ?? 0) > 0;
        $checks[] = $this->check(
            'benchmark.research_fallback',
            'benchmark',
            'Research fallback remains available',
            ! $populationSourceActive || isset($sourceMix['research_share']) || isset($sourceMix['research_weight']) ? 'passed' : 'warning',
            $populationSourceActive
                ? 'Population learning is active with source mix evidence.'
                : 'Population learning is not carrying the benchmark yet; research fallback remains the primary source.',
            ['source_mix' => $sourceMix],
            null,
            'low',
        );

        return $this->areaReport('benchmark', $checks, $evidence);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function checkPlannerIntegration(string $teamId, array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);
        $checks = [];
        $evidence = [];

        [$collectionPlan, $error] = $this->safePayload(fn (): array => app(BenchmarkCollectionPlanner::class)->buildTeamCollectionPlan($teamId, $days));
        $checks[] = $this->payloadCheck(
            'planner.collection_plan',
            'planner',
            'Benchmark collection planner builds',
            $collectionPlan,
            $error,
            'BenchmarkCollectionPlanner should return a safe collection plan.',
        );
        $evidence['collection_priority'] = $collectionPlan['priority_level'] ?? null;

        [$assignments, $error] = $this->safePayload(fn (): array => app(BenchmarkTaskAssignmentService::class)->buildAssignableTasks($teamId, $days));
        $checks[] = $this->payloadCheck(
            'planner.task_assignments',
            'planner',
            'Benchmark task assignment preview builds',
            $assignments,
            $error,
            'BenchmarkTaskAssignmentService should return dry-run assignable tasks.',
        );
        $evidence['assignment_task_count'] = $assignments['task_count'] ?? null;

        [$practicePlan, $error] = $this->safePayload(fn (): array => app(CoachActionPracticePlanner::class)->buildPracticePlanFromCoachActions($teamId, $days, [
            'dry_run' => true,
        ]));
        $checks[] = $this->payloadCheck(
            'planner.suggested_plan',
            'planner',
            'Coach action practice plan builds',
            $practicePlan,
            $error,
            'CoachActionPracticePlanner should preview a plan without saving or publishing.',
        );
        $evidence['suggested_plan_title'] = $practicePlan['title'] ?? $practicePlan['plan_title'] ?? null;

        [$dailyPlanPreview, $error] = $this->safePayload(fn (): array => app(BenchmarkPracticePlanDailyPlannerAdapter::class)->previewMapping($teamId, $days));
        $checks[] = $this->payloadCheck(
            'planner.daily_plan_adapter',
            'planner',
            'Suggested plan maps to existing Daily Planner',
            $dailyPlanPreview,
            $error,
            'BenchmarkPracticePlanDailyPlannerAdapter should preview mapping into daily_plans.',
        );

        $tables = [
            'daily_plans' => Schema::hasTable('daily_plans'),
            'daily_plan_assignments' => Schema::hasTable('daily_plan_assignments'),
            'daily_plan_progress' => Schema::hasTable('daily_plan_progress'),
            'coach_practice_plans' => Schema::hasTable('coach_practice_plans'),
        ];
        $checks[] = $this->check(
            'planner.source_of_truth_tables',
            'planner',
            'Existing Daily Planner remains source of truth',
            $tables['daily_plans'] && $tables['daily_plan_assignments'] && $tables['daily_plan_progress'] ? 'passed' : 'failed',
            $tables['daily_plans']
                ? 'Daily Planner tables are available.'
                : 'daily_plans table is missing.',
            $tables,
            $tables['daily_plans'] ? null : 'Run the Daily Planner migrations before using planner intelligence.',
            $tables['daily_plans'] ? 'low' : 'critical',
        );

        $planIds = $this->teamDailyPlanIds($teamId);
        $evidence['daily_plan_count'] = count($planIds);
        $evidence['assignment_count'] = $this->tableCount(DailyPlanAssignment::query()->whereIn('plan_id', $planIds));
        $evidence['progress_count'] = $this->tableCount(DailyPlanProgress::query()->whereIn('plan_id', $planIds));

        $metadata = $this->latestBenchmarkItemMetadata($teamId);
        $checks[] = $this->check(
            'planner.benchmark_item_metadata',
            'planner',
            'Benchmark-generated blocks preserve metadata',
            $metadata['inspected'] === 0 || empty($metadata['missing']) ? 'passed' : 'warning',
            $metadata['inspected'] === 0
                ? 'No benchmark-generated Daily Plan items were found to inspect.'
                : (empty($metadata['missing']) ? 'Benchmark-generated Daily Plan items include source, tags, metrics, and coach instructions where available.' : 'Some benchmark-generated Daily Plan items are missing metadata.'),
            $metadata,
            empty($metadata['missing']) ? null : 'Ensure adapter items include source/tags/relatedMetrics/metrics_to_collect and instructions.',
            'medium',
        );

        return $this->areaReport('planner', $checks, $evidence);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function checkPlayerWorkoutFlow(string $teamId, array $options = []): array
    {
        $checks = [];
        $evidence = [];
        $component = base_path('resources/js/components/planner/PlayerWorkoutsPanel.vue');

        $checks[] = $this->fileContainsCheck(
            'player_workout.panel_exists',
            'player_workout',
            'Player workout panel exists',
            $component,
            ['Player Workouts'],
            'PlayerWorkoutsPanel should render the player-facing workout view.',
        );

        $checks[] = $this->fileContainsCheck(
            'player_workout.metric_entry',
            'player_workout',
            'Metric entry is wired into player workouts',
            $component,
            ['metric_values', 'actuals', 'submitted'],
            'Player workouts should preserve submitted metric values for review.',
        );

        $checks[] = $this->check(
            'player_workout.player_routes',
            'player_workout',
            'Player workout routes exist',
            $this->routeExists('GET', 'player/daily-plans') && $this->routeExists('POST', 'player/daily-plans/{id}/progress') ? 'passed' : 'warning',
            'Player daily plan read/progress endpoints were checked.',
            [
                'daily_plans_route' => $this->routeExists('GET', 'player/daily-plans'),
                'progress_route' => $this->routeExists('POST', 'player/daily-plans/{id}/progress'),
            ],
            'Verify player daily plan API routes if players cannot load or save workouts.',
            'high',
        );

        $planIds = $this->teamDailyPlanIds($teamId);
        $submittedTasks = $this->safeTaskCount($teamId, fn ($query) => $query->whereNotNull('submitted_payload'));
        $pendingSubmitted = $this->safeTaskCount($teamId, fn ($query) => $query->whereNotNull('submitted_payload')->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING));
        $trustedWithoutReview = $this->safeTaskCount($teamId, fn ($query) => $query
            ->whereNotNull('submitted_payload')
            ->whereNot('review_status', BenchmarkCollectionTask::REVIEW_APPROVED)
            ->whereNotNull('promoted_at'));

        $checks[] = $this->check(
            'player_workout.no_auto_trust',
            'player_workout',
            'Player submitted metrics do not auto-trust',
            $trustedWithoutReview === 0 ? 'passed' : 'failed',
            $trustedWithoutReview === 0
                ? 'No non-approved submitted metric values appear promoted.'
                : 'Some submitted metric values appear promoted without approved review status.',
            [
                'submitted_tasks' => $submittedTasks,
                'pending_submitted_tasks' => $pendingSubmitted,
                'trusted_without_approval' => $trustedWithoutReview,
            ],
            $trustedWithoutReview === 0 ? null : 'Block trusted promotion unless review_status is approved.',
            $trustedWithoutReview === 0 ? 'low' : 'critical',
        );

        $evidence = [
            'daily_plan_count' => count($planIds),
            'assignment_count' => $this->tableCount(DailyPlanAssignment::query()->whereIn('plan_id', $planIds)),
            'progress_count' => $this->tableCount(DailyPlanProgress::query()->whereIn('plan_id', $planIds)),
            'submitted_benchmark_tasks' => $submittedTasks,
        ];

        return $this->areaReport('player_workout', $checks, $evidence);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function checkReviewAndTrustedDataFlow(string $teamId, array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);
        $checks = [];
        $evidence = [];

        [$reviewSummary, $error] = $this->safePayload(fn (): array => app(BenchmarkTaskReviewService::class)->buildTeamReviewSummary($teamId));
        $checks[] = $this->payloadCheck(
            'review.summary',
            'review',
            'Coach review summary builds',
            $reviewSummary,
            $error,
            'BenchmarkTaskReviewService should return pending/approved/correction summary.',
        );
        $evidence['review_summary'] = $this->onlyKeys($reviewSummary, ['pending_count', 'approved_count', 'correction_requested_count', 'rejected_count']);

        [$promotionStatus, $error] = $this->safePayload(fn (): array => app(BenchmarkTrustedDataPromotionService::class)->buildPromotionStatus($teamId));
        $checks[] = $this->payloadCheck(
            'trusted_data.promotion_status',
            'trusted_data',
            'Trusted data promotion status builds',
            $promotionStatus,
            $error,
            'BenchmarkTrustedDataPromotionService should show approved values waiting for promotion.',
        );
        $evidence['promotion_status'] = $this->onlyKeys($promotionStatus, ['awaiting_promotion_count', 'promoted_count', 'failed_count']);

        $unsafePromotions = [
            'pending_promoted' => $this->safeTaskCount($teamId, fn ($query) => $query->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING)->whereNotNull('promoted_at')),
            'rejected_promoted' => $this->safeTaskCount($teamId, fn ($query) => $query->where('review_status', BenchmarkCollectionTask::REVIEW_REJECTED)->whereNotNull('promoted_at')),
            'correction_promoted' => $this->safeTaskCount($teamId, fn ($query) => $query->where('review_status', BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED)->whereNotNull('promoted_at')),
        ];
        $checks[] = $this->check(
            'trusted_data.approved_only',
            'trusted_data',
            'Only approved values are promoted',
            array_sum($unsafePromotions) === 0 ? 'passed' : 'failed',
            array_sum($unsafePromotions) === 0
                ? 'No pending/rejected/correction benchmark tasks appear promoted.'
                : 'Some non-approved benchmark tasks appear promoted.',
            $unsafePromotions,
            array_sum($unsafePromotions) === 0 ? null : 'Audit promoted benchmark tasks and require approved_payload before promotion.',
            array_sum($unsafePromotions) === 0 ? 'low' : 'critical',
        );

        $approved = BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->where('review_status', BenchmarkCollectionTask::REVIEW_APPROVED)
            ->whereNotNull('approved_payload')
            ->first();
        if ($approved) {
            [$preview, $error] = $this->safePayload(fn (): array => app(BenchmarkTrustedDataPromotionService::class)->previewPromotion((string) $approved->id));
            $checks[] = $this->payloadCheck(
                'trusted_data.preview_promotion',
                'trusted_data',
                'Approved task promotion preview builds',
                $preview,
                $error,
                'Promotion preview should be safe and read-only.',
            );
        } else {
            $checks[] = $this->check(
                'trusted_data.preview_promotion',
                'trusted_data',
                'Approved task promotion preview builds',
                'skipped',
                'No approved benchmark task with approved payload exists to preview.',
                [],
                null,
                'low',
            );
        }

        [$refreshStatus, $error] = $this->safePayload(fn (): array => app(BenchmarkRefreshService::class)->buildRefreshStatus($teamId, null, $days));
        $checks[] = $this->payloadCheck(
            'trusted_data.refresh_status',
            'trusted_data',
            'Benchmark refresh status builds',
            $refreshStatus,
            $error,
            'BenchmarkRefreshService should report refresh status without mutating data.',
        );

        [$currentState, $error] = $this->safePayload(fn (): array => app(BenchmarkDataQualityRescoreService::class)->buildCurrentState($teamId, null, $days));
        $checks[] = $this->payloadCheck(
            'trusted_data.rescore_state',
            'trusted_data',
            'Benchmark data quality state builds',
            $currentState,
            $error,
            'BenchmarkDataQualityRescoreService should produce current state safely.',
        );

        [$actionRanking, $error] = $this->safePayload(fn (): array => app(CoachActionReRankingService::class)->buildCurrentActionRanking($teamId, $days));
        $checks[] = $this->payloadCheck(
            'trusted_data.action_ranking',
            'trusted_data',
            'Coach action ranking builds',
            $actionRanking,
            $error,
            'CoachActionReRankingService should rank actions from current benchmark state.',
        );

        return $this->areaReport('review_trusted_data', $checks, $evidence);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function checkReportsAndCommunication(string $teamId, array $options = []): array
    {
        $weeks = $this->weeks($options['weeks'] ?? 8);
        $checks = [];
        $evidence = [];

        [$weeklyRollup, $error] = $this->safePayload(fn (): array => app(WeeklyPlannerRollupService::class)->buildTeamWeeklyRollup($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.weekly_rollup', 'reports', 'Weekly planner rollup builds', $weeklyRollup, $error, 'WeeklyPlannerRollupService should summarize the week safely.');

        [$teamReport, $error] = $this->safePayload(fn (): array => app(CoachWeeklyTeamReportService::class)->buildTeamReport($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.team_report', 'reports', 'Coach weekly team report builds', $teamReport, $error, 'CoachWeeklyTeamReportService should build a safe report payload.');

        [$parentExport, $error] = $this->safePayload(fn (): array => app(CoachWeeklyReportExportService::class)->buildExport($teamId, [
            'audience' => 'parents',
            'template_key' => 'parent_update',
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('privacy.weekly_parent_export', 'privacy', 'Parent weekly report export is safe', $parentExport, $error, 'Parent weekly exports should hide internal review details.');
        $checks[] = $this->privacyPayloadCheck('privacy.weekly_parent_export_keys', 'Parent weekly export hides private keys', $parentExport);

        [$deliveryPreview, $error] = $this->safePayload(fn (): array => app(WeeklyReportDeliveryPrepService::class)->prepareDelivery($teamId, [
            'audience' => 'parents',
            'template_key' => 'parent_update',
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.weekly_delivery_preview', 'reports', 'Weekly report delivery preview builds', $deliveryPreview, $error, 'Delivery prep should preview only and never send.');

        [$deliveryReview, $error] = $this->safePayload(fn (): array => app(WeeklyReportDeliveryReviewService::class)->buildDraftReview($teamId, [
            'audience' => 'parents',
            'template_key' => 'parent_update',
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.weekly_delivery_review', 'reports', 'Weekly delivery review builds', $deliveryReview, $error, 'Delivery review should require explicit send confirmation.');

        [$seasonArchive, $error] = $this->safePayload(fn (): array => app(SeasonDevelopmentArchiveService::class)->buildTeamSeasonArchive($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.season_archive', 'reports', 'Season development archive builds', $seasonArchive, $error, 'SeasonDevelopmentArchiveService should build the season summary safely.');

        [$seasonExport, $error] = $this->safePayload(fn (): array => app(SeasonArchiveExportService::class)->buildExport($teamId, [
            'audience' => 'parents',
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('privacy.season_parent_export', 'privacy', 'Parent season archive export is safe', $seasonExport, $error, 'Parent season archive exports should hide private player/review details.');
        $checks[] = $this->privacyPayloadCheck('privacy.season_parent_export_keys', 'Parent season export hides private keys', $seasonExport);

        [$seasonDeliveryPreview, $error] = $this->safePayload(fn (): array => app(SeasonArchiveDeliveryPrepService::class)->prepareDelivery($teamId, [
            'audience' => 'parents',
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.season_delivery_preview', 'reports', 'Season archive delivery preview builds', $seasonDeliveryPreview, $error, 'Season archive delivery prep should preview only.');

        [$seasonDeliveryReview, $error] = $this->safePayload(fn (): array => app(SeasonArchiveDeliveryReviewService::class)->buildDraftReview($teamId, [
            'audience' => 'parents',
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.season_delivery_review', 'reports', 'Season archive delivery review builds', $seasonDeliveryReview, $error, 'Season archive delivery review should require explicit send confirmation.');

        [$rhythm, $error] = $this->safePayload(fn (): array => app(CommunicationRhythmService::class)->buildTeamRhythm($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.communication_rhythm', 'reports', 'Communication rhythm builds', $rhythm, $error, 'CommunicationRhythmService should use delivery metadata safely.');

        [$seasonRhythm, $error] = $this->safePayload(fn (): array => app(SeasonCommunicationRhythmService::class)->buildTeamRhythm($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('reports.season_communication_rhythm', 'reports', 'Season communication rhythm builds', $seasonRhythm, $error, 'SeasonCommunicationRhythmService should use metadata only.');

        $evidence = [
            'weekly_report_status' => $teamReport['status'] ?? null,
            'season_archive_status' => $seasonArchive['status'] ?? null,
            'weekly_delivery_status' => $deliveryReview['status'] ?? null,
            'season_delivery_status' => $seasonDeliveryReview['status'] ?? null,
        ];

        return $this->areaReport('reports', $checks, $evidence);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function checkOperatingHome(string $teamId, array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);
        $weeks = $this->weeks($options['weeks'] ?? 8);
        $checks = [];
        $evidence = [];

        [$home, $error] = $this->safePayload(fn (): array => app(CoachOperatingSystemHomeService::class)->buildHome($teamId, [
            'days' => $days,
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('operating_home.payload', 'operating_home', 'Operating Home payload builds', $home, $error, 'CoachOperatingSystemHomeService should return a safe home payload.');
        $evidence['home_status'] = $home['home_status'] ?? null;
        $evidence['primary_next_action'] = $home['primary_next_action']['title'] ?? null;

        [$actions, $error] = $this->safePayload(fn (): array => app(CoachOperatingHomeActionService::class)->buildAvailableActions($teamId, $home, [
            'days' => $days,
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('actions.available', 'actions', 'Operating Home actions build', $actions, $error, 'CoachOperatingHomeActionService should expose safe action metadata.');

        [$commandCenter, $error] = $this->safePayload(fn (): array => app(CoachPlannerCommandCenterService::class)->buildForTeam($teamId, [
            'days' => $days,
        ]));
        $checks[] = $this->payloadCheck('operating_home.command_center', 'operating_home', 'Coach command center builds', $commandCenter, $error, 'CoachPlannerCommandCenterService should return current planner operations safely.');

        $dangerous = $this->dangerousActionSafety($actions['actions'] ?? []);
        $checks[] = $this->check(
            'actions.requires_confirmation',
            'actions',
            'Dangerous actions require confirmation',
            empty($dangerous['missing_confirmation']) ? 'passed' : 'failed',
            empty($dangerous['missing_confirmation'])
                ? 'All dangerous Operating Home actions expose confirmation requirements.'
                : 'Some dangerous actions do not require explicit confirmation.',
            $dangerous,
            empty($dangerous['missing_confirmation']) ? null : 'Set requires_confirmation=true for every dangerous action.',
            empty($dangerous['missing_confirmation']) ? 'low' : 'critical',
        );

        $checks[] = $this->check(
            'operating_home.route',
            'routes',
            'Operating Home routes exist',
            $this->routeExists('GET', 'coach/teams/{teamId}/operating-system-home')
                && $this->routeExists('GET', 'coach/teams/{teamId}/operating-system-home/actions')
                && $this->routeExists('POST', 'coach/teams/{teamId}/operating-system-home/actions/execute')
                ? 'passed'
                : 'warning',
            'Operating Home API routes were checked.',
            [
                'home' => $this->routeExists('GET', 'coach/teams/{teamId}/operating-system-home'),
                'actions' => $this->routeExists('GET', 'coach/teams/{teamId}/operating-system-home/actions'),
                'execute' => $this->routeExists('POST', 'coach/teams/{teamId}/operating-system-home/actions/execute'),
            ],
            'Verify Operating Home routes are registered behind auth middleware.',
            'high',
        );

        return $this->areaReport('operating_home', $checks, $evidence);
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @return array<string, mixed>
     */
    public function buildQaSummary(array $checks): array
    {
        $total = count($checks);
        $passed = count(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'passed'));
        $warnings = count(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'warning'));
        $failed = count(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'failed'));
        $skipped = count(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'skipped'));
        $criticalFailures = count(array_filter($checks, fn (array $check): bool => ($check['status'] ?? null) === 'failed' && ($check['severity'] ?? null) === 'critical'));

        $readiness = match (true) {
            $criticalFailures > 0 => 'blocked',
            $failed > 0 => 'needs_fix',
            $warnings > 0 => 'needs_polish',
            default => 'ready',
        };

        $qaStatus = match (true) {
            $failed > 0 => 'failed',
            $warnings > 0 => 'warning',
            $total === 0 => 'partial',
            default => 'passed',
        };

        return [
            'total_checks' => $total,
            'passed_checks' => $passed,
            'warning_checks' => $warnings,
            'failed_checks' => $failed,
            'skipped_checks' => $skipped,
            'critical_failures' => $criticalFailures,
            'readiness_label' => $readiness,
            'headline' => $this->headline($qaStatus, $warnings, $failed),
            'next_best_fix' => $this->recommendedFixes($checks)[0]['recommended_fix'] ?? null,
            'qa_status' => $qaStatus,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function checkDevelopmentHealth(string $teamId, array $options): array
    {
        $weeks = $this->weeks($options['weeks'] ?? 8);
        $checks = [];
        $evidence = [];

        [$health, $error] = $this->safePayload(fn (): array => app(DevelopmentProgramHealthService::class)->buildTeamHealthScore($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('health.score', 'health', 'Development health score builds', $health, $error, 'DevelopmentProgramHealthService should build an explainable score safely.');
        $evidence['health_score'] = $health['overall_score_0_100'] ?? null;

        [$trend, $error] = $this->safePayload(fn (): array => app(DevelopmentHealthTrendService::class)->buildTeamTrendline($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('health.trendline', 'health', 'Development health trendline builds', $trend, $error, 'DevelopmentHealthTrendService should handle missing periods safely.');

        [$alerts, $error] = $this->safePayload(fn (): array => app(DevelopmentHealthAlertService::class)->buildTeamAlerts($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('health.alerts', 'health', 'Development health alerts build', $alerts, $error, 'DevelopmentHealthAlertService should generate alerts without modifying data.');
        $evidence['alert_count'] = $alerts['alert_count'] ?? count($this->arrayValue($alerts['alerts'] ?? []));

        [$alertActions, $error] = $this->safePayload(fn (): array => app(DevelopmentHealthAlertActionService::class)->buildActionsForTeam($teamId, [
            'weeks' => $weeks,
        ]));
        $checks[] = $this->payloadCheck('health.alert_actions', 'health', 'Development health alert actions build', $alertActions, $error, 'Alert actions should map alerts to existing safe workflows.');

        return $this->areaReport('health', $checks, $evidence);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function checkPrivacy(string $teamId, array $options): array
    {
        $checks = [];
        $evidence = [];

        $coachOnlyRoutes = [
            ['GET', 'coach/teams/{teamId}/weekly-team-report'],
            ['POST', 'coach/teams/{teamId}/weekly-report/send-delivery-draft'],
            ['POST', 'coach/teams/{teamId}/season-archive/send-delivery-draft'],
            ['POST', 'intelligence/benchmark-tasks/{taskId}/promote'],
        ];

        foreach ($coachOnlyRoutes as [$method, $uri]) {
            $route = $this->findRoute($method, $uri);
            $middleware = $route ? $route->gatherMiddleware() : [];
            $checks[] = $this->check(
                'privacy.route.'.md5($method.$uri),
                'privacy',
                'Coach-only route is protected: '.$uri,
                $route && $this->hasAuthMiddleware($middleware) ? 'passed' : 'warning',
                $route
                    ? 'Route exists with middleware: '.implode(', ', $middleware)
                    : 'Route was not found.',
                ['method' => $method, 'uri' => $uri, 'middleware' => $middleware],
                $route ? 'Verify role/team authorization middleware for this coach-only endpoint.' : 'Register the expected coach route or update the QA route list.',
                'high',
            );
        }

        $evidence['coach_only_routes_checked'] = count($coachOnlyRoutes);

        return $this->areaReport('privacy', $checks, $evidence);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function checkActionSafety(string $teamId, array $options): array
    {
        [$actions, $error] = $this->safePayload(fn (): array => app(CoachOperatingHomeActionService::class)->buildAvailableActions($teamId, [], $options));
        if ($error) {
            return $this->areaReport('actions', [
                $this->check('actions.available_for_safety', 'actions', 'Action safety metadata builds', 'failed', $error, [], 'Fix CoachOperatingHomeActionService before action safety can be validated.', 'high'),
            ], []);
        }

        $dangerous = $this->dangerousActionSafety($actions['actions'] ?? []);
        $previewActions = array_values(array_filter($actions['actions'] ?? [], fn (array $action): bool => ($action['mode'] ?? null) === 'preview'));

        return $this->areaReport('actions', [
            $this->check(
                'actions.confirmation_required',
                'actions',
                'Dangerous actions require explicit confirmation',
                empty($dangerous['missing_confirmation']) ? 'passed' : 'failed',
                empty($dangerous['missing_confirmation']) ? 'Dangerous actions are guarded by confirmation metadata.' : 'Some dangerous actions are missing confirmation metadata.',
                $dangerous,
                empty($dangerous['missing_confirmation']) ? null : 'Require confirmation before publish/assign/send/approve/promote/republish actions.',
                empty($dangerous['missing_confirmation']) ? 'low' : 'critical',
            ),
            $this->check(
                'actions.preview_is_safe',
                'actions',
                'Preview actions are non-destructive',
                'passed',
                'Preview actions were inspected as metadata only; no executeAction mutation calls were made.',
                ['preview_action_count' => count($previewActions)],
                null,
                'low',
            ),
        ], [
            'dangerous_actions_checked' => $dangerous,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function checkCopyAndRoutes(string $teamId, array $options): array
    {
        $checks = [];
        $findings = $this->frontendCopyFindings();
        $checks[] = $this->check(
            'copy.user_facing_terms',
            'copy',
            'User-facing copy avoids raw technical terms',
            empty($findings) ? 'passed' : 'warning',
            empty($findings)
                ? 'No likely user-facing raw technical terms were found in key frontend files.'
                : 'Likely user-facing raw technical terms were found.',
            ['findings' => $findings],
            empty($findings) ? null : 'Replace raw technical terms with coach/player-friendly labels.',
            'medium',
        );

        $routeChecks = [
            ['GET', 'coach/teams/{team}/intelligence'],
            ['GET', 'coach/teams/{teamId}/planner-command-center'],
            ['GET', 'coach/teams/{teamId}/weekly-planner-rollup'],
            ['GET', 'coach/teams/{teamId}/weekly-team-report'],
            ['GET', 'coach/teams/{teamId}/season-development-archive'],
            ['GET', 'coach/teams/{teamId}/operating-system-home'],
        ];
        foreach ($routeChecks as [$method, $uri]) {
            $checks[] = $this->check(
                'routes.'.md5($method.$uri),
                'routes',
                'Expected route exists: '.$uri,
                $this->routeExists($method, $uri) ? 'passed' : 'warning',
                $this->routeExists($method, $uri) ? 'Route is registered.' : 'Route was not found.',
                ['method' => $method, 'uri' => $uri],
                $this->routeExists($method, $uri) ? null : 'Verify route URI/name after recent planner and intelligence phases.',
                'medium',
            );
        }

        return $this->areaReport('copy_routes', $checks, [
            'copy_finding_count' => count($findings),
            'route_checks' => count($routeChecks),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @return array<int, array<string, mixed>>
     */
    private function recommendedFixes(array $checks): array
    {
        return collect($checks)
            ->filter(fn (array $check): bool => in_array($check['status'] ?? null, ['failed', 'warning'], true) && ! empty($check['recommended_fix']))
            ->sortByDesc(fn (array $check): int => $this->severityRank((string) ($check['severity'] ?? 'low')))
            ->map(fn (array $check): array => [
                'check_id' => $check['check_id'],
                'area' => $check['area'],
                'severity' => $check['severity'],
                'title' => $check['title'],
                'recommended_fix' => $check['recommended_fix'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function areaReport(string $area, array $checks, array $evidence): array
    {
        return [
            'area' => $area,
            'checks' => $checks,
            'evidence' => $evidence,
        ];
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function check(string $id, string $area, string $title, string $status, string $message, array $details = [], ?string $recommendedFix = null, string $severity = 'low'): array
    {
        return [
            'check_id' => $id,
            'area' => $area,
            'title' => $title,
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'recommended_fix' => $recommendedFix,
            'severity' => $severity,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadCheck(string $id, string $area, string $title, array $payload, ?string $error, string $successMessage): array
    {
        if ($error) {
            return $this->check($id, $area, $title, 'failed', $error, [], 'Fix the service exception so this payload can fail safely.', 'high');
        }

        return $this->check(
            $id,
            $area,
            $title,
            ! empty($payload) ? 'passed' : 'warning',
            ! empty($payload) ? $successMessage : 'Payload returned empty but did not crash.',
            ['keys' => array_keys($payload)],
            ! empty($payload) ? null : 'Add or verify an empty-state payload for this workflow.',
            ! empty($payload) ? 'low' : 'medium',
        );
    }

    private function fileContainsCheck(string $id, string $area, string $title, string $path, array $needles, string $message): array
    {
        if (! is_file($path)) {
            return $this->check($id, $area, $title, 'failed', 'Required frontend file was not found.', ['path' => $path], 'Restore or update the expected frontend file path.', 'high');
        }

        $contents = file_get_contents($path) ?: '';
        $found = array_values(array_filter($needles, fn (string $needle): bool => str_contains($contents, $needle)));

        return $this->check(
            $id,
            $area,
            $title,
            ! empty($found) ? 'passed' : 'warning',
            ! empty($found) ? $message : 'Expected frontend markers were not found.',
            ['path' => $path, 'found' => $found, 'expected_any' => $needles],
            ! empty($found) ? null : 'Verify the player workout panel still supports benchmark-generated workflow display.',
            'medium',
        );
    }

    private function privacyPayloadCheck(string $id, string $title, array $payload): array
    {
        $unsafeKeys = $this->findUnsafeKeys($payload);

        return $this->check(
            $id,
            'privacy',
            $title,
            empty($unsafeKeys) ? 'passed' : 'warning',
            empty($unsafeKeys) ? 'No private/internal keys were found in the audience-filtered payload.' : 'Potential private/internal keys were found in an audience-filtered payload.',
            ['unsafe_keys' => array_slice($unsafeKeys, 0, 20)],
            empty($unsafeKeys) ? null : 'Review audience filtering for report/export payloads before sending externally.',
            'high',
        );
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
            if (! Schema::hasTable('player_teams')) {
                return 0;
            }

            return PlayerTeam::query()->where('team_id', $teamId)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, string>
     */
    private function teamDailyPlanIds(string $teamId): array
    {
        try {
            if (! Schema::hasTable('daily_plans')) {
                return [];
            }

            return DailyPlan::query()
                ->where('team_id', $teamId)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function tableCount(mixed $query): int
    {
        try {
            return $query->count();
        } catch (Throwable) {
            return 0;
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
     * @return array<string, mixed>
     */
    private function latestBenchmarkItemMetadata(string $teamId): array
    {
        try {
            $plan = DailyPlan::query()
                ->where('team_id', $teamId)
                ->latest('updated_at')
                ->first();

            if (! $plan) {
                return ['inspected' => 0, 'missing' => []];
            }

            $missing = [];
            $inspected = 0;
            foreach ($this->dailyPlanItems($plan) as $item) {
                if (! $this->isBenchmarkGeneratedItem($item)) {
                    continue;
                }

                $inspected++;
                $missingFields = [];
                foreach (['source', 'tags'] as $field) {
                    if (empty($item[$field] ?? null)) {
                        $missingFields[] = $field;
                    }
                }
                if (empty($item['relatedMetrics'] ?? null) && empty($item['metrics_to_collect'] ?? null)) {
                    $missingFields[] = 'metrics';
                }
                if (empty($item['why'] ?? null) && empty($item['instructions'] ?? null) && empty($item['note'] ?? null)) {
                    $missingFields[] = 'why_or_instructions';
                }
                if (! empty($missingFields)) {
                    $missing[] = [
                        'item' => $item['title'] ?? $item['name'] ?? $item['id'] ?? 'Daily Plan item',
                        'missing_fields' => $missingFields,
                    ];
                }
            }

            return ['inspected' => $inspected, 'missing' => $missing];
        } catch (Throwable $exception) {
            return ['inspected' => 0, 'missing' => [], 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dailyPlanItems(DailyPlan $plan): array
    {
        $items = [];
        foreach ($this->arrayValue($plan->buckets ?? []) as $bucket) {
            foreach ($this->arrayValue($bucket['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    private function isBenchmarkGeneratedItem(array $item): bool
    {
        $signals = [
            $item['source'] ?? null,
            ...$this->arrayValue($item['tags'] ?? []),
        ];

        return collect($signals)
            ->filter()
            ->contains(fn (mixed $signal): bool => str_contains(strtolower((string) $signal), 'benchmark'));
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function dangerousActionSafety(array $actions): array
    {
        $dangerous = [
            'publish_plan',
            'assign_plan',
            'send_reminder',
            'approve_selected_values',
            'request_corrections',
            'promote_trusted_data',
            'send_weekly_report',
            'send_season_packet',
            'republish_plan',
        ];

        $missing = [];
        $checked = [];
        foreach ($actions as $action) {
            $type = (string) ($action['action_type'] ?? $action['type'] ?? $action['id'] ?? '');
            if (! in_array($type, $dangerous, true)) {
                continue;
            }
            $checked[] = $type;
            if (! (bool) ($action['requires_confirmation'] ?? false)) {
                $missing[] = $type;
            }
        }

        return [
            'checked' => array_values(array_unique($checked)),
            'missing_confirmation' => array_values(array_unique($missing)),
            'expected_dangerous_actions' => $dangerous,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function frontendCopyFindings(): array
    {
        $terms = [
            'source_mix',
            'population_policy',
            'global_clean',
            'trusted_payload_only',
            'submitted_payload',
            'approved_payload',
            'payload',
            'bucket_count',
            'selected_bucket_key',
            'review_status',
            'metric_key',
            'command_center',
        ];
        $files = [
            'resources/js/pages/practice/DailyPlanner.vue',
            'resources/js/features/development/pages/TeamDevelopmentDashboard.vue',
            'resources/js/components/planner/PlayerWorkoutsPanel.vue',
        ];
        $findings = [];
        foreach ($files as $file) {
            $path = base_path($file);
            if (! is_file($path)) {
                continue;
            }
            $inTemplate = false;
            foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $lineNumber => $line) {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, '<template')) {
                    $inTemplate = true;
                }
                if (str_starts_with($trimmed, '</template')) {
                    $inTemplate = false;
                }
                foreach ($terms as $term) {
                    if (! str_contains(strtolower($line), strtolower($term)) || ! $this->looksUserFacingCopy($line, $term, $inTemplate)) {
                        continue;
                    }
                    $findings[] = [
                        'file' => $file,
                        'line' => $lineNumber + 1,
                        'term' => $term,
                        'excerpt' => trim($line),
                    ];
                }
            }
        }

        return $findings;
    }

    private function looksUserFacingCopy(string $line, string $term, bool $inTemplate): bool
    {
        $lower = strtolower($line);
        foreach ([':key=', ':class=', 'v-for=', 'v-if=', 'v-else', '@click=', 'data-dp-section=', 'data-'] as $skip) {
            if (str_contains($lower, $skip)) {
                return false;
            }
        }

        if ($inTemplate) {
            $staticLine = preg_replace('/\{\{.*?\}\}/', '', $line) ?? $line;

            return preg_match('/>[^<]*'.preg_quote($term, '/').'[^<]*</i', $staticLine) === 1
                || preg_match('/(?:title|aria-label|placeholder)=["\'][^"\']*'.preg_quote($term, '/').'[^"\']*["\']/i', $staticLine) === 1;
        }

        if (preg_match('/^\s*(const|let|return|if|for|while|switch|\}|\]|\[)/', $line) === 1) {
            return false;
        }

        preg_match_all('/([\'"`])((?:\\\\.|(?!\1).)*?)\1/s', $line, $matches);
        $text = collect($matches[2] ?? [])
            ->reject(fn (string $literal): bool => preg_match('/^[a-z0-9_:\-]+$/', $literal) === 1)
            ->map(fn (string $literal): string => preg_replace('/\$\{[^}]*\}/', '', $literal) ?? $literal)
            ->implode(' ');

        return str_contains(strtolower($text), strtolower($term));
    }

    private function routeExists(string $method, string $uri): bool
    {
        return $this->findRoute($method, $uri) !== null;
    }

    private function findRoute(string $method, string $uri): mixed
    {
        $method = strtoupper($method);
        $expected = $this->normalizeRouteUri($uri);

        foreach (Route::getRoutes() as $route) {
            if (! in_array($method, $route->methods(), true)) {
                continue;
            }

            if ($this->normalizeRouteUri($route->uri()) === $expected) {
                return $route;
            }
        }

        return null;
    }

    private function normalizeRouteUri(string $uri): string
    {
        $uri = trim($uri, '/');
        $uri = preg_replace('/^api\//', '', $uri) ?? $uri;

        return preg_replace('/\{[^}]+\}/', '{}', $uri) ?? $uri;
    }

    /**
     * @param array<int, string> $middleware
     */
    private function hasAuthMiddleware(array $middleware): bool
    {
        return collect($middleware)->contains(fn (string $item): bool => str_contains($item, 'auth'));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function duplicateMissingRows(array $rows): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = implode('|', [
                $row['metric_key'] ?? $row['display_name'] ?? 'unknown',
                $row['classification'] ?? $row['missing_type'] ?? 'unknown',
            ]);
            if (isset($seen[$key])) {
                $duplicates[] = $key;
            }
            $seen[$key] = true;
        }

        return array_values(array_unique($duplicates));
    }

    /**
     * @return array<int, string>
     */
    private function findUnsafeKeys(mixed $payload, string $path = ''): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $unsafe = [];
        $blocked = [
            'submitted_payload',
            'approved_payload',
            'promotion_result',
            'review_notes',
            'rejection_reason',
            'correction_message',
            'internal_notes',
            'staff_notes',
            'message_body',
            'packet_body',
        ];
        foreach ($payload as $key => $value) {
            $keyPath = $path === '' ? (string) $key : $path.'.'.$key;
            if (in_array((string) $key, $blocked, true)) {
                $unsafe[] = $keyPath;
            }
            if (is_array($value)) {
                $unsafe = array_merge($unsafe, $this->findUnsafeKeys($value, $keyPath));
            }
        }

        return array_values(array_unique($unsafe));
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    private function onlyKeys(array $payload, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $result[$key] = $payload[$key];
            }
        }

        return $result;
    }

    private function headline(string $status, int $warnings, int $failed): string
    {
        return match ($status) {
            'passed' => 'FMTRX end-to-end workflow is ready.',
            'warning' => 'FMTRX workflow is connected with polish items remaining.',
            'failed' => 'FMTRX workflow has issues that need fixing before production confidence.',
            default => 'FMTRX workflow QA is partially complete.',
        };
    }

    private function severityRank(string $severity): int
    {
        return [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ][$severity] ?? 0;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        return [
            'days' => $this->days($options['days'] ?? 365),
            'weeks' => $this->weeks($options['weeks'] ?? ($options['week_days'] ?? 8)),
            'week_days' => $this->days($options['week_days'] ?? 7),
            'include_frontend_payload_checks' => $this->bool($options['include_frontend_payload_checks'] ?? true),
            'include_privacy_checks' => $this->bool($options['include_privacy_checks'] ?? true),
            'include_action_safety_checks' => $this->bool($options['include_action_safety_checks'] ?? true),
            'include_route_checks' => $this->bool($options['include_route_checks'] ?? true),
            'dry_run' => true,
            'area' => $this->nullableString($options['area'] ?? null),
        ];
    }

    private function days(mixed $value): int
    {
        return max(7, min(365, (int) $value));
    }

    private function weeks(mixed $value): int
    {
        return max(1, min(52, (int) $value));
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
