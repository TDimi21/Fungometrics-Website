<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Utils\Helper;

final class CageStatisticsService
{
    public array $rangesLaunchAngles;
    public array $rangesSprayAngles;

    public function __construct()
    {
        $this->rangesLaunchAngles = config('constants.launchAngleCageRanges');
        $this->rangesSprayAngles = config('constants.sprayAngleCageRanges');
    }

    public function launchAngleTotals($data): array { if(0===$data->count())return[]; $result['team_totals']=$this->launchAngleTotalsTeam($data); $result['players']=$this->launchAngleTotalsPlayers($data); return $result; }
    public function launchAnglePercents($data): array { if(0===$data->count())return[]; $result['team_totals']=$this->launchAnglePercentsTeam($data); $result['players']=$this->launchAnglePercentsPlayers($data); return $result; }
    public function launchAngleExitVelocityAverage($data): array { if(0===$data->count())return[]; $result['team_totals']=$this->launchAngleExitVelocityAverageTeam($data); $result['players']=$this->launchAngleExitVelocityAveragePlayers($data); return $result; }
    public function sprayAngleTotals($data): array { if(0===$data->count())return[]; $result['team_totals']=$this->sprayAngleTotalsTeam($data); $result['players']=$this->sprayAngleTotalsPlayers($data); return $result; }
    public function sprayAnglePercents($data): array { if(0===$data->count())return[]; $result['team_totals']=$this->sprayAnglePercentsTeam($data); $result['players']=$this->sprayAnglePercentsPlayers($data); return $result; }
    public function sprayAngleExitVelocityAverage($data): array { if(0===$data->count())return[]; $result['team_totals']=$this->sprayAngleExitVelocityAverageTeam($data); $result['players']=$this->sprayAngleExitVelocityAveragePlayers($data); return $result; }

    private function launchAngleTotalsTeam($data) { $r=['swings'=>$data->count()]; foreach($this->rangesLaunchAngles as $k=>$l){$r[$k]=$data->whereBetween('launch_angle',$l)->count();} return $r; }
    private function launchAngleTotalsPlayers($data) { $r=[]; foreach($data->groupBy('user_id') as $k=>$item){$r[$k]=['swings'=>$item->count()]; foreach($this->rangesLaunchAngles as $rk=>$l){$r[$k][$rk]=$item->whereBetween('launch_angle',$l)->count();}} return $r; }
    private function launchAnglePercentsTeam($data) { $t=$data->count(); $r=['swings'=>$t]; foreach($this->rangesLaunchAngles as $k=>$l){$r[$k]=round(Helper::caseDivide($data->whereBetween('launch_angle',$l)->count(),$t)*100,2);} return $r; }
    private function launchAnglePercentsPlayers($data) { $r=[]; foreach($data->groupBy('user_id') as $k=>$item){$t=$item->count(); $r[$k]=['swings'=>$t]; foreach($this->rangesLaunchAngles as $rk=>$l){$r[$k][$rk]=round(Helper::caseDivide($item->whereBetween('launch_angle',$l)->count(),$t)*100,2);}} return $r; }
    private function launchAngleExitVelocityAverageTeam($data) { $t=$data->count(); $r=['swings'=>$t]; foreach($this->rangesLaunchAngles as $k=>$l){$r[$k]=round($data->whereBetween('launch_angle',$l)->avg('launch_angle_velocity')??0,2);} return $r; }
    private function launchAngleExitVelocityAveragePlayers($data) { $r=[]; foreach($data->groupBy('user_id') as $k=>$item){$t=$item->count(); $r[$k]=['swings'=>$t]; foreach($this->rangesLaunchAngles as $rk=>$l){$r[$k][$rk]=round($item->whereBetween('launch_angle',$l)->avg('launch_angle_velocity')??0,2);}} return $r; }
    private function sprayAngleTotalsTeam($data) { $r=['swings'=>$data->count()]; foreach($this->rangesSprayAngles as $k=>$l){$r[$k]=$data->whereBetween('spray_angle',$l)->count();} return $r; }
    private function sprayAngleTotalsPlayers($data) { $r=[]; foreach($data->groupBy('user_id') as $k=>$item){$r[$k]=['swings'=>$item->count()]; foreach($this->rangesSprayAngles as $rk=>$l){$r[$k][$rk]=$item->whereBetween('spray_angle',$l)->count();}} return $r; }
    private function sprayAnglePercentsTeam($data) { $t=$data->count(); $r=['swings'=>$t]; foreach($this->rangesSprayAngles as $k=>$l){$r[$k]=round(Helper::caseDivide($data->whereBetween('spray_angle',$l)->count(),$t)*100,2);} return $r; }
    private function sprayAnglePercentsPlayers($data) { $r=[]; foreach($data->groupBy('user_id') as $k=>$item){$t=$item->count(); $r[$k]=['swings'=>$t]; foreach($this->rangesSprayAngles as $rk=>$l){$r[$k][$rk]=round(Helper::caseDivide($item->whereBetween('spray_angle',$l)->count(),$t)*100,2);}} return $r; }
    private function sprayAngleExitVelocityAverageTeam($data) { $r=['swings'=>$data->count()]; foreach($this->rangesSprayAngles as $k=>$l){$r[$k]=round($data->whereBetween('spray_angle',$l)->avg('launch_angle_velocity')??0,2);} return $r; }
    private function sprayAngleExitVelocityAveragePlayers($data) { $r=[]; foreach($data->groupBy('user_id') as $k=>$item){$t=$item->count(); $r[$k]=['swings'=>$t]; foreach($this->rangesSprayAngles as $rk=>$l){$r[$k][$rk]=round($item->whereBetween('spray_angle',$l)->avg('launch_angle_velocity')??0,2);}} return $r; }

    public function fcs($data): array
    {
        if (0 === $data->count()) return [];
        $ev50=78; $ev90=98; $d50=180; $d90=300;
        $clamp=fn($v,$min,$max)=>max($min,min($max,$v));
        $launchScoreFn=function($la,$traj){
            if($la!==null&&$la>=-90&&$la<=90){
                if($la>=10&&$la<=25)return 100; if($la>=26&&$la<=35)return 82;
                if($la>=0&&$la<10)return 72; if($la>35&&$la<=50)return 60;
                return $la<0?40:45;
            }
            return match(strtoupper((string)$traj)){'LD'=>100,'FB'=>82,'PF'=>68,'GB'=>55,'PU'=>35,default=>60};
        };
        $powerScores=$launchScores=$sprayAngles=$evVals=$distVals=[];
        $sweetCount=$ldCount=0;
        foreach($data as $row){
            $ev=is_numeric($row->launch_angle_velocity)&&$row->launch_angle_velocity>=10&&$row->launch_angle_velocity<=130?(float)$row->launch_angle_velocity:null;
            $dist=is_numeric($row->distance_travel)&&$row->distance_travel>=10&&$row->distance_travel<=500?(float)$row->distance_travel:null;
            $la=is_numeric($row->launch_angle)&&$row->launch_angle>=-90&&$row->launch_angle<=90?(float)$row->launch_angle:null;
            $spray=is_numeric($row->spray_angle)&&$row->spray_angle>=-90&&$row->spray_angle<=90?(float)$row->spray_angle:null;
            $traj=$row->type_of_hit??null;
            if($ev===null&&$dist===null&&$la===null&&$traj===null)continue;
            $evN=$ev!==null?$clamp(($ev-$ev50)/max(1,$ev90-$ev50),0,1):null;
            $dN=$dist!==null?$clamp(($dist-$d50)/max(1,$d90-$d50),0,1):null;
            if($evN!==null&&$dN!==null)$powerScores[]=($evN*0.65+$dN*0.35)*100;
            elseif($evN!==null)$powerScores[]=$evN*100;
            elseif($dN!==null)$powerScores[]=$dN*100;
            else $powerScores[]=50;
            $launchScores[]=$launchScoreFn($la,$traj);
            if($spray!==null)$sprayAngles[]=$spray;
            if($ev!==null)$evVals[]=$ev;
            if($dist!==null)$distVals[]=$dist;
            if($la!==null&&$la>=10&&$la<=25)$sweetCount++;
            if(strtoupper((string)$traj)==='LD')$ldCount++;
        }
        $N=count($powerScores);
        // Show cage stats for any recorded swing (matches the app + the other
        // disciplines, which gate at >=1). A 3-swing minimum silently blanked the
        // whole FCS panel — score AND tiles — for small sessions.
        if($N<1)return[];
        $powerScore=array_sum($powerScores)/$N;
        $launchScore=array_sum($launchScores)/$N;
        $pull=$middle=$oppo=0;
        foreach($sprayAngles as $a){if($a<-15)$pull++; elseif($a>15)$oppo++; else $middle++;}
        $sprayTotal=$pull+$middle+$oppo; $approachScore=65;
        if($sprayTotal>0){$probs=array_filter([$pull/$sprayTotal,$middle/$sprayTotal,$oppo/$sprayTotal]); $entropy=-array_sum(array_map(fn($p)=>$p*log($p),$probs)); $approachScore=$clamp(($entropy/log(3))*100,0,100);}
        $raw=$powerScore*0.45+$launchScore*0.40+$approachScore*0.15;
        $reliability=$clamp(sqrt($N/30),0.6,1.0);
        $fcs=round($clamp(50+(($raw-50)*$reliability),0,100),1);
        return ['fcs'=>$fcs,'total'=>$N,'powerScore'=>round($powerScore,1),'launchScore'=>round($launchScore,1),'approachScore'=>round($approachScore,1),'reliability'=>round($reliability,2),'avgEV'=>count($evVals)?round(array_sum($evVals)/count($evVals),1):null,'maxEV'=>count($evVals)?round(max($evVals),1):null,'avgDist'=>count($distVals)?round(array_sum($distVals)/count($distVals),1):null,'sweetSpotPct'=>$N>0?round(($sweetCount/$N)*100,1):0,'ldPct'=>$N>0?round(($ldCount/$N)*100,1):0,'pullPct'=>$sprayTotal>0?round(($pull/$sprayTotal)*100,1):null,'middlePct'=>$sprayTotal>0?round(($middle/$sprayTotal)*100,1):null,'oppoPct'=>$sprayTotal>0?round(($oppo/$sprayTotal)*100,1):null];
    }
}
