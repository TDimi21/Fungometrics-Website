<?php
namespace Tests\Feature\Api\Player;
use App\Models\{User,Practice,Profile};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class SessionStatisticsScopeTest extends TestCase
{
    public static function modes(): array {return [
        ['batting','B','HP','BattingPracticeResult','batter_id'],['bullpen','P','HP','BullpenPracticeResult','pitcher_id'],
        ['cage','C','HP','CagePracticeResult','user_id'],['longtoss','T','LT','LongTossPractice','user_id'],
        ['exitvelocity','T','EV','ExitVelocityPractice','user_id'],['weightball','T','WB','WeightBallPractice','user_id'],
    ];}
    /** @dataProvider modes */
    public function test_player_statistics_cannot_be_expanded_to_other_players_by_omitting_scope($endpoint,$type,$mode,$model,$identity): void
    {
        $player=User::factory()->create(['type'=>'player','subscription_plan'=>'player_pro']);
        $other=User::factory()->create(['type'=>'player']);
        foreach([$player,$other] as $user)Profile::factory()->create(['user_id'=>$user->id]);
        $practice=Practice::factory()->create(['user_id'=>$player->id,'type'=>$type,'modes'=>$mode]);
        $model='\\App\\Models\\'.$model;
        $own=$model::factory()->create(['practice_id'=>$practice->id,$identity=>$player->id]);
        $foreign=$model::factory()->create(['practice_id'=>$practice->id,$identity=>$other->id]);
        Sanctum::actingAs($player,['player']);
        $r=$this->getJson('/api/statistics/'.$practice->id.'/'.$endpoint.'?player=0')->assertOk();
        $r->assertJsonMissing(['id'=>$foreign->id]);
        $this->assertStringContainsString($own->id,$r->getContent());
    }
}
