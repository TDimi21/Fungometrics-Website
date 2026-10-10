<?php
namespace Tests\Feature\Api\Planner;
use App\Models\{User,Team,DailyPlan,DailyPlanAssignment,DailyPlanProgress,WorkoutTemplate,WorkoutProgram,Practice,LongTossPractice,BullpenPracticeResult};
use App\Services\Planner\DailyThrowLedgerService;
use Database\Seeders\FlameBangersWorkoutTemplateSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedPlannerTest extends TestCase
{
    private $coach,$player,$second,$team;
    protected function setUp(): void {
        parent::setUp();config(['access.temporary_full_access.enabled'=>true,'access.temporary_full_access.ends_at'=>null]);
        $this->coach=User::factory()->create(['type'=>'coach']);$this->player=User::factory()->create(['type'=>'player']);$this->second=User::factory()->create(['type'=>'player']);$this->team=Team::factory()->create();
        foreach([$this->coach,$this->player,$this->second] as $user)$this->grantTeamAccess($user,$this->team);
        $this->seed(FlameBangersWorkoutTemplateSeeder::class);$this->asCoach();
    }
    private function asCoach(){Sanctum::actingAs($this->coach,['coach']);}
    private function draft($slug='velocity-day',$date=null): array {
        $t=WorkoutTemplate::where('slug','flamebangers-'.$slug)->firstOrFail();
        return $this->postJson("/api/coach/workout-templates/{$t->id}/use",['id'=>(string)Str::uuid(),'team_id'=>$this->team->id,'date'=>$date??now()->toDateString(),'player_ids'=>[$this->player->id]])->assertCreated()->json('data');
    }
    private function publish(array $plan): array {return $this->postJson('/api/coach/daily-plans',$plan+['assigned_player_ids'=>[$this->player->id]] +[])->json('data')??[];}
    private function program(): array {
        $t=WorkoutTemplate::where('slug','flamebangers-bullpen-day')->firstOrFail();$r=WorkoutTemplate::where('slug','flamebangers-recovery-day')->firstOrFail();
        return $this->postJson('/api/coach/workout-programs',['id'=>(string)Str::uuid(),'version'=>0,'team_id'=>$this->team->id,'name'=>'Living program','start_date'=>now()->addDay()->toDateString(),'weeks'=>4,'schedule'=>[
            ['id'=>(string)Str::uuid(),'day_offset'=>0,'template_id'=>$t->id,'phase'=>'Foundation','player_ids'=>[$this->player->id,$this->second->id]],
            ['id'=>(string)Str::uuid(),'day_offset'=>2,'template_id'=>$r->id,'phase'=>'Recovery','player_ids'=>[$this->player->id]],
        ]])->assertOk()->json('data');
    }
    public function test_cross_device_draft_versions_and_contract_are_identical(): void {
        $p=$this->draft();$v=$p['version'];
        $b=$this->postJson('/api/coach/daily-plans',array_merge($p,['name'=>'Edited on phone','version'=>$v]))->assertOk()->assertJsonPath('data.planner_contract_version','2.0')->json('data');
        $this->postJson('/api/coach/daily-plans',array_merge($p,['name'=>'Stale web overwrite','version'=>$v]))->assertConflict();
        $rows=$this->getJson('/api/coach/daily-plans?team_id='.$this->team->id)->assertOk()->json('data');$this->assertSame('Edited on phone',$rows[0]['name']);
        $published=$this->postJson('/api/coach/daily-plans',array_merge($b,['status'=>'published']))->assertOk()->json('data');
        Sanctum::actingAs($this->player,['player']);
        $web=$this->getJson('/api/player/daily-plans/'.$p['id'])->assertOk()->json('data');$mobile=$this->getJson('/api/player/planner/day?date='.now()->toDateString())->assertOk()->json('data.0');
        $this->assertSame($web['blocks'],$mobile['blocks']);$this->assertSame($published['version'],$mobile['version']);
    }
    public function test_rolling_publication_selective_updates_and_master_ownership(): void {
        $p=$this->program();$entry=$p['schedule'][0]['id'];$url='/api/coach/workout-programs/'.$p['id'].'/publish';
        $p=$this->postJson($url,['version'=>$p['version'],'workload_approved'=>true,'entry_ids'=>[$entry]])->assertOk()->json('data');
        $this->assertDatabaseCount('daily_plans',2);$this->assertArrayNotHasKey('published_at',$p['schedule'][1]);
        $oldOther=$p['schedule'][0]['daily_plan_ids'][$this->second->id];
        $p['schedule'][0]['snapshot']['description']='Selected athlete correction';
        $p=$this->postJson('/api/coach/workout-programs',$p)->assertOk()->json('data');
        $p=$this->postJson($url,['version'=>$p['version'],'workload_approved'=>true,'entry_ids'=>[$entry],'player_ids'=>[$this->player->id]])->assertOk()->json('data');
        $this->assertSame($oldOther,$p['schedule'][0]['daily_plan_ids'][$this->second->id]);$this->assertDatabaseCount('daily_plan_revisions',1);
        $otherCoach=User::factory()->create(['type'=>'coach']);$this->grantTeamAccess($otherCoach,$this->team);Sanctum::actingAs($otherCoach,['coach']);$this->postJson('/api/coach/workout-programs',$p)->assertForbidden();
    }
    public function test_duplicate_session_publication_rolls_back(): void {
        $p=$this->program();$entry=$p['schedule'][0]['id'];$this->postJson('/api/coach/workout-programs/'.$p['id'].'/publish',['version'=>1,'workload_approved'=>true,'entry_ids'=>[$entry]])->assertOk();
        $other=$this->program();$this->postJson('/api/coach/workout-programs/'.$other['id'].'/publish',['version'=>1,'workload_approved'=>true,'entry_ids'=>[$other['schedule'][0]['id']]])->assertConflict()->assertJsonPath('message','SESSION CONFLICT');$this->assertDatabaseCount('daily_plans',2);
    }
    public function test_quick_counts_canonical_sessions_and_retries_do_not_double_count(): void {
        $p=$this->draft();DailyPlan::find($p['id'])->update(['status'=>'published']);$items=collect($p['buckets'])->flatMap(fn($b)=>$b['items']);$quick=$items->firstWhere('name','PlyoCare Reverse Throws');$long=$items->firstWhere('name','Jaeger Long Toss Series');
        $session=Practice::factory()->create(['team_id'=>$this->team->id,'user_id'=>$this->player->id,'type'=>'T','modes'=>'LT','is_completed'=>true]);
        for($i=1;$i<=28;$i++)LongTossPractice::create(['practice_id'=>$session->id,'team_id'=>$this->team->id,'user_id'=>$this->player->id,'set'=>1,'sort'=>$i,'hop'=>0,'distance'=>100]);
        Sanctum::actingAs($this->player,['player']);$payload=['version'=>0,'items'=>[$quick['id']=>['done'=>true,'quick_throws'=>[['count'=>20,'category'=>'warmup_throws','intent'=>50,'timestamp'=>now()->toIso8601String()]]],$long['id']=>['done'=>true,'session_id'=>$session->id]]];
        $this->postJson('/api/player/daily-plans/'.$p['id'].'/progress',$payload)->assertOk();$this->postJson('/api/player/daily-plans/'.$p['id'].'/progress',$payload)->assertConflict();
        $ledger=app(DailyThrowLedgerService::class)->build($this->player->id,now()->toDateString());$this->assertSame(48,$ledger['total_throws']);$this->assertSame(20,$ledger['warmup_throws']);$this->assertSame(28,$ledger['long_toss_throws']);
    }
    public function test_post_training_alert_is_visible_and_reviewed_by_team_coaches(): void {
        $p=$this->draft();DailyPlan::find($p['id'])->update(['status'=>'published']);Sanctum::actingAs($this->player,['player']);
        $this->postJson('/api/player/daily-plans/'.$p['id'].'/progress',['version'=>0,'post_training'=>['arm_fatigue'=>7,'arm_soreness'=>2,'pain'=>false]])->assertOk();
        $other=User::factory()->create(['type'=>'coach']);$this->grantTeamAccess($other,$this->team);Sanctum::actingAs($other,['coach']);
        $row=$this->getJson('/api/coach/planner/day?team_id='.$this->team->id.'&date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.post_training.needs_attention',true)->json('data.0');
        $this->postJson('/api/coach/daily-plans/'.$p['id'].'/alert-review',['player_id'=>$this->player->id,'version'=>$row['progress']['version'],'action'=>'Discussed recovery session'])->assertOk();
        $this->asCoach();$this->getJson('/api/coach/planner/day?team_id='.$this->team->id.'&date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.alert_review.reviewed_by',$other->id);
    }
    public function test_old_plan_and_cross_team_access(): void {
        $p=$this->draft();DailyPlan::find($p['id'])->update(['status'=>'published','buckets'=>[['type'=>'recovery','items'=>[['id'=>'legacy','name'=>'Rest']]]]]);Sanctum::actingAs($this->player,['player']);$this->getJson('/api/player/daily-plans/'.$p['id'])->assertOk()->assertJsonPath('data.blocks.0.items.0.execution_type','COMPLETE');
        $outsider=User::factory()->create(['type'=>'coach']);Sanctum::actingAs($outsider,['coach']);$this->getJson('/api/coach/planner/day?team_id='.$this->team->id.'&date='.now()->toDateString())->assertForbidden();
    }
    public function test_linked_session_start_is_idempotent_and_completion_is_canonical(): void {
        $p=$this->draft('bullpen-day');DailyPlan::find($p['id'])->update(['status'=>'published']);
        $item=collect($p['buckets'])->flatMap(fn($b)=>$b['items'])->first(fn($i)=>($i['metadata']['session_type']??null)==='bullpen');
        Sanctum::actingAs($this->player,['player']);
        $payload=['team'=>$this->team->id,'type'=>'P','note'=>'Assigned bullpen','players'=>[['id'=>$this->player->id,'sort'=>0]],'planner_plan_id'=>$p['id'],'planner_item_id'=>$item['id']];
        $session=$this->postJson('/api/training',$payload)->assertCreated()->json('data.id');
        $this->postJson('/api/training',$payload)->assertOk()->assertJsonPath('data.id',$session);
        $this->assertDatabaseCount('practices',1);
        $this->getJson('/api/player/planner/day?date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.actual_results.'.$item['id'].'.done',false);
        Practice::find($session)->update(['is_completed'=>true]);
        $this->getJson('/api/player/planner/day?date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.actual_results.'.$item['id'].'.done',true);
        Sanctum::actingAs($this->second,['player']);$this->postJson('/api/training',$payload)->assertForbidden();
    }

    public function test_explicit_merge_keeps_both_sources_and_one_session_launcher(): void {
        $first=$this->program();$first=$this->postJson('/api/coach/workout-programs/'.$first['id'].'/publish',['version'=>1,'workload_approved'=>true,'entry_ids'=>[$first['schedule'][0]['id']]])->assertOk()->json('data');
        $second=$this->program();$url='/api/coach/workout-programs/'.$second['id'].'/publish';$payload=['version'=>1,'workload_approved'=>true,'entry_ids'=>[$second['schedule'][0]['id']],'player_ids'=>[$this->player->id]];
        $conflict=$this->postJson($url,$payload)->assertConflict()->json('conflicts.0');
        $result=$this->postJson($url,$payload+['resolutions'=>[array_merge($conflict,['action'=>'merge'])]])->assertOk()->json('data');
        $id=$result['schedule'][0]['daily_plan_ids'][$this->player->id];$merged=DailyPlan::findOrFail($id);
        $items=collect($merged->buckets)->flatMap(fn($b)=>$b['items']);
        $this->assertCount(1,$items->filter(fn($i)=>($i['metadata']['session_type']??'')==='bullpen'));
        $this->assertCount(2,$items->first(fn($i)=>($i['metadata']['session_type']??'')==='bullpen')['merged_prescriptions']);
        $this->assertDatabaseHas('daily_plan_assignments',['plan_id'=>$conflict['plan_id'],'user_id'=>$this->player->id,'schedule_status'=>'superseded']);
        $this->assertDatabaseHas('daily_plan_assignments',['plan_id'=>$first['schedule'][0]['daily_plan_ids'][$this->second->id],'user_id'=>$this->second->id,'schedule_status'=>'active']);
    }
    public function test_missed_schedule_moves_only_one_player_and_is_retry_safe(): void {
        $p=$this->program();$p=$this->postJson('/api/coach/workout-programs/'.$p['id'].'/publish',['version'=>1,'workload_approved'=>true])->assertOk()->json('data');
        $id=$p['schedule'][0]['daily_plan_ids'][$this->player->id];$otherId=$p['schedule'][0]['daily_plan_ids'][$this->second->id];
        Sanctum::actingAs($this->player,['player']);$payload=['date'=>now()->addDays(2)->toDateString(),'behavior'=>'shift','request_id'=>(string)Str::uuid()];
        $first=$this->postJson('/api/player/daily-plans/'.$id.'/schedule-adjustment',$payload)->assertOk()->json('data');
        $this->postJson('/api/player/daily-plans/'.$id.'/schedule-adjustment',$payload)->assertOk()->assertJsonPath('data',$first);
        $this->assertSame($p['start_date'],WorkoutProgram::find($p['id'])->start_date);
        $this->assertNull(DailyPlanAssignment::where('plan_id',$otherId)->first()->scheduled_date);
        $this->assertSame($p['start_date'],DailyPlan::find($id)->date->format('Y-m-d'));
    }

    public function test_player_day_adjustment_preserves_results_and_other_players(): void {
        $p=$this->draft('recovery-day');$plan=DailyPlan::find($p['id']);$plan->update(['status'=>'published']);DailyPlanAssignment::create(['plan_id'=>$plan->id,'user_id'=>$this->second->id]);
        $buckets=$plan->buckets;$done=$buckets[0]['items'][0]['id'];$next=$buckets[0]['items'][1]['id'];
        Sanctum::actingAs($this->player,['player']);$this->postJson('/api/player/daily-plans/'.$plan->id.'/progress',['version'=>0,'items'=>[$done=>['done'=>true]]])->assertOk();
        $otherCoach=User::factory()->create(['type'=>'coach']);$this->grantTeamAccess($otherCoach,$this->team);Sanctum::actingAs($otherCoach,['coach']);
        $buckets[0]['items'][1]['prescription_text']='Easy recovery — 5 minutes';
        $url='/api/coach/daily-plans/'.$plan->id.'/prescription-adjustment';
        $payload=['player_id'=>$this->player->id,'version'=>0,'reason'=>'Coach adjustment after check-in','buckets'=>$buckets];
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('data.assignment.prescription_override.version',1);
        $this->assertSame($plan->buckets,$plan->fresh()->buckets);
        $this->assertDatabaseCount('daily_plan_revisions',1);
        Sanctum::actingAs($this->player,['player']);
        $this->getJson('/api/player/planner/day?date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.blocks.0.items.1.prescription_text','Easy recovery — 5 minutes')->assertJsonPath('data.0.actual_results.'.$done.'.done',true);
        $this->postJson('/api/player/daily-plans/'.$plan->id.'/progress',['version'=>1,'assignment_version'=>0,'items'=>[$next=>['done'=>true]]])->assertConflict();
        Sanctum::actingAs($this->second,['player']);
        $this->getJson('/api/player/planner/day?date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.blocks.0.items.1.prescription_text',$plan->buckets[0]['items'][1]['prescription_text']);
        Sanctum::actingAs($otherCoach,['coach']);$payload['version']=1;$payload['buckets'][0]['items'][0]['name']='Rewrite recorded exercise';$this->postJson($url,$payload)->assertConflict();
    }

    public function test_strength_sets_create_one_observed_record_and_edits_keep_audit_history(): void {
        $p=$this->draft('recovery-day');$plan=DailyPlan::find($p['id']);$plan->update(['status'=>'published','buckets'=>[['type'=>'strength_primary','title'=>'Strength','items'=>[['id'=>'squat','name'=>'Back Squat','metadata'=>['strength_metric'=>'back_squat']]]]]]);
        Sanctum::actingAs($this->player,['player']);$url='/api/player/daily-plans/'.$plan->id.'/progress';
        $result=$this->postJson($url,['version'=>0,'items'=>['squat'=>['sets'=>[['weight'=>100,'reps'=>5,'done'=>true],['weight'=>110,'reps'=>3,'done'=>true]]]]])->assertOk()->json('data');
        $this->assertEquals(830,$result['items']['squat']['strength_summary']['volume_lb']);$id=$result['items']['squat']['strength_fitness_id'];
        $this->assertNull(\App\Models\PlayerFitness::find($id)->back_squat);
        $this->postJson($url,['version'=>1,'items'=>['squat'=>['sets'=>[['weight'=>100,'reps'=>5,'done'=>true]]]]])->assertOk()->assertJsonPath('data.items.squat.strength_fitness_id',$id)->assertJsonCount(1,'data.actual_history');
        $this->assertSame(1,\App\Models\PlayerFitness::where('user_id',$this->player->id)->count());
    }

}
