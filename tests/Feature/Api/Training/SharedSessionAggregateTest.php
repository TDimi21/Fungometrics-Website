<?php
namespace Tests\Feature\Api\Training;
use App\Models\{User,Team,Practice,BattingPracticeResult,BullpenPracticeResult,CagePracticeResult,LongTossPractice,ExitVelocityPractice,WeightBallPractice};
use App\Services\ResultTrainingService;
use Tests\TestCase;
class SharedSessionAggregateTest extends TestCase
{
    public static function modes(): array { return [
        ['Batting','B','HP',BattingPracticeResult::class,'batter_id'],['Bullpen','P','HP',BullpenPracticeResult::class,'pitcher_id'],
        ['Cage','C','HP',CagePracticeResult::class,'user_id'],['LongToss','T','LT',LongTossPractice::class,'user_id'],
        ['ExitVelocity','T','EV',ExitVelocityPractice::class,'user_id'],['WeightBall','T','WB',WeightBallPractice::class,'user_id'],
    ]; }
    /** @dataProvider modes */
    public function test_each_shared_team_counts_the_same_result_once_and_private_sessions_stay_private($name,$type,$mode,$model,$identity): void
    {
        $user=User::factory()->create(['type'=>'player']);
        [$a,$b,$outsider]=Team::factory()->count(3)->create()->all();
        $shared=Practice::factory()->create(['user_id'=>$user->id,'team_id'=>$a->id,'is_player_shared'=>true,'type'=>$type,'modes'=>$mode]);
        $shared->sharedTeams()->sync([$a->id,$b->id]);
        $extra=in_array($type,['B','P'])?['is_in_match'=>false]:[];
        $row=$model::factory()->create(['practice_id'=>$shared->id,'team_id'=>$a->id,$identity=>$user->id]+$extra);
        $private=Practice::factory()->create(['user_id'=>$user->id,'team_id'=>null,'type'=>$type,'modes'=>$mode]);
        $hidden=$model::factory()->create(['practice_id'=>$private->id,'team_id'=>null,$identity=>$user->id]+$extra);
        $dates=[now()->subDay()->toDateString(),now()->addDay()->toDateString()];
        $method='get'.$name.'Results';$recent=$method.'LastSessions';
        foreach([$a,$b] as $team){
            $this->assertSame([$row->id],ResultTrainingService::$method($team->id,[$user->id],$dates)->pluck('id')->all());
            $this->assertSame([$row->id],ResultTrainingService::$recent($team->id,[$user->id])->pluck('id')->all());
        }
        $this->assertCount(0,ResultTrainingService::$method($outsider->id,[$user->id],$dates));
        $this->assertCount(0,ResultTrainingService::$recent($outsider->id,[$user->id]));
        $this->assertCount(2,ResultTrainingService::$method('',[$user->id],$dates));
    }
}
