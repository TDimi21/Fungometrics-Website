<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\AddNewLiveABSessionRequest;
use App\Http\Resources\Api\PracticeLiveABSessionResource;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Player;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\TeamsLiveAB;
use App\Models\User;
use App\Services\CreateServiceData;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddNewLiveABSession extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(AddNewLiveABSessionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $dataRequest = $request->validated();
            $config = $dataRequest['liveab_config'] ?? null;
            if ($config) {
                abort_unless('L' === $dataRequest['type'], 422, 'Live AB session required.');
                foreach (['away', 'home'] as $side) {
                    $teamId = $config[$side]['team_id'];
                    abort_unless(\App\Models\CoachTeam::where('coach_id', $request->user()->id)->where('team_id', $teamId)->exists(), 403);
                    foreach (['hitters','pitchers'] as $role) {
                        $ids = $config[$side][$role] ?? [];
                        $config[$side][$role] = $ids;
                        abort_unless(count($ids) === count(array_unique($ids)), 422, 'Do not repeat players in a lineup.');
                        $required = 'game' === $config['mode'] || ('away' === $side && 'hitters' === $role) || ('home' === $side && 'pitchers' === $role);
                        abort_if($required && ! $ids, 422, 'Select hitters and pitchers for each side.');
                        abort_unless(\App\Models\PlayerTeam::where('team_id', $teamId)->whereIn('user_id', $ids)->distinct()->count('user_id') === count(array_unique($ids)), 422, 'Select roster players.');
                    }
                }
                $dataRequest['teams'] = ['a' => $config['away']['team_id'], 'b' => $config['home']['team_id']];
                $dataRequest['liveab_config'] = $config;
                $battingIds = array_values(array_unique(array_merge($config['away']['hitters'], $config['home']['hitters'])));
                $pitchingIds = array_values(array_unique(array_merge($config['home']['pitchers'], $config['away']['pitchers'])));
                $dataRequest['players'] = [
                    'a' => array_map(fn ($id, $sort) => ['id' => $id, 'sort' => $sort], $battingIds, array_keys($battingIds)),
                    'b' => array_map(fn ($id, $sort) => ['id' => $id, 'sort' => $sort], $pitchingIds, array_keys($pitchingIds)),
                ];
            }
            $dataRequest['started'] = Carbon::now();
            $dataRequest['modes'] = $dataRequest['modes'] ?? PracticeModes::HIT_OR_PITCH->value;
            $dataRequest['type'] = $dataRequest['type'] ?? PracticeTypes::LIVE_AB->value;

            $teamsInput = $dataRequest['teams'] ?? [];
            $playersInput = $dataRequest['players'] ?? [];

            $teamA = (string) ($teamsInput['a'] ?? $teamsInput[0] ?? '');
            $teamB = (string) ($teamsInput['b'] ?? $teamsInput[1] ?? '');

            if ('' === $teamA || '' === $teamB) {
                DB::rollBack();
                return response()->json([
                    'code' => '022-E',
                    'message' => 'teams a and b are required',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            $batters = $playersInput['a'] ?? $playersInput[0] ?? [];
            $pitchers = $playersInput['b'] ?? $playersInput[1] ?? [];

            if ( ! is_array($batters) || ! is_array($pitchers) || 0 === count($batters) || 0 === count($pitchers)) {
                DB::rollBack();
                return response()->json([
                    'code' => '022-E',
                    'message' => 'players a and b must be non-empty arrays',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Keep a canonical team on the practice row for compatibility with
            // existing joins/listing queries that rely on practices.team_id.
            $dataRequest['team_id'] = $teamB;

            $practice = (new CreateServiceData(new Practice()))->handle($dataRequest);
            $players = null;
            $hasTeamsLiveAbBattingColumn = Schema::hasColumn('teams_live_a_b_s', 'batting');

            $teams = null;
            $seenTeamIds = [];
            foreach (['a' => $teamA, 'b' => $teamB] as $side => $team) {
                if (in_array($team, $seenTeamIds, true)) {
                    continue;
                } // skip duplicates
                $seenTeamIds[] = $team;
                $teamPayload = [
                    'team_id' => $team,
                    'practice_id' => $practice->id
                ];
                if ($hasTeamsLiveAbBattingColumn) {
                    $teamPayload['batting'] = 'a' === $side;
                }
                $teams[] = (new CreateServiceData(new TeamsLiveAB()))->handle($teamPayload);
            }

            foreach (['a' => $batters, 'b' => $pitchers] as $key => $team) {
                foreach ($team as $player) {
                    $incomingId = (string) ($player['id'] ?? '');
                    if ('' === $incomingId) {
                        throw new Exception('lineup player id is required');
                    }

                    // Mobile payload can carry either users.id or players.id.
                    // practice_line_ups.user_id requires users.id.
                    $resolvedUserId = User::query()->where('id', $incomingId)->value('id');
                    if ( ! $resolvedUserId) {
                        $resolvedUserId = Player::query()->where('id', $incomingId)->value('user_id');
                    }
                    if ( ! $resolvedUserId) {
                        throw new Exception('invalid lineup player id: '.$incomingId);
                    }

                    $players[$key][] = (new CreateServiceData(new PracticeLineUp()))
                        ->handle([
                            'practice_id' => $practice->id,
                            'user_id' => $resolvedUserId,
                            'sort' => (int) ($player['sort'] ?? 0),
                            'is_batting' => 'a' === $key,
                            'is_pitching' => 'b' === $key,
                        ]);
                }
            }

            $response = [
                'code' => '022',
                'message' => 'practice live ab create',
                'status' => 'success',
                'data' => new PracticeLiveABSessionResource([
                    'practice' => $practice,
                    'teams' => $teams,
                    'players' => $players
                ]),
            ];
            DB::commit();

            // Bust the GetLastSessions server cache for both teams so the
            // list screen shows this new session immediately (cache TTL = 300 s)
            foreach ([$teamA, $teamB] as $teamId) {
                Cache::forget("last_sessions_{$teamId}");
            }

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $exception;
            }
            $response = [
                'code' => '022-E',
                'message' => 'error to create a session training',
                'status' => 'error',
                'data' => [],
            ];
            Log::error('AddNewLiveABSession error', [
                'exception' => get_class($exception),
                'request_id' => $request->header('X-Request-ID'),
            ]);
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
