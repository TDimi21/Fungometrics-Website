<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\CompleteCoachController;
use App\Http\Controllers\Api\Auth\CompletePlayerController;
use App\Http\Controllers\Api\Auth\GetUserCompleteController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\AccountDeletionController;
use App\Http\Controllers\Api\Auth\PasswordChange;
use App\Http\Controllers\Api\Auth\RecoverPasswordController;
use App\Http\Controllers\Api\Auth\RegisterCoachController;
use App\Http\Controllers\Api\Auth\RegisterPlayerController;
use App\Http\Controllers\Api\Auth\SendEmailRecoverController;
use App\Http\Controllers\Api\Coach\AddCoaches;
use App\Http\Controllers\Api\Coach\AddPlayers;
use App\Http\Controllers\Api\Coach\AddTeams;
use App\Http\Controllers\Api\Coach\EditCoach;
use App\Http\Controllers\Api\Coach\EditPlayers;
use App\Http\Controllers\Api\Coach\SetPlayerPassword;
use App\Http\Controllers\Api\Coach\EditTeams;
use App\Http\Controllers\Api\Coach\GetCoachesList;
use App\Http\Controllers\Api\Coach\GetLastSessions;
use App\Http\Controllers\Api\Coach\GetPerformanceOverview;
use App\Http\Controllers\Api\Coach\GetTeamLeaderboard;
use App\Http\Controllers\Api\Coach\GetPlayersList;
use App\Http\Controllers\Api\Coach\GetPracticePlans;
use App\Http\Controllers\Api\Coach\GetStatsBundle;
use App\Http\Controllers\Api\Coach\SavePracticePlan;
use App\Http\Controllers\Api\Coach\DeletePracticePlan;
// Daily Planner — its own domain, shared between app and web
use App\Http\Controllers\Api\Planner\AcknowledgeDailyPlanUpdate;
use App\Http\Controllers\Api\Planner\GetDailyPlans;
use App\Http\Controllers\Api\Planner\SaveDailyPlan;
use App\Http\Controllers\Api\Planner\DeleteDailyPlan;
use App\Http\Controllers\Api\Planner\GetDailyPlanCommandCenter;
use App\Http\Controllers\Api\Planner\GetMyWorkouts;
use App\Http\Controllers\Api\Planner\GetMyWorkout;
use App\Http\Controllers\Api\Planner\GetPlayerWeeklyPlans;
use App\Http\Controllers\Api\Planner\GetPlayerWeeklyCompletionSummary;
use App\Http\Controllers\Api\Planner\GetDailyPlanAcknowledgements;
use App\Http\Controllers\Api\Planner\GetDailyPlanReminderPreview;
use App\Http\Controllers\Api\Planner\GetDailyPlanUpdateStatus;
use App\Http\Controllers\Api\Planner\GetDailyPlanCompletionSummary;
use App\Http\Controllers\Api\Planner\GetTeamPlannerCommandCenter;
use App\Http\Controllers\Api\Planner\GetNextWeekCalendarDraft;
use App\Http\Controllers\Api\Planner\GetNextWeekPlanDraft;
use App\Http\Controllers\Api\Planner\GetCoachWeeklyReportExport;
use App\Http\Controllers\Api\Planner\GetCoachWeeklyTeamReport;
use App\Http\Controllers\Api\Planner\GetWeeklyPlannerRollup;
use App\Http\Controllers\Api\Planner\GetWeeklyReportTemplates;
use App\Http\Controllers\Api\Planner\MarkDailyPlanUpdateSeen;
use App\Http\Controllers\Api\Planner\GetPlayerDailyPlanCompletionSummary;
use App\Http\Controllers\Api\Planner\RunPlannerCommandCenterAction;
use App\Http\Controllers\Api\Planner\SaveNextWeekCalendarDraftDays;
use App\Http\Controllers\Api\Planner\SaveNextWeekPlanDraftDay;
use App\Http\Controllers\Api\Planner\SaveWorkoutProgress;
use App\Http\Controllers\Api\Planner\SendDailyPlanReminder;
use App\Http\Controllers\Api\Planner\SaveCustomDrill;
use App\Http\Controllers\Api\Planner\WeeklyPlanPublishController;
use App\Http\Controllers\Api\Planner\WeeklyReportNotesController;
use App\Http\Controllers\Api\Planner\CommunicationRhythmController;
use App\Http\Controllers\Api\Planner\WeeklyReportDeliveryPrepController;
use App\Http\Controllers\Api\Planner\WeeklyReportDeliveryAnalyticsController;
use App\Http\Controllers\Api\Planner\WeeklyReportDeliveryHistoryController;
use App\Http\Controllers\Api\Planner\WeeklyReportDeliveryReviewController;
use App\Http\Controllers\Api\Planner\SeasonCommunicationRhythmController;
use App\Http\Controllers\Api\Planner\CoachOperatingSystemHomeController;
use App\Http\Controllers\Api\Planner\DevelopmentProgramHealthController;
use App\Http\Controllers\Api\Planner\DevelopmentHealthTrendController;
use App\Http\Controllers\Api\Planner\DevelopmentHealthAlertsController;
use App\Http\Controllers\Api\Planner\DevelopmentHealthAlertActionsController;
use App\Http\Controllers\Api\Planner\SeasonDevelopmentArchiveController;
use App\Http\Controllers\Api\Planner\SeasonArchiveExportController;
use App\Http\Controllers\Api\Planner\SeasonArchiveDeliveryAnalyticsController;
use App\Http\Controllers\Api\Planner\SeasonArchiveDeliveryHistoryController;
use App\Http\Controllers\Api\Planner\SeasonArchiveDeliveryPrepController;
use App\Http\Controllers\Api\Planner\SeasonArchiveDeliveryReviewController;
use App\Http\Controllers\Api\Planner\GetCustomDrills;
use App\Http\Controllers\Api\Planner\DeleteCustomDrill;
use App\Http\Controllers\Api\Planner\GetDrillLibrary;
use App\Http\Controllers\Api\Planner\GetWorkoutCompletions;
use App\Http\Controllers\Api\Planner\GetDailyPlanProgress;
use App\Http\Controllers\Api\Planner\SaveCoachWorkoutReview;
// Player sub-groups — reusable assign presets, shared app↔web
use App\Http\Controllers\Api\Coach\GetPlayerGroups;
use App\Http\Controllers\Api\Coach\SavePlayerGroup;
use App\Http\Controllers\Api\Coach\DeletePlayerGroup;
use App\Http\Controllers\Api\Coach\GetFieldPresets;
use App\Http\Controllers\Api\Coach\SaveFieldPreset;
use App\Http\Controllers\Api\Coach\DeleteFieldPreset;
use App\Http\Controllers\Api\Coach\AssessmentDraftController;
use App\Http\Controllers\Api\Coach\AssessmentInsightController;
use App\Http\Controllers\Api\Coach\TeamInsightController;
use App\Http\Controllers\Api\Coach\TeamBenchmarkOverrideController;
use App\Http\Controllers\Api\Coach\GetTeamById;
use App\Http\Controllers\Api\Coach\GetTeamCode;
use App\Http\Controllers\Api\Coach\GetPlayerDevelopmentBoard;
use App\Http\Controllers\Api\Coach\GetPlayerDevelopmentDashboard;
use App\Http\Controllers\Api\Coach\GetTeamPlayerCards;
use App\Http\Controllers\Api\Coach\GetTeamsPlayersV2;
use App\Http\Controllers\Api\Coach\RemoveCoachFromTeam;
use App\Http\Controllers\Api\Coach\RemovePlayers;
use App\Http\Controllers\Api\Coach\RemoveTeam;
use App\Http\Controllers\Api\DashBoard\GetDataCharts;
use App\Http\Controllers\Api\DashBoard\GetDataGraphics;
use App\Http\Controllers\Api\DashBoard\GetPlayerCompareStats;
use App\Http\Controllers\Api\DashBoard\GetPlayerPitchStats;
use App\Http\Controllers\Api\DashBoard\GetPlayerLiveABPitchStats;
use App\Http\Controllers\Api\DashBoard\GetTeamPitchStats;
use App\Http\Controllers\Api\DashBoard\GetTeamLiveABPitchStats;
use App\Http\Controllers\Api\DashBoard\GetTopTenResults;
use App\Http\Controllers\Api\Player\GetBattingPractices;
use App\Http\Controllers\Api\Player\GetBullpenPractices;
use App\Http\Controllers\Api\Player\GetCagePractices;
use App\Http\Controllers\Api\Player\GetCreatedPractices;
use App\Http\Controllers\Api\Player\GetDashboardSummary;
use App\Http\Controllers\Api\Player\GetFitness;
use App\Http\Controllers\Api\Player\GetLiveABPractices;
use App\Http\Controllers\Api\Player\GetTrainingPractices;
use App\Http\Controllers\Api\Player\JoinTeamByCode;
use App\Http\Controllers\Api\Player\JoinAuthenticatedPlayerTeam;
use App\Http\Controllers\Api\Player\GetPlayerFilteredStatistics;
use App\Http\Controllers\Api\Player\GetAthleticPerformance;
use App\Http\Controllers\Api\Player\SaveFitness;
use App\Http\Controllers\Api\Player\SetPlayerCredentials;
use App\Http\Controllers\Api\Coach\SaveAssessment;
use App\Http\Controllers\Api\Coach\CalculateAthleticPerformance;
use App\Http\Controllers\Api\Coach\GetPlayerAssessments;
use App\Http\Controllers\Api\Coach\GetTeamAssessments;
use App\Http\Controllers\Api\ScoresStatisticPlayers;
use App\Http\Controllers\Api\GetPlayerPitchVelocityZones;
use App\Http\Controllers\Api\GetPlayerSmTakeZones;
use App\Http\Controllers\Api\IntelligenceController;
use App\Http\Controllers\Api\SearchCoaches;
use App\Http\Controllers\Api\SearchPlayers;
use App\Http\Controllers\Api\Sessions\GetAllPracticesByModes;
use App\Http\Controllers\Api\Sessions\GetExitVelocityPracticeResult;
use App\Http\Controllers\Api\Sessions\GetListLiveABSessions;
use App\Http\Controllers\Api\Sessions\GetLongTossPracticeResult;
use App\Http\Controllers\Api\Sessions\GetPracticeSessionByMode;
use App\Http\Controllers\Api\Sessions\GetPracticesSessionByType;
use App\Http\Controllers\Api\Sessions\GetTeamsPracticesSessionByMode;
use App\Http\Controllers\Api\Sessions\GetTeamsPracticesSessionsByType;
use App\Http\Controllers\Api\Sessions\GetWeightBallPracticeResult;
use App\Http\Controllers\Api\Sessions\Results\GetBattingPracticeResults;
use App\Http\Controllers\Api\Sessions\Results\GetBullpenPracticeResults;
use App\Http\Controllers\Api\Sessions\Results\GetCagePracticeResults;
use App\Http\Controllers\Api\Sessions\Results\GetLiveABPracticeResults;
use App\Http\Controllers\Api\Sessions\Results\ListSmsResults;
use App\Http\Controllers\Api\Sessions\Results\SendSmsResults;
use App\Http\Controllers\Api\Training\AddNewLiveABSession;
use App\Http\Controllers\Api\Training\AddNewSession;
use App\Http\Controllers\Api\Training\AddPlayerToTraining;
use App\Http\Controllers\Api\Training\DeletePractice;
use App\Http\Controllers\Api\Training\FilterTrainings;
use App\Http\Controllers\Api\Training\FinishPractice;
use App\Http\Controllers\Api\Training\GetSession;
use App\Http\Controllers\Api\Training\Result\EditBattingResultPractice;
use App\Http\Controllers\Api\Training\Result\EditBullpenResultPractice;
use App\Http\Controllers\Api\Training\Result\EditCageResultPractice;
use App\Http\Controllers\Api\Training\Result\EstimateCageBallFlight;
use App\Http\Controllers\Api\Training\Result\EditExitVelocityResultPractice;
use App\Http\Controllers\Api\Training\Result\EditLiveABResultPractice;
use App\Http\Controllers\Api\Training\Result\EditLongTossResultPractice;
use App\Http\Controllers\Api\Training\Result\EditWeightBallResultPractice;
use App\Http\Controllers\Api\Training\Result\GetBattingResultPractice;
use App\Http\Controllers\Api\Training\Result\GetBullpenResultPractice;
use App\Http\Controllers\Api\Training\Result\GetCageResultPractice;
use App\Http\Controllers\Api\Training\Result\GetExitVelocityResultPractice;
use App\Http\Controllers\Api\Training\Result\GetLiveABResultPractice;
use App\Http\Controllers\Api\Training\Result\GetLongTossResultPractice;
use App\Http\Controllers\Api\Training\Result\GetWeightBallResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveBattingResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveBullpenResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveCageResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveExitVelocityResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveLiveABResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveLongTossResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveWeightBallResultPractice;
use App\Http\Controllers\Api\Training\Result\SaveArmCareResultPractice;
use App\Http\Controllers\Api\Training\Result\GetArmCareResults;
use App\Http\Controllers\Api\Training\Result\SaveScriptedBpPlan;
use App\Http\Controllers\Api\Training\Result\SaveScriptedBpSwing;
use App\Http\Controllers\Api\Sessions\Results\GetScriptedBpResults;
use App\Http\Controllers\Api\Admin\UpdateUserPlan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Billing\RevenueCatWebhookController;
use App\Http\Controllers\Api\Billing\RevenueCatSyncController;
use App\Http\Controllers\Api\Billing\RevenueCatProductsController;
use App\Http\Controllers\Api\DataHub\InspectTrackManFile;
use App\Http\Controllers\Api\DataHub\DictionaryController;
use App\Http\Controllers\Api\DataHub\PlayerMappingController;
use App\Http\Controllers\Api\DataHub\FmtrxTemplateController;

Route::post('login', LoginController::class);
Route::post('billing/revenuecat/webhook', RevenueCatWebhookController::class)->middleware('throttle:120,1');

Route::post('/forgot-password', SendEmailRecoverController::class)->middleware(['guest']);

Route::post('/recover-password', RecoverPasswordController::class)->middleware(['guest']);
Route::get('/complete/{claim}', GetUserCompleteController::class)
    ->middleware(['guest', 'throttle:20,1', 'account.claim']);
Route::post('/complete/{claim}/coach', CompleteCoachController::class)
    ->middleware(['guest', 'throttle:5,1', 'account.claim:coach']);
Route::post('/complete/{claim}/player', CompletePlayerController::class)
    ->middleware(['guest', 'throttle:5,1', 'account.claim:player']);

Route::middleware(['auth:sanctum', 'route.scope'])->group(function (): void {
    Route::post('data-hub/inspect', InspectTrackManFile::class)
        ->middleware(['plan:data_hub_import', 'throttle:10,1']);
    Route::get('data-hub/imports/{batch}/rapsodo-report', \App\Http\Controllers\Api\DataHub\RapsodoPitchingSessionReportController::class);
    Route::get('data-hub/rapsodo-reports', \App\Http\Controllers\Api\DataHub\RapsodoReportIndexController::class);
    Route::middleware('plan:data_hub_import')->prefix('data-hub')->group(function (): void {
        Route::get('dictionary', [DictionaryController::class, 'index']);
        Route::post('mappings/resolve', [DictionaryController::class, 'resolve'])->middleware('throttle:30,1');
        Route::post('mappings/approve', [DictionaryController::class, 'approve'])->middleware('throttle:10,1');
        Route::get('unknown-columns', [DictionaryController::class, 'unknown']);
        Route::patch('unknown-columns/{unknown}', [DictionaryController::class, 'updateUnknown'])->middleware('throttle:30,1');
        Route::post('concept-submissions', [DictionaryController::class, 'submit'])->middleware('throttle:10,1');
        Route::get('player-mappings/roster', [PlayerMappingController::class, 'roster']);
        Route::post('player-mappings/approve', [PlayerMappingController::class, 'approve'])->middleware('throttle:20,1');
        Route::post('imports/blast', [\App\Http\Controllers\Api\DataHub\BlastImportController::class, 'store'])->middleware('throttle:10,1');
        Route::post('imports/rapsodo', [\App\Http\Controllers\Api\DataHub\RapsodoImportController::class, 'store'])->middleware('throttle:10,1');
        Route::post('imports/generic', [\App\Http\Controllers\Api\DataHub\GenericImportController::class, 'store'])->middleware('throttle:10,1');
        Route::get('imports', [\App\Http\Controllers\Api\DataHub\BlastImportController::class, 'history']);
        Route::get('imports/{batch}/blast-report', \App\Http\Controllers\Api\DataHub\BlastSessionDevelopmentReportController::class);
        Route::get('players/{player}/metrics', [\App\Http\Controllers\Api\DataHub\BlastImportController::class, 'playerMetrics']);
        Route::get('templates', [FmtrxTemplateController::class, 'index']);
        Route::get('templates/download', [FmtrxTemplateController::class, 'download'])->middleware('throttle:20,1');
    });
    Route::post('auth/web-session', \App\Http\Controllers\Api\Auth\CreateWebSession::class)->middleware('throttle:10,1');
    Route::post('logout', \App\Http\Controllers\Api\Auth\LogoutController::class)->middleware('throttle:20,1');
    Route::get('me/access', \App\Http\Controllers\Api\Access\GetMyAccess::class);
    Route::get('me/account-deletion/status', [AccountDeletionController::class, 'status'])
        ->middleware('throttle:20,1');
    Route::post('me/account-deletion/authorize', [AccountDeletionController::class, 'authorizeDeletion'])
        ->middleware('throttle:3,10');
    Route::delete('me/account', [AccountDeletionController::class, 'destroy'])
        ->middleware('throttle:3,10');
    Route::post('me/billing/revenuecat/sync', RevenueCatSyncController::class)->middleware('throttle:10,1');
    Route::get('me/billing/revenuecat/products', RevenueCatProductsController::class);
    Route::post('/edit/players/{id}', EditPlayers::class);
    Route::get('player/me', \App\Http\Controllers\Api\Player\GetMe::class);
    Route::post('player/fitness', SaveFitness::class);
    Route::get('player/fitness/{id}', GetFitness::class);
    Route::get('players/{player}/athletic-performance', GetAthleticPerformance::class);
    Route::middleware('plan:view_assessment_reports')->post('assessments/{assessment}/calculate-api', CalculateAthleticPerformance::class);
    Route::middleware('ability:coach')->post('assessments', SaveAssessment::class);
    Route::middleware('plan:view_assessment_reports')->get('assessments/player/{player}', GetPlayerAssessments::class);
    Route::middleware('plan:view_assessment_reports')->get('assessments/team/{team}', GetTeamAssessments::class);
    Route::get('dashboard/{team}', GetDataGraphics::class);
    Route::get('player-compare/{player}', GetPlayerCompareStats::class);
    Route::get('pitcher-stats/team', GetTeamPitchStats::class);
    Route::get('pitcher-liveab-stats/team', GetTeamLiveABPitchStats::class);
    Route::get('pitcher-stats/{player}', GetPlayerPitchStats::class);
    Route::get('pitcher-liveab-stats/{player}', GetPlayerLiveABPitchStats::class);
    Route::post('charts', GetDataCharts::class);
    Route::post('table/{team}', GetTopTenResults::class);
    Route::post('password', PasswordChange::class);

});

Route::prefix('player')->group(function (): void {
    Route::post('register', RegisterPlayerController::class);
    Route::middleware('throttle:5,1')->post('join', JoinTeamByCode::class);
    Route::middleware(['auth:sanctum', 'ability:profile-claim'])->group(function (): void {
        Route::post('set-credentials', SetPlayerCredentials::class); // first-time email+password setup after claim
    });
    Route::middleware(['auth:sanctum', 'ability:player', 'route.scope'])->group(function (): void {
        Route::middleware('throttle:10,1')->post('teams/join', JoinAuthenticatedPlayerTeam::class);
        Route::get('sessions/batting', GetBattingPractices::class);
        Route::get('sessions/bullpen', GetBullpenPractices::class);
        Route::get('sessions/cage', GetCagePractices::class);
        Route::get('sessions/training', GetTrainingPractices::class);
        Route::get('sessions/liveab', GetLiveABPractices::class);
        Route::get('sessions/created', GetCreatedPractices::class);
        Route::get('dashboard-summary', GetDashboardSummary::class);
        Route::get('statistics/{player}', ScoresStatisticPlayers::class);
        Route::get('benchmark-tasks', [IntelligenceController::class, 'listPlayerBenchmarkTasks']);
        Route::get('benchmark-tasks/{taskId}', [IntelligenceController::class, 'showPlayerBenchmarkTask']);
        Route::get('benchmark-tasks/{taskId}/completion-workflow', [IntelligenceController::class, 'playerBenchmarkTaskCompletionWorkflow']);
        Route::get('benchmark-tasks/{taskId}/review-status', [IntelligenceController::class, 'playerBenchmarkTaskReviewStatus']);
        Route::post('benchmark-tasks/{taskId}/start', [IntelligenceController::class, 'startPlayerBenchmarkTask']);
        Route::post('benchmark-tasks/{taskId}/complete', [IntelligenceController::class, 'completePlayerBenchmarkTask']);
        Route::post('benchmark-tasks/{taskId}/complete-with-payload', [IntelligenceController::class, 'completePlayerBenchmarkTaskWithPayload']);
        Route::post('benchmark-tasks/{taskId}/dismiss', [IntelligenceController::class, 'dismissPlayerBenchmarkTask']);
        Route::middleware('plan:development_graphs')->get('development/players/{player}', GetPlayerDevelopmentDashboard::class);
        Route::middleware('plan:development_graphs')->get('development/teams/{team}/players/{player}', GetPlayerDevelopmentDashboard::class);
        Route::middleware('plan:development_graphs')->get('intelligence', [IntelligenceController::class, 'selfPlayer']);

        // Daily Planner (player side) — "My Workouts" + progress
        Route::get('weekly-plans', GetPlayerWeeklyPlans::class);
        Route::get('weekly-completion-summary', GetPlayerWeeklyCompletionSummary::class);
        Route::get('daily-plans', GetMyWorkouts::class);
        Route::get('daily-plans/{id}', GetMyWorkout::class);
        Route::get('daily-plans/{dailyPlanId}/completion-summary', GetPlayerDailyPlanCompletionSummary::class);
        Route::get('daily-plans/{id}/update-status', GetDailyPlanUpdateStatus::class);
        Route::post('daily-plans/{id}/acknowledge-update', AcknowledgeDailyPlanUpdate::class);
        Route::post('daily-plans/{id}/mark-update-seen', MarkDailyPlanUpdateSeen::class);
        Route::post('daily-plans/{id}/progress', SaveWorkoutProgress::class);
    });
});

Route::prefix('coach')->group(function (): void {
    Route::post('register', RegisterCoachController::class);
    Route::middleware(['auth:sanctum', 'route.scope'])->get('/teams/{teamId}/operating-system-home', [CoachOperatingSystemHomeController::class, 'team']);
    Route::middleware(['auth:sanctum', 'route.scope'])->get('/teams/{teamId}/operating-system-home/actions', [CoachOperatingSystemHomeController::class, 'actions']);
    Route::middleware(['auth:sanctum', 'route.scope'])->post('/teams/{teamId}/operating-system-home/actions/execute', [CoachOperatingSystemHomeController::class, 'executeAction']);
    Route::middleware(['auth:sanctum', 'ability:coach', 'route.scope'])->group(function (): void {
        Route::post('/players/{id}/set-password', SetPlayerPassword::class);
        Route::middleware('plan:add_team')->post('/add/teams', AddTeams::class);
        Route::post('/edit', EditCoach::class);
        Route::middleware('plan:edit_team')->post('/edit/teams/{team}', EditTeams::class);
        Route::post('/add/players', AddPlayers::class);
        Route::post('/remove/players', RemovePlayers::class);
        Route::delete('/remove/coach/{id}', RemoveCoachFromTeam::class);
        Route::middleware('plan:sms_results')->get('/list/results/{practice}', ListSmsResults::class);
        Route::delete('/remove/team/{id}', RemoveTeam::class);
        Route::post('/add/coaches', AddCoaches::class);
        Route::get('/roster/coaches', GetCoachesList::class);
        Route::get('/roster/players', GetPlayersList::class);
        Route::get('/teams', GetTeamsPlayersV2::class);
        Route::get('/teams/{id}', GetTeamById::class);
        Route::get('/teams/{id}/code', GetTeamCode::class);  // retrieve join code for a team
        Route::middleware('plan:view_player_cards')->get('/teams/{id}/player-cards', GetTeamPlayerCards::class);
        Route::middleware('plan:view_advanced_stats')->get('/teams/{id}/player-development-board', GetPlayerDevelopmentBoard::class);
        Route::middleware('plan:view_advanced_stats')->get('/teams/{team}/intelligence', [IntelligenceController::class, 'team']);
        Route::middleware('plan:view_advanced_stats')->get('/teams/{team}/players/{player}/intelligence', [IntelligenceController::class, 'player']);
        Route::middleware('plan:view_advanced_stats')->get('/teams/{teamId}/launch-readiness', [IntelligenceController::class, 'launchReadiness']);
        Route::middleware('plan:view_advanced_stats')->get('/development/teams/{team}/players/{player}', GetPlayerDevelopmentDashboard::class);
        Route::get('/sessions/lasts/{team}', GetLastSessions::class);
        // One call returning recent sessions' detail bundled by type (kills the
        // Stats screen's per-session N+1 fetch).
        Route::middleware(['plan:view_team_stats', 'plan:view_session_report'])->get('/stats/bundle/{team}', GetStatsBundle::class);
        Route::middleware('plan:performance_overview')->get('/performance-overview/{team}', GetPerformanceOverview::class);
        Route::middleware('plan:performance_overview')->get('/leaderboard/{team}', GetTeamLeaderboard::class);
        Route::middleware('plan:liveab_sessions')->post('/trainingab', AddNewLiveABSession::class);
        Route::middleware('plan:liveab_sessions')->get('/statistics/{practice}/liveab', GetLiveABPracticeResults::class);
        Route::middleware('throttle:30,1')->get('/search/players', SearchPlayers::class);
        Route::middleware('throttle:30,1')->get('/search/coaches', SearchCoaches::class);
        Route::get('/statistics/{player}', ScoresStatisticPlayers::class);
        Route::get('/pitcher/velocity/{player}', GetPlayerPitchVelocityZones::class);
        Route::get('/pitcher/smtake/{player}', GetPlayerSmTakeZones::class);
        Route::post('/lineup/{training}', AddPlayerToTraining::class);
        Route::middleware('plan:sms_results')->post('/send/results/{practice}', SendSmsResults::class);

        // Practice Planner — team-shared, synced between app and web
        Route::middleware('plan:planner_create')->get('/practice-plans', GetPracticePlans::class);
        Route::middleware('plan:planner_create')->post('/practice-plans', SavePracticePlan::class);
        Route::middleware('plan:planner_create')->delete('/practice-plans/{id}', DeletePracticePlan::class);

        // Daily Planner (coach authoring) — synced between app and web
        Route::middleware('plan:planner_create')->get('/daily-plans', GetDailyPlans::class);
        Route::middleware('plan:planner_create')->post('/daily-plans', SaveDailyPlan::class);
        Route::middleware('plan:planner_create')->delete('/daily-plans/{id}', DeleteDailyPlan::class);
        // Coach reviews player results: all players' progress for a plan, + per-player review.
        Route::middleware('plan:view_workout_progress')->get('/daily-plans/{id}/progress', GetDailyPlanProgress::class);
        Route::middleware('plan:view_workout_progress')->get('/daily-plans/{dailyPlanId}/completion-summary', GetDailyPlanCompletionSummary::class);
        Route::middleware('plan:view_workout_progress')->post('/daily-plans/{planId}/players/{playerId}/review', SaveCoachWorkoutReview::class);
        Route::middleware('plan:view_workout_progress')->get('/daily-plans/{dailyPlanId}/acknowledgements', GetDailyPlanAcknowledgements::class);
        Route::middleware('plan:view_workout_progress')->get('/daily-plans/{dailyPlanId}/reminder-preview', GetDailyPlanReminderPreview::class);
        Route::middleware('plan:assign_workouts')->post('/daily-plans/{dailyPlanId}/send-reminder', SendDailyPlanReminder::class);
        Route::middleware('plan:assign_workouts')->post('/daily-plans/{dailyPlanId}/send-reminder-to-players', SendDailyPlanReminder::class);
        Route::middleware(['plan:planner_create', 'plan:ai_analytics'])->get('/daily-plans/{dailyPlanId}/update-suggestions', [IntelligenceController::class, 'dailyPlanUpdateSuggestions']);
        Route::middleware(['plan:planner_create', 'plan:ai_analytics'])->post('/daily-plans/{dailyPlanId}/apply-update-suggestions', [IntelligenceController::class, 'applyDailyPlanUpdateSuggestions']);
        Route::middleware('plan:planner_create')->get('/daily-plans/{dailyPlanId}/republish-review', [IntelligenceController::class, 'dailyPlanRepublishReview']);
        Route::middleware('plan:planner_create')->post('/daily-plans/{dailyPlanId}/republish-review/preview', [IntelligenceController::class, 'previewDailyPlanRepublishReview']);
        Route::middleware('plan:planner_create')->post('/daily-plans/{dailyPlanId}/republish-review/apply', [IntelligenceController::class, 'applyDailyPlanRepublishReview']);
        Route::middleware('plan:planner_create')->post('/daily-plans/{dailyPlanId}/republish', [IntelligenceController::class, 'republishDailyPlan']);
        Route::middleware('plan:planner_create')->get('/daily-plans/{dailyPlanId}/revisions/compare', [IntelligenceController::class, 'compareDailyPlanRevisions']);
        Route::middleware('plan:planner_create')->get('/daily-plans/{dailyPlanId}/revisions/{revisionId}', [IntelligenceController::class, 'showDailyPlanRevision']);
        Route::middleware('plan:planner_create')->get('/daily-plans/{dailyPlanId}/revisions', [IntelligenceController::class, 'listDailyPlanRevisions']);
        Route::middleware('plan:planner_create')->get('/daily-plans/{dailyPlanId}/command-center', GetDailyPlanCommandCenter::class);
        Route::middleware('plan:planner_create')->get('/teams/{teamId}/planner-command-center', GetTeamPlannerCommandCenter::class);
        Route::middleware('plan:planner_create')->post('/teams/{teamId}/planner-command-center/action', RunPlannerCommandCenterAction::class);
        Route::middleware('plan:planner_create')->get('/teams/{teamId}/weekly-planner-rollup', GetWeeklyPlannerRollup::class);
        Route::get('/teams/{teamId}/weekly-team-report', GetCoachWeeklyTeamReport::class);
        Route::get('/teams/{teamId}/communication-rhythm', [CommunicationRhythmController::class, 'team']);
        Route::get('/teams/{teamId}/season-communication-rhythm', [SeasonCommunicationRhythmController::class, 'team']);
        Route::get('/teams/{teamId}/development-program-health', [DevelopmentProgramHealthController::class, 'team']);
        Route::get('/teams/{teamId}/development-health-trendline', [DevelopmentHealthTrendController::class, 'team']);
        Route::get('/teams/{teamId}/development-health-alerts', [DevelopmentHealthAlertsController::class, 'team']);
        Route::get('/teams/{teamId}/development-health-alert-actions', [DevelopmentHealthAlertActionsController::class, 'index']);
        Route::post('/teams/{teamId}/development-health-alert-actions/execute', [DevelopmentHealthAlertActionsController::class, 'execute']);
        Route::get('/teams/{teamId}/season-development-archive', [SeasonDevelopmentArchiveController::class, 'team']);
        Route::get('/teams/{teamId}/season-archive/export', [SeasonArchiveExportController::class, 'team']);
        Route::get('/teams/{teamId}/season-archive/delivery-preview', [SeasonArchiveDeliveryPrepController::class, 'preview']);
        Route::post('/teams/{teamId}/season-archive/create-delivery-draft', [SeasonArchiveDeliveryPrepController::class, 'createDraft']);
        Route::post('/teams/{teamId}/season-archive/delivery-review', [SeasonArchiveDeliveryReviewController::class, 'review']);
        Route::post('/teams/{teamId}/season-archive/update-delivery-draft', [SeasonArchiveDeliveryReviewController::class, 'updateDraft']);
        Route::post('/teams/{teamId}/season-archive/send-delivery-draft', [SeasonArchiveDeliveryReviewController::class, 'sendDraft']);
        Route::get('/teams/{teamId}/season-archive/delivery-analytics', [SeasonArchiveDeliveryAnalyticsController::class, 'team']);
        Route::get('/teams/{teamId}/season-archive/deliveries', [SeasonArchiveDeliveryHistoryController::class, 'index']);
        Route::get('/season-archive-deliveries/{deliveryId}', [SeasonArchiveDeliveryHistoryController::class, 'show']);
        Route::post('/season-archive-deliveries/{deliveryId}/record-copy', [SeasonArchiveDeliveryHistoryController::class, 'recordCopy']);
        Route::get('/teams/{teamId}/weekly-report/export', GetCoachWeeklyReportExport::class);
        Route::get('/weekly-report-templates', [GetWeeklyReportTemplates::class, 'index']);
        Route::get('/teams/{teamId}/weekly-report/template-preview', [GetWeeklyReportTemplates::class, 'preview']);
        Route::get('/teams/{teamId}/weekly-report/delivery-preview', [WeeklyReportDeliveryPrepController::class, 'preview']);
        Route::post('/teams/{teamId}/weekly-report/create-delivery-draft', [WeeklyReportDeliveryPrepController::class, 'createDraft']);
        Route::post('/teams/{teamId}/weekly-report/delivery-review', [WeeklyReportDeliveryReviewController::class, 'review']);
        Route::post('/teams/{teamId}/weekly-report/update-delivery-draft', [WeeklyReportDeliveryReviewController::class, 'updateDraft']);
        Route::post('/teams/{teamId}/weekly-report/send-delivery-draft', [WeeklyReportDeliveryReviewController::class, 'sendDraft']);
        Route::get('/teams/{teamId}/weekly-report/delivery-analytics', [WeeklyReportDeliveryAnalyticsController::class, 'team']);
        Route::get('/teams/{teamId}/weekly-report/deliveries', [WeeklyReportDeliveryHistoryController::class, 'index']);
        Route::get('/weekly-report-deliveries/{deliveryId}', [WeeklyReportDeliveryHistoryController::class, 'show']);
        Route::post('/weekly-report-deliveries/{deliveryId}/record-copy', [WeeklyReportDeliveryHistoryController::class, 'recordCopy']);
        Route::get('/teams/{teamId}/weekly-report-notes', [WeeklyReportNotesController::class, 'index']);
        Route::post('/teams/{teamId}/weekly-report-notes', [WeeklyReportNotesController::class, 'store']);
        Route::put('/weekly-report-notes/{noteId}', [WeeklyReportNotesController::class, 'update']);
        Route::delete('/weekly-report-notes/{noteId}', [WeeklyReportNotesController::class, 'destroy']);
        Route::middleware('plan:planner_create')->get('/teams/{teamId}/next-week-plan-draft', GetNextWeekPlanDraft::class);
        Route::middleware('plan:planner_create')->post('/teams/{teamId}/next-week-plan-draft/save-day', SaveNextWeekPlanDraftDay::class);
        Route::middleware('plan:planner_create')->get('/teams/{teamId}/next-week-calendar-draft', GetNextWeekCalendarDraft::class);
        Route::middleware('plan:planner_create')->post('/teams/{teamId}/next-week-calendar-draft/save-days', SaveNextWeekCalendarDraftDays::class);
        Route::middleware('plan:planner_create')->get('/teams/{teamId}/weekly-draft-plans', [WeeklyPlanPublishController::class, 'list']);
        Route::middleware('plan:assign_workouts')->post('/teams/{teamId}/weekly-draft-plans/publish', [WeeklyPlanPublishController::class, 'publishWeeklyDrafts']);
        Route::middleware('plan:planner_create')->post('/daily-plans/{dailyPlanId}/publish', [WeeklyPlanPublishController::class, 'publishPlan']);
        Route::middleware('plan:assign_workouts')->post('/daily-plans/{dailyPlanId}/publish-and-assign', [WeeklyPlanPublishController::class, 'publishAndAssignPlan']);
        Route::get('/teams/{teamId}/daily-plan-update-suggestions', [IntelligenceController::class, 'teamDailyPlanUpdateSuggestions']);

        // Custom drills / lifts — saved per coach; `library` is the shared,
        // browse-other-coaches' community view (public drills).
        Route::middleware('plan:plan_builder')->get('/drills', GetCustomDrills::class);
        Route::middleware('plan:plan_builder')->get('/drills/library', GetDrillLibrary::class);
        Route::middleware('plan:plan_builder')->post('/drills', SaveCustomDrill::class);
        Route::middleware('plan:plan_builder')->delete('/drills/{id}', DeleteCustomDrill::class);

        // Recent workout completions — polled by the coach app for in-app alerts.
        Route::middleware('plan:view_workout_progress')->get('/workout-completions', GetWorkoutCompletions::class);

        // Player sub-groups — reusable assign presets for plans & practices.
        Route::middleware('plan:manage_player_groups')->get('/player-groups', GetPlayerGroups::class);
        Route::middleware('plan:manage_player_groups')->post('/player-groups', SavePlayerGroup::class);
        Route::middleware('plan:manage_player_groups')->delete('/player-groups/{id}', DeletePlayerGroup::class);

        // Saved field presets (Game Mode field builder) — user-scoped, synced replacement for localStorage
        Route::get('/field-presets', GetFieldPresets::class);
        Route::post('/field-presets', SaveFieldPreset::class);
        Route::delete('/field-presets/{id}', DeleteFieldPreset::class);

        // Synced (team-shared) replacements for localStorage-only data
        Route::middleware('plan:view_assessment_recommendations')->post('/assessments/{id}/insights', [AssessmentInsightController::class, 'update']);
        Route::get('/assessment-drafts/{player}', [AssessmentDraftController::class, 'show']);
        Route::post('/assessment-drafts', [AssessmentDraftController::class, 'store']);
        Route::delete('/assessment-drafts/{player}', [AssessmentDraftController::class, 'destroy']);
        Route::get('/teams/{id}/practice-insight', [TeamInsightController::class, 'show']);
        Route::post('/teams/{id}/practice-insight', [TeamInsightController::class, 'update']);
        Route::get('/teams/{teamId}/benchmark-overrides', [TeamBenchmarkOverrideController::class, 'index']);
        Route::put('/teams/{teamId}/benchmark-overrides', [TeamBenchmarkOverrideController::class, 'update']);
        Route::delete('/teams/{teamId}/benchmark-overrides', [TeamBenchmarkOverrideController::class, 'destroy']);
    });
});

Route::middleware(['auth:sanctum', 'ability:coach', 'plan:view_advanced_stats'])
    ->prefix('intelligence')
    ->group(function (): void {
        Route::get('/teams/{teamId}/benchmark-tasks', [IntelligenceController::class, 'listBenchmarkTasks']);
        Route::post('/teams/{teamId}/benchmark-tasks/generate', [IntelligenceController::class, 'generateBenchmarkTasks']);
        Route::post('/teams/{teamId}/coach-action-practice-plan/daily-plan', [IntelligenceController::class, 'saveCoachActionPracticePlanToDailyPlanner']);
        Route::get('/teams/{teamId}/daily-plan-update-suggestions', [IntelligenceController::class, 'teamDailyPlanUpdateSuggestions']);
        Route::post('/teams/{teamId}/benchmark-tasks/save-drafts', [IntelligenceController::class, 'saveBenchmarkDrafts']);
        Route::post('/teams/{teamId}/benchmark-tasks/assign', [IntelligenceController::class, 'assignBenchmarkTasks']);
        Route::post('/teams/{teamId}/refresh-benchmarks', [IntelligenceController::class, 'refreshTeamBenchmarks']);
        Route::post('/teams/{teamId}/rescore-benchmark-data-quality', [IntelligenceController::class, 'rescoreBenchmarkDataQuality']);
        Route::get('/teams/{teamId}/benchmark-task-reviews', [IntelligenceController::class, 'listBenchmarkTaskReviews']);
        Route::get('/teams/{teamId}/benchmark-task-promotions', [IntelligenceController::class, 'listBenchmarkTaskPromotions']);
        Route::post('/teams/{teamId}/promote-approved-benchmark-tasks', [IntelligenceController::class, 'promoteApprovedBenchmarkTasks']);
        Route::get('/benchmark-tasks/{taskId}/completion-workflow', [IntelligenceController::class, 'benchmarkTaskCompletionWorkflow']);
        Route::post('/benchmark-tasks/{taskId}/complete', [IntelligenceController::class, 'completeBenchmarkTask']);
        Route::post('/benchmark-tasks/{taskId}/complete-with-payload', [IntelligenceController::class, 'completeBenchmarkTaskWithPayload']);
        Route::post('/benchmark-tasks/{taskId}/approve', [IntelligenceController::class, 'approveBenchmarkTask']);
        Route::post('/benchmark-tasks/{taskId}/preview-promotion', [IntelligenceController::class, 'previewBenchmarkTaskPromotion']);
        Route::post('/benchmark-tasks/{taskId}/promote', [IntelligenceController::class, 'promoteBenchmarkTask']);
        Route::post('/benchmark-tasks/{taskId}/reject', [IntelligenceController::class, 'rejectBenchmarkTask']);
        Route::post('/benchmark-tasks/{taskId}/request-correction', [IntelligenceController::class, 'requestBenchmarkTaskCorrection']);
        Route::post('/benchmark-tasks/{taskId}/dismiss', [IntelligenceController::class, 'dismissBenchmarkTask']);
    });

Route::middleware(['auth:sanctum'])->prefix('training')->group(function (): void {
    Route::middleware(['session.entitlement', 'throttle:session-write'])->post('/', AddNewSession::class);
    Route::middleware('session.entitlement')->get('/{uuid}', GetSession::class);
    Route::middleware('session.entitlement')->put('/{uuid}', FinishPractice::class);
    Route::middleware('session.entitlement')->delete('/{uuid}', DeletePractice::class);
});

Route::middleware(['auth:sanctum', 'route.scope'])->prefix('result')->group(function (): void {
    Route::middleware('throttle:session-write')->post('/cage/estimate', EstimateCageBallFlight::class);
    Route::middleware('session.entitlement')->get('/batting/{uuid}', GetBattingResultPractice::class);
    Route::middleware('session.entitlement')->post('/batting', SaveBattingResultPractice::class);
    Route::middleware('session.entitlement')->put('/batting/{uuid}', EditBattingResultPractice::class);

    Route::middleware('session.entitlement')->get('/bullpen/{uuid}', GetBullpenResultPractice::class);
    Route::middleware('session.entitlement')->post('/bullpen', SaveBullpenResultPractice::class);
    Route::middleware('session.entitlement')->put('/bullpen/{uuid}', EditBullpenResultPractice::class);

    Route::middleware('session.entitlement')->get('/cage/{uuid}', GetCageResultPractice::class);
    Route::middleware(['session.entitlement', 'throttle:session-write'])->post('/cage', SaveCageResultPractice::class);
    Route::middleware('session.entitlement')->put('/cage/{uuid}', EditCageResultPractice::class);

    Route::middleware(['ability:coach', 'plan:liveab_sessions'])->get('/liveab/{uuid}', GetLiveABResultPractice::class);
    Route::middleware(['ability:coach', 'plan:liveab_sessions'])->post('/liveab', SaveLiveABResultPractice::class);
    Route::middleware(['ability:coach', 'plan:liveab_sessions'])->put('/liveab/{uuid}', EditLiveABResultPractice::class);


    Route::middleware('plan:long_toss_sessions')->get('/longtoss/{uuid}', GetLongTossResultPractice::class);
    Route::middleware('plan:long_toss_sessions')->post('/longtoss', SaveLongTossResultPractice::class);
    Route::middleware('plan:long_toss_sessions')->put('/longtoss/{uuid}', EditLongTossResultPractice::class);


    Route::middleware('plan:exit_velocity_sessions')->get('/exitvelocity/{uuid}', GetExitVelocityResultPractice::class);
    Route::middleware('plan:exit_velocity_sessions')->post('/exitvelocity', SaveExitVelocityResultPractice::class);
    Route::middleware('plan:exit_velocity_sessions')->put('/exitvelocity/{uuid}', EditExitVelocityResultPractice::class);


    Route::middleware('plan:weighted_ball_sessions')->get('/weightball/{uuid}', GetWeightBallResultPractice::class);
    Route::middleware('plan:weighted_ball_sessions')->post('/weightball', SaveWeightBallResultPractice::class);
    Route::middleware('plan:weighted_ball_sessions')->put('/weightball/{uuid}', EditWeightBallResultPractice::class);

    // ── Arm Care / Throwing Prep ───────────────────────────────────────────────
    // Locked on free; available on Player Basic+ (and Coach Pro).
    Route::middleware('plan:arm_care')->get('/armcare', GetArmCareResults::class);
    Route::middleware('plan:arm_care')->post('/armcare', SaveArmCareResultPractice::class);

    // ── Scripted BP ──────────────────────────────────────────────────────────
    Route::middleware('plan:scripted_bp')->post('/scripted-bp/plan', SaveScriptedBpPlan::class);
    Route::middleware('plan:scripted_bp')->post('/scripted-bp/swing', SaveScriptedBpSwing::class);
    Route::middleware('plan:scripted_bp')->get('/scripted-bp/{practice}', GetScriptedBpResults::class);
    Route::middleware(['ability:coach', 'plan:view_team_stats'])->get(
        '/statistics/{team}',
        FilterTrainings::class
    );
    Route::middleware(['ability:player'])->get('/statistics/player/{player}', GetPlayerFilteredStatistics::class);
});

Route::middleware(['auth:sanctum'])->prefix('sessions')->group(function (): void {
    Route::get('/type', GetPracticesSessionByType::class);
    Route::get('/all/type', GetTeamsPracticesSessionsByType::class);
    Route::get('/all/mode', GetTeamsPracticesSessionByMode::class);
    Route::get('/all/modes', GetAllPracticesByModes::class);
    Route::middleware(['ability:coach', 'plan:liveab_sessions'])->get('/all/liveab', GetListLiveABSessions::class);
    Route::get('/mode', GetPracticeSessionByMode::class);
});

Route::middleware(['auth:sanctum', 'route.scope'])->prefix('statistics')->group(function (): void {
    Route::middleware(['session.entitlement', 'plan:view_session_report'])->get('/{practice}/batting', GetBattingPracticeResults::class);
    Route::middleware(['session.entitlement', 'plan:view_session_report'])->get('/{practice}/bullpen', GetBullpenPracticeResults::class);
    Route::middleware(['session.entitlement', 'plan:view_session_report'])->get('/{practice}/longtoss', GetLongTossPracticeResult::class);
    Route::middleware(['session.entitlement', 'plan:view_session_report'])->get('/{practice}/weightball', GetWeightBallPracticeResult::class);
    Route::middleware(['session.entitlement', 'plan:view_session_report'])->get('/{practice}/exitvelocity', GetExitVelocityPracticeResult::class);
    Route::middleware(['session.entitlement', 'plan:view_session_report'])->get('/{practice}/cage', GetCagePracticeResults::class);
    // Players see their own Live AB ball-by-ball too (was coach-only). Still
    // tier-gated by plan:liveab_sessions (Player Pro / Coach Pro).
    Route::middleware(['session.entitlement', 'plan:view_session_report'])->get('/{practice}/liveab', GetLiveABPracticeResults::class);
});

// ── Admin routes ──────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'ability:coach', 'subscription.admin'])->prefix('admin')->group(function (): void {
    Route::get('/billing/plans', [\App\Http\Controllers\Api\Admin\PlanFeatureAdminController::class, 'plans']);
    Route::get('/billing/entitlements', [\App\Http\Controllers\Api\Admin\PlanFeatureAdminController::class, 'entitlements']);
    Route::put('/billing/plans/{plan}/entitlements', [\App\Http\Controllers\Api\Admin\PlanFeatureAdminController::class, 'update']);
    Route::get('/billing/entitlement-audits', [\App\Http\Controllers\Api\Admin\PlanFeatureAdminController::class, 'audits']);
    Route::get('/billing/failed-events', [\App\Http\Controllers\Api\Admin\BillingEventAdminController::class, 'failed']);
    Route::post('/billing/events/{event}/retry', [\App\Http\Controllers\Api\Admin\BillingEventAdminController::class, 'retry']);
    Route::patch('/users/{id}/plan', UpdateUserPlan::class);
    Route::get('/users/{user}/subscriptions', [\App\Http\Controllers\Api\Admin\SubscriptionAdminController::class, 'userIndex']);
    Route::post('/users/{user}/subscriptions', [\App\Http\Controllers\Api\Admin\SubscriptionAdminController::class, 'userStore']);
    Route::get('/teams/{team}/subscriptions', [\App\Http\Controllers\Api\Admin\SubscriptionAdminController::class, 'teamIndex']);
    Route::post('/teams/{team}/subscriptions', [\App\Http\Controllers\Api\Admin\SubscriptionAdminController::class, 'teamStore']);
    Route::patch('/subscriptions/{subscription}', [\App\Http\Controllers\Api\Admin\SubscriptionAdminController::class, 'update']);
    Route::post('/subscriptions/{subscription}/cancel', [\App\Http\Controllers\Api\Admin\SubscriptionAdminController::class, 'cancel']);
    Route::post('/subscriptions/{subscription}/revoke', [\App\Http\Controllers\Api\Admin\SubscriptionAdminController::class, 'revoke']);
    Route::get('/entitlement-grants', [\App\Http\Controllers\Api\Admin\EntitlementGrantAdminController::class, 'index']);
    Route::post('/entitlement-grants', [\App\Http\Controllers\Api\Admin\EntitlementGrantAdminController::class, 'store']);
    Route::post('/entitlement-grants/{grant}/revoke', [\App\Http\Controllers\Api\Admin\EntitlementGrantAdminController::class, 'revoke']);
    // Org-wide (not coach-scoped) team/coach/player directory, filterable by
    // state/level/team_id. Replaces the old admin pattern of paging through
    // the throttled coach/search/* endpoints to build a full user list.
    Route::get('/teams', [\App\Http\Controllers\Api\Admin\AdminDirectoryController::class, 'teams']);
    Route::get('/coaches', [\App\Http\Controllers\Api\Admin\AdminDirectoryController::class, 'coaches']);
    Route::get('/players', [\App\Http\Controllers\Api\Admin\AdminDirectoryController::class, 'players']);
    Route::get('/activity', \App\Http\Controllers\Api\Admin\UserActivityAdminController::class);
    Route::get('/users/{user}/login-history', \App\Http\Controllers\Api\Admin\UserLoginHistoryAdminController::class);
    // Dev/admin-only Cage Distance Validation Lab preview — gated by
    // CAGE_DISTANCE_VALIDATION_ENABLED (off by default, including prod).
    Route::post('/cage-distance/validate', [\App\Http\Controllers\Api\Admin\CageDistanceValidationController::class, 'check']);
});
