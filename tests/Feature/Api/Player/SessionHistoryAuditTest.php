<?php
namespace Tests\Feature\Api\Player;

use App\Models\{User, Practice, BattingPracticeResult, BullpenPracticeResult, CagePracticeResult};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionHistoryAuditTest extends TestCase
{
    public static function modes(): array
    {
        return [['batting','B',BattingPracticeResult::class,'batter_id','batting'],['bullpen','P',BullpenPracticeResult::class,'pitcher_id','bullpen'],['cage','C',CagePracticeResult::class,'user_id','cage']];
    }
    /** @dataProvider modes */
    public function test_player_history_filters_result_rows_and_orders_before_pagination($endpoint,$type,$model,$identity,$relation): void
    {
        $player=User::factory()->create(['type'=>'player']);
        $other=User::factory()->create(['type'=>'player']);
        Sanctum::actingAs($player,['player']);
        $old=Practice::factory()->create(['user_id'=>$player->id,'type'=>$type,'note'=>'Old session','created_at'=>now()->subDays(10)]);
        $new=Practice::factory()->create(['user_id'=>$player->id,'type'=>$type,'note'=>'Today session','created_at'=>now()]);
        $own=$model::factory()->create(['practice_id'=>$new->id,$identity=>$player->id]);
        $foreign=$model::factory()->create(['practice_id'=>$new->id,$identity=>$other->id]);
        $response=$this->getJson('/api/player/sessions/'.$endpoint)->assertOk();
        $response->assertJsonPath('data.data.0.id',$new->id)->assertJsonCount(1,'data.data.0.'.$relation)->assertJsonPath('data.data.0.'.$relation.'.0.id',$own->id);
        $response->assertJsonMissing(['id'=>$foreign->id]);
        $this->getJson('/api/player/sessions/'.$endpoint.'?search=Old')->assertOk()->assertJsonCount(1,'data.data')->assertJsonPath('data.data.0.id',$old->id);
    }
    public function test_training_history_spans_all_teams_and_personal_sessions_without_current_membership(): void
    {
        $player=User::factory()->create(['type'=>'player']);
        $coach=User::factory()->create(['type'=>'coach']);
        $teams=\App\Models\Team::factory()->count(2)->create();
        Sanctum::actingAs($player,['player']);
        $expected=[];
        foreach(range(0,17) as $i) {
            $teamId=$i%3===2 ? null : $teams[$i%3]->id;
            $session=Practice::factory()->create(['type'=>'T','modes'=>'WB','team_id'=>$teamId,'user_id'=>$teamId?$coach->id:$player->id]);
            \App\Models\WeightBallPractice::factory()->create(['practice_id'=>$session->id,'user_id'=>$player->id,'team_id'=>$teamId]);
            $expected[]=$session->id;
        }
        $hidden=Practice::factory()->create(['type'=>'T','modes'=>'WB','team_id'=>$teams[0]->id,'user_id'=>$coach->id]);
        \App\Models\WeightBallPractice::factory()->create(['practice_id'=>$hidden->id,'user_id'=>$coach->id,'team_id'=>$teams[0]->id]);
        $first=$this->getJson('/api/player/sessions/training')->assertOk()->json('data');
        $second=$this->getJson('/api/player/sessions/training?page=2')->assertOk()->json('data');
        $ids=collect(array_merge($first['data'],$second['data']))->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expected,$ids);
        $this->assertNotContains($hidden->id,$ids);
    }

    public function test_enum_player_can_list_connected_teams(): void
    {
        $player=User::factory()->create(['type'=>\App\Models\Concerns\UserTypes::PLAYER]);
        Sanctum::actingAs($player,['player']);
        $this->getJson('/api/player/me/teams')->assertOk()->assertJsonCount(0,'data');
    }
}
