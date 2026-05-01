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
use App\Http\Controllers\Api\Coach\EditTeams;
use App\Http\Controllers\Api\Coach\GetCoachesList;
use App\Http\Controllers\Api\Coach\GetLastSessions;
use App\Http\Controllers\Api\Coach\GetPlayersList;
use App\Http\Controllers\Api\Coach\GetTeamById;
use App\Http\Controllers\Api\Coach\GetTeamsPlayers;
use App\Http\Controllers\Api\Coach\RemoveCoachFromTeam;
use App\Http\Controllers\Api\Coach\RemovePlayers;
use App\Http\Controllers\Api\Coach\RemoveTeam;
use App\Http\Controllers\Api\DashBoard\GetDataCharts;
use App\Http\Controllers\Api\DashBoard\GetDataGraphics;
use App\Http\Controllers\Api\DashBoard\GetTopTenResults;
use App\Http\Controllers\Api\Player\GetBattingPractices;
use App\Http\Controllers\Api\Player\GetBullpenPractices;
use App\Http\Controllers\Api\Player\GetCagePractices;
use App\Http\Controllers\Api\Player\GetFitness;
use App\Http\Controllers\Api\Player\GetTrainingPractices;
use App\Http\Controllers\Api\Player\SaveFitness;
use App\Http\Controllers\Api\ScoresStatisticPlayers;
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
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class);

Route::post('/forgot-password', SendEmailRecoverController::class)->middleware(['guest']);

Route::post('/recover-password', RecoverPasswordController::class)->middleware(['guest']);
Route::get('/complete/{id}', GetUserCompleteController::class)->middleware(['guest']);
Route::post('/complete/{user}/coach', CompleteCoachController::class)->middleware(['guest']);
Route::post('/complete/{user}/player', CompletePlayerController::class)->middleware(['guest']);

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/edit/players/{id}', EditPlayers::class);
    Route::post('player/fitness', SaveFitness::class);
    Route::get('player/fitness/{id}', GetFitness::class);
    Route::get('dashboard/{team}', GetDataGraphics::class);
    Route::post('charts', GetDataCharts::class);
    Route::post('table/{team}', GetTopTenResults::class);
    Route::post('password', PasswordChange::class);

});

Route::prefix('player')->group(function (): void {
    Route::post('register', RegisterPlayerController::class);
    Route::middleware(['auth:sanctum', 'ability:player'])->group(function (): void {
        Route::get('sessions/batting', GetBattingPractices::class);
        Route::get('sessions/bullpen', GetBullpenPractices::class);
        Route::get('sessions/cage', GetCagePractices::class);
        Route::get('sessions/training', GetTrainingPractices::class);
    });
});

Route::prefix('coach')->group(function (): void {
    Route::post('register', RegisterCoachController::class);
    Route::middleware(['auth:sanctum', 'ability:coach'])->group(function (): void {
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
        Route::get('/teams', GetTeamsPlayers::class);
        Route::get('/teams/{id}', GetTeamById::class);
        Route::get('/sessions/lasts/{team}', GetLastSessions::class);
        Route::post('/trainingab', AddNewLiveABSession::class);
        Route::get('/statistics/{practice}/liveab', GetLiveABPracticeResults::class);
        Route::get('/search/players', SearchPlayers::class);
        Route::get('/search/coaches', SearchCoaches::class);
        Route::get('/statistics/{player}', ScoresStatisticPlayers::class);
        Route::post('/lineup/{training}', AddPlayerToTraining::class);
        Route::post('/send/results/{practice}', SendSmsResults::class);
    });
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

    Route::middleware('ability:coach')->get('/liveab/{uuid}', GetLiveABResultPractice::class);
    Route::middleware('ability:coach')->post('/liveab', SaveLiveABResultPractice::class);
    Route::middleware('ability:coach')->put('/liveab/{uuid}', EditLiveABResultPractice::class);


    Route::get('/longtoss/{uuid}', GetLongTossResultPractice::class);
    Route::post('/longtoss', SaveLongTossResultPractice::class);
    Route::put('/longtoss/{uuid}', EditLongTossResultPractice::class);


    Route::get('/exitvelocity/{uuid}', GetExitVelocityResultPractice::class);
    Route::post('/exitvelocity', SaveExitVelocityResultPractice::class);
    Route::put('/exitvelocity/{uuid}', EditExitVelocityResultPractice::class);


    Route::get('/weightball/{uuid}', GetWeightBallResultPractice::class);
    Route::post('/weightball', SaveWeightBallResultPractice::class);
    Route::put('/weightball/{uuid}', EditWeightBallResultPractice::class);
    Route::middleware('ability:coach')->get(
        '/statistics/{team}',
        FilterTrainings::class
    );
});

Route::middleware(['auth:sanctum'])->prefix('sessions')->group(function (): void {
    Route::get('/type', GetPracticesSessionByType::class);
    Route::get('/all/type', GetTeamsPracticesSessionsByType::class);
    Route::get('/all/mode', GetTeamsPracticesSessionByMode::class);
    Route::get('/all/modes', GetAllPracticesByModes::class);
    Route::middleware('ability:coach')->get('/all/liveab', GetListLiveABSessions::class);
    Route::get('/mode', GetPracticeSessionByMode::class);
});

Route::middleware(['auth:sanctum'])->prefix('statistics')->group(function (): void {
    Route::get('/{practice}/batting', GetBattingPracticeResults::class);
    Route::get('/{practice}/bullpen', GetBullpenPracticeResults::class);
    Route::get('/{practice}/longtoss', GetLongTossPracticeResult::class);
    Route::get('/{practice}/weightball', GetWeightBallPracticeResult::class);
    Route::get('/{practice}/exitvelocity', GetExitVelocityPracticeResult::class);
    Route::get('/{practice}/cage', GetCagePracticeResults::class);
    Route::middleware('ability:coach')->get('/{practice}/liveab', GetLiveABPracticeResults::class);
});
