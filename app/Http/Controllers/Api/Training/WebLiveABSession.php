<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Training\Result\SaveLiveABResultPractice;
use App\Http\Requests\Api\Training\Result\LiveABRequest;
use App\Http\Resources\Api\PracticePlayersResource;
use App\Models\Practice;
use App\Models\LiveABPracticeResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Atomic web scorer updates. Existing mobile result endpoints remain compatible. */
class WebLiveABSession extends Controller
{
    private function practice(string $id, bool $lock = false): Practice
    {
        $query = Practice::where('type', 'L')->where('id', $id);
        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function show(Request $request, string $practice)
    {
        return response()->json(['data' => $this->data($this->practice($practice))]);
    }

    private function data(Practice $practice): array
    {
        return [
            'id' => $practice->id, 'note' => $practice->note, 'is_completed' => (bool)$practice->is_completed,
            'config' => $practice->liveab_config, 'state' => $practice->liveab_state ?? $this->initialState($practice),
            'teams' => $practice->liveABTeams()->with('team')->get(),
            'players' => [
                'batters' => PracticePlayersResource::collection($practice->lineup()->where('is_batting', true)->orderBy('sort')->get()),
                'pitchers' => PracticePlayersResource::collection($practice->lineup()->where('is_pitching', true)->orderBy('sort')->get()),
            ],
            'adjustments' => DB::table('liveab_web_events')->where('practice_id', $practice->id)->whereNull('result_id')->where('undone', false)->orderBy('id')->get(['action','before_state','after_state','created_at']),
            'rows' => $practice->live()->with('pitching.profile', 'batting.profile')->orderBy('sort')->get(),
            'can_undo' => DB::table('liveab_web_events')->where('practice_id', $practice->id)->where('undone', false)->exists(),
        ];
    }

    private function initialState(Practice $practice): array
    {
        $last = $practice->live()->with('batting', 'pitching')->orderByDesc('sort')->first();
        $batters = $practice->liveab_config['away']['hitters'] ?? $practice->lineup()->where('is_batting', true)->orderBy('sort')->pluck('user_id')->all();
        $pitchers = $practice->liveab_config['home']['pitchers'] ?? $practice->lineup()->where('is_pitching', true)->orderBy('sort')->pluck('user_id')->all();
        $hitterIndex = $last ? array_search($last->batting?->batter_id, $batters, true) : 0;
        $pitcherIndex = $last ? array_search($last->pitching?->pitcher_id, $pitchers, true) : 0;
        if ($last && $last->turn_is_over) {
            $hitterIndex = ((int)$hitterIndex + 1) % max(1, count($batters));
        }
        return ['inning' => 1,'half' => 'top','outs' => 0,
            'balls' => $last && ! $last->turn_is_over ? min(3, (int)$last->turn_ball) : 0,
            'strikes' => $last && ! $last->turn_is_over ? min(2, (int)$last->turn_strike) : 0,
            'turn' => $last ? (int)$last->turn + ($last->turn_is_over ? 1 : 0) : 1,
            'pitches' => $practice->live()->count(),'runners' => [null,null,null],'scores' => [],
            'hitterIndex' => ['away' => (int)$hitterIndex,'home' => 0],'pitcherIndex' => ['away' => 0,'home' => (int)$pitcherIndex],
            'finished' => false,'revision' => 0];
    }

    private function validateState(Request $request): array
    {
        $data = $request->validate([
            'action' => ['nullable','string','max:50'],
            'client_id' => ['required','uuid'], 'revision' => ['required','integer','min:0'],
            'state' => ['required','array:inning,half,outs,balls,strikes,turn,pitches,runners,scores,hitterIndex,pitcherIndex,finished'],
            'state.inning' => ['required','integer','between:1,999'], 'state.half' => ['required','in:top,bottom'],
            'state.outs' => ['required','integer','between:0,2'], 'state.balls' => ['required','integer','between:0,3'],
            'state.strikes' => ['required','integer','between:0,2'], 'state.turn' => ['required','integer','min:1'],
            'state.pitches' => ['required','integer','min:0'], 'state.finished' => ['required','boolean'],
            'state.runners' => ['required','array','size:3'], 'state.runners.*' => ['nullable','uuid','exists:users,id'],
            'state.scores' => ['present','array','max:1998'], 'state.scores.*' => ['array:inning,half,runs'],
            'state.scores.*.inning' => ['required','integer','between:1,999'], 'state.scores.*.half' => ['required','in:top,bottom'],
            'state.scores.*.runs' => ['required','integer','between:0,999'],
            'state.hitterIndex' => ['required','array:away,home'], 'state.pitcherIndex' => ['required','array:away,home'],
            'state.hitterIndex.away' => ['required','integer','between:0,19'], 'state.hitterIndex.home' => ['required','integer','between:0,19'],
            'state.pitcherIndex.away' => ['required','integer','between:0,19'], 'state.pitcherIndex.home' => ['required','integer','between:0,19'],
        ]);
        return $data;
    }

    private function checkRevision(Practice $practice, array $data): void
    {
        abort_if($practice->is_completed, 409, 'This session has ended.');
        abort_if($practice->liveab_state && (int)$practice->liveab_state['pitches'] !== $practice->live()->count(), 409, 'Pitches changed outside the web scorer. Continue on the original device.');

        abort_unless((int)($practice->liveab_state['revision'] ?? 0) === $data['revision'], 409, 'Session changed. Reload before scoring.');
    }

    public function pitch(LiveABRequest $request, string $practice)
    {
        $data = $this->validateState($request);
        abort_unless($request->input('practice_id') === $practice, 422);
        return DB::transaction(function () use ($request, $practice, $data) {
            $session = $this->practice($practice, true);
            if (DB::table('liveab_web_events')->where('practice_id', $practice)->where('client_id', $data['client_id'])->exists()) {
                return response()->json(['data' => $this->data($session)]);
            }
            $this->checkRevision($session, $data);
            if ($session->liveab_config) {
                $before = $session->liveab_state ?? $this->initialState($session);
                $battingSide = $before['half'] === 'top' ? 'away' : 'home';
                $pitchingSide = $before['half'] === 'top' ? 'home' : 'away';
                foreach (['batting'=>[$battingSide,'hitters','hitterIndex','batter_id'], 'pitching'=>[$pitchingSide,'pitchers','pitcherIndex','pitcher_id']] as $role=>$parts) {
                    [$side,$lineup,$index,$playerKey] = $parts;
                    $team = $session->liveab_config[$side];
                    abort_unless($request->input("$role.team_id") === $team['team_id']
                        && $request->input("$role.$playerKey") === ($team[$lineup][$before[$index][$side]] ?? null),422,'Save the selected hitter and pitcher for this half inning.');
                }
            }
            foreach (['batting' => ['batter_id','is_batting'],'pitching' => ['pitcher_id','is_pitching']] as $role => $keys) {
                abort_unless($session->lineup()->where('user_id', $request->input("{$role}.{$keys[0]}"))->where($keys[1], true)->exists(), 422, 'Player is not in this lineup.');
                abort_unless($session->liveABTeams()->where('team_id', $request->input("{$role}.team_id"))->exists(), 422, 'Team is not in this session.');
            }
            $session->liveab_state = $session->liveab_state ?? $this->initialState($session);
            $result = app(SaveLiveABResultPractice::class)($request);
            abort_if($result->getStatusCode() >= 400, 422, 'Pitch could not be saved.');
            $resultId = $result->getData(true)['data']['id'];
            $saved = LiveABPracticeResult::findOrFail($resultId);
            $play = $request->input('play_result');
            $saved->forceFill([
                'inning' => $session->liveab_state['inning'], 'half_inning' => $session->liveab_state['half'],
                'turn_is_over' => (bool)$play,
                'is_hit' => in_array($play, ['SINGLE','DOUBLE','TRIPLE','HR','BUNT_SINGLE'], true),
                'is_strike' => 'HBP' !== $request->type_of_hit && ('S' === $request->zone || 'TK' !== $request->type_of_hit),
                'is_ball' => 'B' === $request->zone && 'TK' === $request->type_of_hit,
            ])->save();
            $saved->pitching->update(['is_strike' => $saved->is_strike]);
            $this->record($session, $data, $resultId);
            return response()->json(['data' => $this->data($session->fresh())], 201);
        });
    }

    // Runner adjustments, substitutions, reset count and inning controls are
    // saved independently of pitches and can be undone without deleting a pitch.
    public function update(Request $request, string $practice)
    {
        $data = $this->validateState($request);
        return DB::transaction(function () use ($practice, $data) {
            $session = $this->practice($practice, true);
            if ( ! DB::table('liveab_web_events')->where('practice_id', $practice)->where('client_id', $data['client_id'])->exists()) {
                $this->checkRevision($session, $data);
                $this->record($session, $data, null);
            }
            return response()->json(['data' => $this->data($session->fresh())]);
        });
    }

    private function record(Practice $practice, array $data, ?string $resultId): void
    {
        abort_unless($data['state']['pitches'] === $practice->live()->count(), 422, 'Pitch count does not match saved results.');
        $runnerIds = array_values(array_filter($data['state']['runners']));
        abort_unless(count($runnerIds) === count(array_unique($runnerIds)), 422, 'A runner cannot occupy two bases.');
        abort_unless($practice->lineup()->whereIn('user_id', $runnerIds)->distinct()->count('user_id') === count($runnerIds), 422, 'Runner is not in the lineup.');
        if ($practice->liveab_config) {
            foreach (['away','home'] as $side) {
                abort_unless($data['state']['hitterIndex'][$side] < max(1,count($practice->liveab_config[$side]['hitters']))
                    && $data['state']['pitcherIndex'][$side] < max(1,count($practice->liveab_config[$side]['pitchers'])),422,'Select a player in this lineup.');
            }
        }
        $state = $data['state'] + ['revision' => $data['revision'] + 1];
        DB::table('liveab_web_events')->insert([
            'practice_id' => $practice->id,'client_id' => $data['client_id'],'result_id' => $resultId,'action' => $resultId ? 'pitch' : ($data['action'] ?? 'game_control'),
            'before_state' => json_encode($practice->liveab_state ?? $this->initialState($practice)),
            'after_state' => json_encode($state),'revision' => $state['revision'],'created_at' => now(),'updated_at' => now(),
        ]);
        $practice->update(['liveab_state' => $state]);
    }

    public function lineup(Request $request, string $practice)
    {
        $data = $request->validate([
            'revision' => ['required','integer','min:0'], 'side' => ['required','in:away,home'],
            'role' => ['required','in:hitters,pitchers'], 'player_id' => ['required','uuid','exists:users,id'],
        ]);
        return DB::transaction(function () use ($data, $practice) {
            $session = $this->practice($practice, true);
            $config = $session->liveab_config;
            abort_unless($config, 422, 'Use the original device to change a legacy lineup.');
            $side = $data['side'];
            $role = $data['role'];
            $player = $data['player_id'];
            if (in_array($player, $config[$side][$role], true)) {
                return response()->json(['data' => $this->data($session)]);
            }
            $this->checkRevision($session, $data);
            abort_if(count($config[$side][$role]) >= 20, 422, 'Maximum 20 players per lineup.');
            abort_unless(\App\Models\PlayerTeam::where('team_id', $config[$side]['team_id'])->where('user_id', $player)->exists(), 422, 'Choose a player from this roster.');
            $config[$side][$role][] = $player;
            $flag = 'hitters' === $role ? 'is_batting' : 'is_pitching';
            if ( ! $session->lineup()->where('user_id', $player)->where($flag, true)->exists()) {
                $session->lineup()->create(['user_id' => $player,'sort' => count($config[$side][$role]) - 1,'is_batting' => 'hitters' === $role,'is_pitching' => 'pitchers' === $role]);
            }
            $state = $session->liveab_state ?? $this->initialState($session);
            $state['revision'] = $data['revision'] + 1;
            $session->update(['liveab_config' => $config,'liveab_state' => $state]);
            return response()->json(['data' => $this->data($session->fresh())]);
        });
    }

    public function undo(Request $request, string $practice)
    {
        $request->validate(['revision' => ['required','integer','min:1']]);
        return DB::transaction(function () use ($request, $practice) {
            $session = $this->practice($practice, true);
            $this->checkRevision($session, ['revision' => (int)$request->revision]);
            $event = DB::table('liveab_web_events')->where('practice_id', $practice)->where('undone', false)->orderByDesc('id')->first();
            abort_unless($event, 409, 'No web action to undo.');
            if ($event->result_id) {
                $row = LiveABPracticeResult::findOrFail($event->result_id);
                // Never undo an earlier pitch underneath a newer mobile save.
                abort_if($session->live()->where('sort', '>', $row->sort)->exists(), 409, 'A newer pitch exists. Reload the session.');
                $row->batting?->delete();
                $row->pitching?->delete();
                $row->delete();
            }
            $state = json_decode($event->before_state ?? 'null', true);

            abort_unless(is_array($state), 422, 'Reload the initial session state.');
            $state['revision'] = (int)$request->revision + 1;
            $session->update(['liveab_state' => $state]);
            DB::table('liveab_web_events')->where('id', $event->id)->update(['undone' => true,'updated_at' => now()]);
            return response()->json(['data' => $this->data($session->fresh())]);
        });
    }
}
