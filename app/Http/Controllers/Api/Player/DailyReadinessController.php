<?php
namespace App\Http\Controllers\Api\Player;
use App\Http\Controllers\Controller;
use App\Services\Planner\DailyReadinessSummary;
use App\Models\{PlayerDailyReadiness, PlayerTeam, CoachTeam, User};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DailyReadinessController extends Controller {
    private function context(Request $request): array {
        $data = $request->validate(['date'=>['required','date_format:Y-m-d'], 'timezone'=>['required','timezone']]);
        return $data;
    }
    private function authorizePlayerRead(Request $request, string $player): void {
        if ((string)$request->user()->id === $player) return;
        abort_unless((string)$request->user()->type === 'coach',403);
        $teams=CoachTeam::where('coach_id',$request->user()->id)->pluck('team_id');
        abort_unless(PlayerTeam::where('user_id',$player)->where('actual',true)->whereIn('team_id',$teams)->exists(),403);
    }
    public function mine(Request $request) { return $this->history($request,(string)$request->user()->id); }
    public function history(Request $request, string $player) {
        $this->authorizePlayerRead($request,$player);
        $context=$this->context($request);
        $rows=PlayerDailyReadiness::where('user_id',$player)->where('date','<=',$context['date'])->orderByDesc('date')->limit(30)->get();
        return response()->json(['data'=>['today'=>($row=$rows->firstWhere('date',$context['date']))?DailyReadinessSummary::present($row):null,
            'history'=>$rows->map(fn($r)=>DailyReadinessSummary::present($r))->values()]]);
    }
    public function save(Request $request) {
        abort_unless((string)$request->user()->type === 'player',403);
        $context=$this->context($request);
        abort_unless($context['date']===Carbon::now($context['timezone'])->toDateString(),422,'Submit readiness for today in your timezone.');
        $rules=['answers'=>['required','array:'.implode(',',array_merge(DailyReadinessSummary::RATINGS,['sleep_hours','pain_flag','notes']))],
            'answers.sleep_hours'=>['required','numeric','between:0,24'], 'answers.pain_flag'=>['required','boolean'],
            'answers.notes'=>['nullable','string','max:2000']];
        foreach(DailyReadinessSummary::RATINGS as $key) $rules['answers.'.$key]=['required','integer','between:1,10'];
        $answers=$request->validate($rules)['answers'];
        // Atomic insert-or-no-op: the first submission wins across devices.
        // Query builder avoids Eloquent touching updated_at on duplicate saves.
        $id=(string)Str::uuid();
        \Illuminate\Support\Facades\DB::table('player_daily_readiness')->upsert([['id'=>$id,'user_id'=>$request->user()->id,
            'date'=>$context['date'],'timezone'=>$context['timezone'],'scale_version'=>2,
            'answers'=>json_encode($answers),'created_at'=>now(),'updated_at'=>now()]],
            ['user_id','date'],['user_id']);
        $row=PlayerDailyReadiness::where('user_id',$request->user()->id)->where('date',$context['date'])->firstOrFail();
        $conflict=$row->id!==$id && $row->answers!=$answers;
        return response()->json(['data'=>DailyReadinessSummary::present($row),
            'message'=>$row->id===$id?'Readiness submitted.':'Readiness has already been submitted today. Your original answers are unchanged.',
            'already_submitted'=>$row->id!==$id],$conflict?409:200);
    }
    public function team(Request $request, string $team) {
        abort_unless((string)$request->user()->type === 'coach' && CoachTeam::where('coach_id',$request->user()->id)->where('team_id',$team)->exists(),403);
        $context=$this->context($request);
        $ids=PlayerTeam::where('team_id',$team)->where('actual',true)->pluck('user_id')->unique();
        $entries=PlayerDailyReadiness::whereIn('user_id',$ids)->where('date',$context['date'])->get()->keyBy('user_id');
        $players=User::with('profile')->whereIn('id',$ids)->get()->map(function($player)use($entries){
            $name=trim(($player->profile?->first_name??'').' '.($player->profile?->last_name??''));
            return ['user_id'=>$player->id,'name'=>$name?:'Player','readiness'=>isset($entries[$player->id])?DailyReadinessSummary::present($entries[$player->id]):null];
        });
        return response()->json(['data'=>['date'=>$context['date'],'players'=>$players]]);
    }
}
