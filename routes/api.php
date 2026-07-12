<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\CompleteCoachController;
use App\Http\Controllers\Api\Auth\CompletePlayerController;
use App\Http\Controllers\Api\Auth\GetUserCompleteController;
use App\Http\Controllers\Api\Auth\LoginController;
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
use App\Http\Controllers\Api\Coach\GetPlayersList;
use App\Http\Controllers\Api\Coach\GetPracticePlans;
use App\Http\Controllers\Api\Coach\GetStatsBundle;
use App\Http\Controllers\Api\Coach\SavePracticePlan;
use App\Http\Controllers\Api\Coach\DeletePracticePlan;
// Daily Planner — its own domain, shared between app and web
use App\Http\Controllers\Api\Planner\GetDailyPlans;
use App\Http\Controllers\Api\Planner\SaveDailyPlan;
use App\Http\Controllers\Api\Planner\DeleteDailyPlan;
use App\Http\Controllers\Api\Planner\GetMyWorkouts;
use App\Http\Controllers\Api\Planner\GetMyWorkout;
use App\Http\Controllers\Api\Planner\SaveWorkoutProgress;
use App\Http\Controllers\Api\Planner\SaveCustomDrill;
use App\Http\Controllers\Api\Planner\GetCustomDrills;
use App\Http\Controllers\Api\Planner\DeleteCustomDrill;
use App\Http\Controllers\Api\Planner\GetDrillLibrary;
use App\Http\Controllers\Api\Planner\GetWorkoutCompletions;
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
use App\Http\Controllers\Api\Coach\GetTeamById;
use App\Http\Controllers\Api\Coach\GetTeamCode;
use App\Http\Controllers\Api\Coach\GetPlayerDevelopmentBoard;
use App\Http\Controllers\Api\Coach\GetPlayerDevelopmentDashboard;
use App\Http\Controllers\Api\Coach\GetTeamPlayerCards;
use App\Http\Controllers\Api\Coach\GetTeamsPlayers;
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
use App\Http\Controllers\Api\Player\GetFitness;
use App\Http\Controllers\Api\Player\GetLiveABPractices;
use App\Http\Controllers\Api\Player\GetTrainingPractices;
use App\Http\Controllers\Api\Player\JoinTeamByCode;
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

Route::middleware(['auth:sanctum', 'ability:coach'])->get('/opcache-clear', function() {
    opcache_reset();
    return response()->json(['cleared' => true, 'ts' => time()]);
});

Route::post('login', LoginController::class);

Route::post('/forgot-password', SendEmailRecoverController::class)->middleware(['guest']);

Route::post('/recover-password', RecoverPasswordController::class)->middleware(['guest']);
Route::get('/complete/{id}', GetUserCompleteController::class)->middleware(['guest']);
Route::post('/complete/{user}/coach', CompleteCoachController::class)->middleware(['guest']);
Route::post('/complete/{user}/player', CompletePlayerController::class)->middleware(['guest']);

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/edit/players/{id}', EditPlayers::class);
    Route::get('player/me', \App\Http\Controllers\Api\Player\GetMe::class);
    Route::post('player/fitness', SaveFitness::class);
    Route::get('player/fitness/{id}', GetFitness::class);
    Route::get('players/{player}/athletic-performance', GetAthleticPerformance::class);
    Route::post('assessments/{assessment}/calculate-api', CalculateAthleticPerformance::class);
    Route::post('assessments', SaveAssessment::class);
    Route::get('assessments/player/{player}', GetPlayerAssessments::class);
    Route::get('assessments/team/{team}', GetTeamAssessments::class);
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
    Route::post('join', JoinTeamByCode::class);          // phone + team_code → claim profile / join team
    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::post('set-credentials', SetPlayerCredentials::class); // first-time email+password setup after claim
    });
    Route::middleware(['auth:sanctum', 'ability:player'])->group(function (): void {
        Route::get('sessions/batting', GetBattingPractices::class);
        Route::get('sessions/bullpen', GetBullpenPractices::class);
        Route::get('sessions/cage', GetCagePractices::class);
        Route::get('sessions/training', GetTrainingPractices::class);
        Route::get('sessions/liveab', GetLiveABPractices::class);
        Route::get('sessions/created', GetCreatedPractices::class);
        Route::get('statistics/{player}', ScoresStatisticPlayers::class);
        Route::get('benchmark-tasks', [IntelligenceController::class, 'listPlayerBenchmarkTasks']);
        Route::get('benchmark-tasks/{taskId}', [IntelligenceController::class, 'showPlayerBenchmarkTask']);
        Route::get('benchmark-tasks/{taskId}/completion-workflow', [IntelligenceController::class, 'playerBenchmarkTaskCompletionWorkflow']);
        Route::get('benchmark-tasks/{taskId}/review-status', [IntelligenceController::class, 'playerBenchmarkTaskReviewStatus']);
        Route::post('benchmark-tasks/{taskId}/start', [IntelligenceController::class, 'startPlayerBenchmarkTask']);
        Route::post('benchmark-tasks/{taskId}/complete', [IntelligenceController::class, 'completePlayerBenchmarkTask']);
        Route::post('benchmark-tasks/{taskId}/complete-with-payload', [IntelligenceController::class, 'completePlayerBenchmarkTaskWithPayload']);
        Route::post('benchmark-tasks/{taskId}/dismiss', [IntelligenceController::class, 'dismissPlayerBenchmarkTask']);
        Route::middleware('plan:view_advanced_stats')->get('development/players/{player}', GetPlayerDevelopmentDashboard::class);
        Route::middleware('plan:view_advanced_stats')->get('development/teams/{team}/players/{player}', GetPlayerDevelopmentDashboard::class);

        // Daily Planner (player side) — "My Workouts" + progress
        Route::get('daily-plans', GetMyWorkouts::class);
        Route::get('daily-plans/{id}', GetMyWorkout::class);
        Route::post('daily-plans/{id}/progress', SaveWorkoutProgress::class);
    });
});

Route::prefix('coach')->group(function (): void {
    Route::post('register', RegisterCoachController::class);
    Route::middleware(['auth:sanctum', 'ability:coach'])->group(function (): void {
        Route::post('/players/{id}/set-password', SetPlayerPassword::class);
        Route::post('/add/teams', AddTeams::class);
        Route::post('/edit', EditCoach::class);
        Route::post('/edit/teams/{team}', EditTeams::class);
        Route::post('/add/players', AddPlayers::class);
        Route::post('/remove/players', RemovePlayers::class);
        Route::delete('/remove/coach/{id}', RemoveCoachFromTeam::class);
        Route::get('/list/results/{practice}', ListSmsResults::class);
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
        Route::middleware('plan:view_advanced_stats')->get('/development/teams/{team}/players/{player}', GetPlayerDevelopmentDashboard::class);
        Route::get('/sessions/lasts/{team}', GetLastSessions::class);
        // One call returning recent sessions' detail bundled by type (kills the
        // Stats screen's per-session N+1 fetch).
        Route::get('/stats/bundle/{team}', GetStatsBundle::class);
        Route::middleware('plan:performance_overview')->get('/performance-overview/{team}', GetPerformanceOverview::class);
        Route::post('/trainingab', AddNewLiveABSession::class);
        Route::middleware('plan:liveab_sessions')->get('/statistics/{practice}/liveab', GetLiveABPracticeResults::class);
        Route::get('/search/players', SearchPlayers::class);
        Route::get('/search/coaches', SearchCoaches::class);
        Route::get('/statistics/{player}', ScoresStatisticPlayers::class);
        Route::get('/pitcher/velocity/{player}', GetPlayerPitchVelocityZones::class);
        Route::get('/pitcher/smtake/{player}', GetPlayerSmTakeZones::class);
        Route::post('/lineup/{training}', AddPlayerToTraining::class);
        Route::middleware('plan:sms_results')->post('/send/results/{practice}', SendSmsResults::class);

        // Practice Planner — team-shared, synced between app and web
        Route::get('/practice-plans', GetPracticePlans::class);
        Route::post('/practice-plans', SavePracticePlan::class);
        Route::delete('/practice-plans/{id}', DeletePracticePlan::class);

        // Daily Planner (coach authoring) — synced between app and web
        Route::get('/daily-plans', GetDailyPlans::class);
        Route::post('/daily-plans', SaveDailyPlan::class);
        Route::delete('/daily-plans/{id}', DeleteDailyPlan::class);
        Route::get('/daily-plans/{dailyPlanId}/update-suggestions', [IntelligenceController::class, 'dailyPlanUpdateSuggestions']);
        Route::post('/daily-plans/{dailyPlanId}/apply-update-suggestions', [IntelligenceController::class, 'applyDailyPlanUpdateSuggestions']);
        Route::get('/daily-plans/{dailyPlanId}/revisions/compare', [IntelligenceController::class, 'compareDailyPlanRevisions']);
        Route::get('/daily-plans/{dailyPlanId}/revisions/{revisionId}', [IntelligenceController::class, 'showDailyPlanRevision']);
        Route::get('/daily-plans/{dailyPlanId}/revisions', [IntelligenceController::class, 'listDailyPlanRevisions']);
        Route::get('/teams/{teamId}/daily-plan-update-suggestions', [IntelligenceController::class, 'teamDailyPlanUpdateSuggestions']);

        // Custom drills / lifts — saved per coach; `library` is the shared,
        // browse-other-coaches' community view (public drills).
        Route::get('/drills', GetCustomDrills::class);
        Route::get('/drills/library', GetDrillLibrary::class);
        Route::post('/drills', SaveCustomDrill::class);
        Route::delete('/drills/{id}', DeleteCustomDrill::class);

        // Recent workout completions — polled by the coach app for in-app alerts.
        Route::get('/workout-completions', GetWorkoutCompletions::class);

        // Player sub-groups — reusable assign presets for plans & practices.
        Route::get('/player-groups', GetPlayerGroups::class);
        Route::post('/player-groups', SavePlayerGroup::class);
        Route::delete('/player-groups/{id}', DeletePlayerGroup::class);

        // Saved field presets (Game Mode field builder) — user-scoped, synced replacement for localStorage
        Route::get('/field-presets', GetFieldPresets::class);
        Route::post('/field-presets', SaveFieldPreset::class);
        Route::delete('/field-presets/{id}', DeleteFieldPreset::class);

        // Synced (team-shared) replacements for localStorage-only data
        Route::post('/assessments/{id}/insights', [AssessmentInsightController::class, 'update']);
        Route::get('/assessment-drafts/{player}', [AssessmentDraftController::class, 'show']);
        Route::post('/assessment-drafts', [AssessmentDraftController::class, 'store']);
        Route::delete('/assessment-drafts/{player}', [AssessmentDraftController::class, 'destroy']);
        Route::get('/teams/{id}/practice-insight', [TeamInsightController::class, 'show']);
        Route::post('/teams/{id}/practice-insight', [TeamInsightController::class, 'update']);
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
    Route::post('/', AddNewSession::class);
    Route::get('/{uuid}', GetSession::class);
    Route::put('/{uuid}', FinishPractice::class);
    Route::delete('/{uuid}', DeletePractice::class);
});

Route::middleware(['auth:sanctum'])->prefix('result')->group(function (): void {
    Route::get('/batting/{uuid}', GetBattingResultPractice::class);
    Route::post('/batting', SaveBattingResultPractice::class);
    Route::put('/batting/{uuid}', EditBattingResultPractice::class);

    Route::get('/bullpen/{uuid}', GetBullpenResultPractice::class);
    Route::post('/bullpen', SaveBullpenResultPractice::class);
    Route::put('/bullpen/{uuid}', EditBullpenResultPractice::class);

    Route::get('/cage/{uuid}', GetCageResultPractice::class);
    Route::post('/cage', SaveCageResultPractice::class);
    Route::put('/cage/{uuid}', EditCageResultPractice::class);

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
    Route::post('/scripted-bp/plan', SaveScriptedBpPlan::class);
    Route::post('/scripted-bp/swing', SaveScriptedBpSwing::class);
    Route::get('/scripted-bp/{practice}', GetScriptedBpResults::class);
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

Route::middleware(['auth:sanctum'])->prefix('statistics')->group(function (): void {
    Route::get('/{practice}/batting', GetBattingPracticeResults::class);
    Route::get('/{practice}/bullpen', GetBullpenPracticeResults::class);
    Route::middleware('plan:long_toss_sessions')->get('/{practice}/longtoss', GetLongTossPracticeResult::class);
    Route::middleware('plan:weighted_ball_sessions')->get('/{practice}/weightball', GetWeightBallPracticeResult::class);
    Route::middleware('plan:exit_velocity_sessions')->get('/{practice}/exitvelocity', GetExitVelocityPracticeResult::class);
    Route::get('/{practice}/cage', GetCagePracticeResults::class);
    // Players see their own Live AB ball-by-ball too (was coach-only). Still
    // tier-gated by plan:liveab_sessions (Player Pro / Coach Pro).
    Route::middleware('plan:liveab_sessions')->get('/{practice}/liveab', GetLiveABPracticeResults::class);
});

// ── Admin routes ──────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'ability:coach'])->prefix('admin')->group(function (): void {
    Route::patch('/users/{id}/plan', UpdateUserPlan::class);
});
