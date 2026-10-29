<?php
namespace App\Services\LongToss;

use App\Models\{LongTossGuide, Practice, User};
use Carbon\Carbon;

class SessionSetup
{
    public static function rules(): array {
        $rules=['long_toss_setup'=>['sometimes','array','min:1','max:100']];
        $fields=[
            'player_id'=>'required|uuid|distinct',
            'readiness'=>'required|integer|between:1,10','pain'=>'required|boolean',
            'timezone'=>'required|timezone','maximum_ft'=>'required|numeric|between:30,500',
            'day_type'=>'required|in:recovery,moderate,high','season_phase'=>'required|in:offseason,preseason,inseason,returning',
            'throwing_history'=>'required|string|max:500',
            'out_sets'=>'required|integer|between:2,20','in_sets'=>'required|integer|between:1,20',
            'out_throws'=>'required|integer|between:1,30',
            'in_throws'=>'required|integer|between:1,30',
            'rest_seconds'=>'required|integer|between:10,300','max_throws'=>'nullable|integer|between:3,120',

        ];
        foreach($fields as $key=>$rule)$rules['long_toss_setup.*.'.$key]=$rule;
        return $rules;
    }
    public function create(Practice $practice, array $setups): void {
        $engine=app(GuidanceEngine::class);
        $actor=auth()->user();
        if($actor->type==='coach') abort_unless($practice->team_id && \App\Models\CoachTeam::where('team_id',$practice->team_id)->where('coach_id',$actor->id)->whereHas('team')->exists(),403);

        foreach($setups as $setup) {
            if($actor->type==='coach') abort_unless(\App\Models\PlayerTeam::where('team_id',$practice->team_id)->where('user_id',$setup['player_id'])->where('actual',true)->exists(),403);
            $player=User::whereKey($setup['player_id'])->where('type','player')->firstOrFail();
            $age=$player->player?->born_date ? Carbon::parse($player->player->born_date)->age : null;
            abort_if($age===null || $age<5 || $age>99,422,'Update the player profile birth date before starting guided throwing. Age is read from the profile.');
            $date=Carbon::now($setup['timezone'])->toDateString();
            $plan=array_merge($setup,['version'=>3,'start_ft'=>30,'distance_cap_ft'=>$setup['maximum_ft'],'min_age'=>$age,'max_age'=>$age,'scheduled_date'=>$date,'measured_max_ft'=>$setup['maximum_ft']]);
            $snapshot=$engine->build((int)$setup['readiness'],(bool)$setup['pain'],(float)$setup['maximum_ft'],$plan,$date,$age);
            abort_if($snapshot['status']==='needs_plan',422,$snapshot['message']);
            $snapshot['plan']=$plan;
            $snapshot['timezone']=$setup['timezone'];
            $snapshot['baseline']=['maximum_ft'=>$setup['maximum_ft'],'source'=>'Session setup measured maximum','review_reference_ft'=>$setup['maximum_ft']];
            LongTossGuide::create(['practice_id'=>$practice->id,'player_id'=>$player->id,'team_id'=>$practice->team_id,
                'readiness'=>$setup['readiness'],'pain'=>$setup['pain'],'snapshot'=>$snapshot,
                'status'=>$snapshot['status']==='rest'?'stopped':'active','starting_count'=>0]);
        }
    }
}
