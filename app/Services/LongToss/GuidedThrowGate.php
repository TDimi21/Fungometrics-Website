<?php
namespace App\Services\LongToss;
use App\Models\{LongTossGuide, LongTossPractice};
class GuidedThrowGate {
    public function check(string $practice, string $player): void {
        $guide=LongTossGuide::where('practice_id',$practice)->where('player_id',$player)->lockForUpdate()->first();
        if(!$guide) return; // Preserve historical/manual sessions and old clients.
        abort_unless($guide->status==='active',422,'Guided throwing is stopped or finished. Do not record another guided throw.');
        $stages=$guide->snapshot['stages'];
        $limit=array_sum(array_column(array_slice($stages,0,$guide->stage+1),'throws'));
        $count=LongTossPractice::where('practice_id',$practice)->where('user_id',$player)->count()-$guide->starting_count;
        abort_if($count >= $limit,422,'Checkpoint reached. Check arm feel before continuing.');
    }
}
