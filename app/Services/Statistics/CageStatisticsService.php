cat >> app/Services/Statistics/LongTossStatisticsService.php << 'EOF'

    public function lts($data, array $practiceIds=[]): array
    {
        if(0===$data->count())return[];
        $clamp=fn($v,$min,$max)=>max($min,min($max,(float)$v));
        $hopScore=fn($hop)=>match(true){$hop<=0=>100,$hop===1=>82,$hop===2=>58,default=>25};
        $allThrows=[];
        foreach($data as $row){$dist=(float)($row->distance??0);if($dist<20||$dist>600)continue;$allThrows[]=['dist'=>$dist,'hop'=>(int)($row->hop??3),'userId'=>(string)($row->user_id??'unknown'),'date'=>$row->created_at??null];}
        if(empty($allThrows))return[];
        $playerDists=[];$playerThrows=[];
        foreach($allThrows as $t){$playerDists[$t['userId']][]=$t['dist'];$playerThrows[$t['userId']][]=$t;}
        $extPctScores=[];$estPeakVelos=[];$peakNoHopDists=[];$intensityScores=[];$carryScores=[];
        foreach($playerThrows as $pid=>$throws){
            $nhd=array_values(array_map(fn($t)=>(float)$t['dist'],array_filter($throws,fn($t)=>(int)$t['hop']<=0)));
            $peakNoHop=!empty($nhd)?max($nhd):max(array_column($throws,'dist'));$peakNoHop=(float)$peakNoHop;$peakNoHopDists[]=$peakNoHop;
            $epv=$this->estimatePeakMoundVelocityFromDistance($peakNoHop);$estPeakVelos[]=$epv;
            $extPctScores[]=$clamp((($epv-67.0)/33.0)*100.0,0.0,100.0);
            foreach($throws as $t){$ip=$this->estimateIntensityPctFromDistance($epv,(float)$t['dist']);$intensityScores[]=$ip;$carryScores[]=($ip*$hopScore((int)$t['hop']))/100.0;}
        }
        $avgExt=count($extPctScores)?array_sum($extPctScores)/count($extPctScores):0.0;$extScore=$clamp(($avgExt/100.0)*25.0,0.0,25.0);
        $avgCarry=count($carryScores)?array_sum($carryScores)/count($carryScores):50;$carryScore=$clamp(($avgCarry/100.0)*25.0,0.0,25.0);
        $consScores=[];foreach($playerDists as $dists){if(count($dists)<2)continue;$mean=array_sum($dists)/count($dists);$var=array_sum(array_map(fn($d)=>($d-$mean)**2,$dists))/count($dists);$cv=$mean>0?sqrt($var)/$mean:1;$consScores[]=$clamp(1-$cv,0,1)*100;}
        $avgCons=count($consScores)?array_sum($consScores)/count($consScores):50;$consScore=$clamp(($avgCons/100)*20,0,20);
        $sessionAvgs=[];$byP=[];foreach($allThrows as $t){$pid=$t['date']?date('Y-m-d',strtotime((string)$t['date'])):'unknown';$byP[$pid][]=$t['dist'];}foreach($byP as $date=>$dists){$sessionAvgs[$date]=array_sum($dists)/count($dists);}
        $progScore=10;if(count($sessionAvgs)>=2){ksort($sessionAvgs);$vals=array_values($sessionAvgs);$oldest=$vals[0];$newest=$vals[count($vals)-1];$pctChg=$oldest>0?($newest-$oldest)/$oldest:0;$progScore=$clamp(10+($pctChg*100),0,20);}
        $sessionCount=count($sessionAvgs);$availScore=$sessionCount>0?$clamp(min($sessionCount/8,1)*10,0,10):5;
        $totalScore=round($clamp($extScore+$carryScore+$consScore+$progScore+$availScore,0,100),1);
        $playerMaxes=array_map(fn($d)=>max($d),array_values($playerDists));$totalPlayers=count($playerDists);$zeroHop=count(array_filter($allThrows,fn($t)=>$t['hop']<=0));$eliteThrowers=count(array_filter($estPeakVelos,fn($v)=>$v>=90.0));
        $avgIntensity=count($intensityScores)?array_sum($intensityScores)/count($intensityScores):0.0;$avgEPV=count($estPeakVelos)?array_sum($estPeakVelos)/count($estPeakVelos):null;$avgPNHD=count($peakNoHopDists)?array_sum($peakNoHopDists)/count($peakNoHopDists):null;
        return['lts'=>$totalScore,'total'=>count($allThrows),'extensionScore'=>round($extScore,1),'carryScore'=>round($carryScore,1),'consistencyScore'=>round($consScore,1),'progressionScore'=>round($progScore,1),'availabilityScore'=>round($availScore,1),'totalPlayers'=>$totalPlayers,'avgMaxDist'=>$totalPlayers>0?round(array_sum($playerMaxes)/$totalPlayers,1):null,'extensionPct'=>round($avgExt,1),'avgCarryScore'=>round($avgCarry,1),'zeroHopRate'=>count($allThrows)>0?round(($zeroHop/count($allThrows))*100,1):0,'sessionCount'=>$sessionCount,'eliteThrowers'=>$eliteThrowers,'eliteThrowerRate'=>$totalPlayers>0?round(($eliteThrowers/$totalPlayers)*100,1):0,'avgIntensityPct'=>round($avgIntensity,1),'avgEstimatedPeakVelo'=>$avgEPV!==null?round($avgEPV,1):null,'avgPeakNoHopDist'=>$avgPNHD!==null?round($avgPNHD,1):null];
    }

    private function longTossChartRows(): array
    {
        return[['velo'=>67.0,'peak'=>210.0,'intensity'=>[50=>105,55=>116,60=>126,65=>137,70=>147,75=>158,80=>168,85=>179,90=>189,95=>200,100=>210]],['velo'=>72.0,'peak'=>240.0,'intensity'=>[50=>120,55=>132,60=>144,65=>156,70=>168,75=>180,80=>192,85=>204,90=>216,95=>228,100=>240]],['velo'=>77.0,'peak'=>260.0,'intensity'=>[50=>130,55=>143,60=>156,65=>169,70=>182,75=>195,80=>208,85=>221,90=>234,95=>247,100=>260]],['velo'=>82.0,'peak'=>280.0,'intensity'=>[50=>140,55=>154,60=>168,65=>182,70=>196,75=>210,80=>224,85=>238,90=>252,95=>266,100=>280]],['velo'=>87.0,'peak'=>300.0,'intensity'=>[50=>150,55=>165,60=>180,65=>195,70=>210,75=>225,80=>240,85=>255,90=>270,95=>285,100=>300]],['velo'=>92.0,'peak'=>330.0,'intensity'=>[50=>165,55=>182,60=>198,65=>215,70=>231,75=>248,80=>264,85=>281,90=>297,95=>314,100=>330]],['velo'=>97.0,'peak'=>360.0,'intensity'=>[50=>180,55=>198,60=>216,65=>234,70=>252,75=>270,80=>288,85=>306,90=>324,95=>342,100=>360]],['velo'=>100.0,'peak'=>410.0,'intensity'=>[50=>205,55=>226,60=>246,65=>267,70=>287,75=>308,80=>328,85=>349,90=>369,95=>390,100=>410]]];
    }

    private function estimatePeakMoundVelocityFromDistance(float $distance): float
    {
        $rows=$this->longTossChartRows();$distance=max(0.0,$distance);
        if($distance<=$rows[0]['peak']){$lo=$rows[0];return $lo['velo']*($distance/max(1.0,$lo['peak']));}
        $last=$rows[count($rows)-1];if($distance>=$last['peak']){$excess=$distance-$last['peak'];return min(105.0,$last['velo']+($excess/20.0));}
        for($i=0;$i<count($rows)-1;$i++){$a=$rows[$i];$b=$rows[$i+1];if($distance>=$a['peak']&&$distance<=$b['peak']){$pct=($distance-$a['peak'])/max(1.0,($b['peak']-$a['peak']));return $a['velo']+(($b['velo']-$a['velo'])*$pct);}}
        return 67.0;
    }

    private function estimateIntensityPctFromDistance(float $estimatedPeakVelo, float $distance): float
    {
        $row=$this->nearestChartRowByVelocity($estimatedPeakVelo);$chart=$row['intensity'];ksort($chart);
        if($distance<=$chart[50])return max(0.0,min(50.0,($distance/max(1.0,$chart[50]))*50.0));
        if($distance>=$chart[100])return 100.0;
        $keys=array_keys($chart);
        for($i=0;$i<count($keys)-1;$i++){$k1=(int)$keys[$i];$k2=(int)$keys[$i+1];$d1=(float)$chart[$k1];$d2=(float)$chart[$k2];if($distance>=$d1&&$distance<=$d2){$pct=($distance-$d1)/max(1.0,($d2-$d1));return $k1+(($k2-$k1)*$pct);}}
        return 50.0;
    }

    private function nearestChartRowByVelocity(float $velo): array
    {
        $rows=$this->longTossChartRows();$best=$rows[0];$bestDiff=abs($velo-$best['velo']);
        foreach($rows as $row){$diff=abs($velo-$row['velo']);if($diff<$bestDiff){$best=$row;$bestDiff=$diff;}}
        return $best;
    }

}
EOF<?php

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

    public function launchAngleTotals($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->launchAngleTotalsTeam($data);
        $result['players'] = $this->launchAngleTotalsPlayers($data);
        return $result;
    }

    public function launchAnglePercents($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->launchAnglePercentsTeam($data);
        $result['players'] = $this->launchAnglePercentsPlayers($data);
        return $result;
    }

    public function launchAngleExitVelocityAverage($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->launchAngleExitVelocityAverageTeam($data);
        $result['players'] = $this->launchAngleExitVelocityAveragePlayers($data);
        return $result;
    }

    public function sprayAngleTotals($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->sprayAngleTotalsTeam($data);
        $result['players'] = $this->sprayAngleTotalsPlayers($data);
        return $result;
    }

    public function sprayAnglePercents($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->sprayAnglePercentsTeam($data);
        $result['players'] = $this->sprayAnglePercentsPlayers($data);
        return $result;


    }

    public function sprayAngleExitVelocityAverage($data): array
    {
        if(0 === $data->count()) {
            return [];
        }
        $result['team_totals']= $this->sprayAngleExitVelocityAverageTeam($data);
        $result['players'] = $this->sprayAngleExitVelocityAveragePlayers($data);
        return $result;


    }

    private function launchAngleTotalsTeam($data)
    {
        $launchAngleTotals = ['swings' => $data->count()];
        foreach ($this->rangesLaunchAngles as $range => $limits) {
            $launchAngleTotals[$range] = $data->whereBetween('launch_angle', $limits)->count();
        }
        return $launchAngleTotals;
    }


    private function launchAngleTotalsPlayers($data)
    {
        $launchAngleTotals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $launchAngleTotals[$key] = ['swings' => $item->count()];
            foreach ($this->rangesLaunchAngles as $range => $limits) {
                $launchAngleTotals[$key][$range] = $item->whereBetween('launch_angle', $limits)->count();
            }
        }

        return $launchAngleTotals;
    }

    private function launchAnglePercentsTeam($data)
    {
        $totals = $data->count();
        $launchAngleTotals = ['swings' => $totals];
        foreach ($this->rangesLaunchAngles as $range => $limits) {
            $launchAngleTotals[$range] = round(Helper::caseDivide($data->whereBetween('launch_angle', $limits)->count(), $totals)*100, 2);
        }
        return $launchAngleTotals;
    }

    private function launchAnglePercentsPlayers($data)
    {
        $launchAnglePercents=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $launchAnglePercents[$key] = ['swings' => $totals];
            foreach ($this->rangesLaunchAngles as $range => $limits) {
                $launchAnglePercents[$key][$range] = round(Helper::caseDivide($item->whereBetween('launch_angle', $limits)->count(), $totals)
                    *100, 2);
            }
        }

        return $launchAnglePercents;
    }

    private function launchAngleExitVelocityAverageTeam($data)
    {
        $totals = $data->count();
        $launchAngleTotals = ['swings' => $totals];
        foreach ($this->rangesLaunchAngles as $range => $limits) {
            $launchAngleTotals[$range] = round($data->whereBetween('launch_angle', $limits)->avg('launch_angle_velocity')??0, 2);
        }
        return $launchAngleTotals;
    }

    private function launchAngleExitVelocityAveragePlayers($data)
    {
        $launchAngleExitVelocityAverage=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $launchAngleExitVelocityAverage[$key] = ['swings' => $totals];
            foreach ($this->rangesLaunchAngles as $range => $limits) {
                $launchAngleExitVelocityAverage[$key][$range] = round($item->whereBetween('launch_angle', $limits)
                    ->avg('launch_angle_velocity')??0, 2);
            }
        }

        return $launchAngleExitVelocityAverage;
    }

    private function sprayAngleTotalsTeam($data)
    {
        $sprayAngleTotals = ['swings' => $data->count()];
        foreach ($this->rangesSprayAngles as $range => $limits) {
            $sprayAngleTotals[$range] = $data->whereBetween('spray_angle', $limits)->count();
        }
        return $sprayAngleTotals;
    }

    private function sprayAngleTotalsPlayers($data)
    {
        $sprayAngleTotals=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $sprayAngleTotals[$key] = ['swings' => $item->count()];
            foreach ($this->rangesSprayAngles as $range => $limits) {
                $sprayAngleTotals[$key][$range] = $item->whereBetween('spray_angle', $limits)->count();
            }
        }

        return $sprayAngleTotals;
    }

    private function sprayAnglePercentsTeam($data)
    {
        $totals = $data->count();
        $sprayAngleTotals = ['swings' => $totals];
        foreach ($this->rangesSprayAngles as $range => $limits) {
            $sprayAngleTotals[$range] = round(Helper::caseDivide($data->whereBetween('spray_angle', $limits)->count(), $totals)*100, 2);
        }
        return $sprayAngleTotals;
    }

    private function sprayAnglePercentsPlayers($data)
    {
        $sprayAnglePercents=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $sprayAnglePercents[$key] = ['swings' => $totals];
            foreach ($this->rangesSprayAngles as $range => $limits) {
                $sprayAnglePercents[$key][$range] = round(Helper::caseDivide($item->whereBetween(
                    'spray_angle',
                    $limits
                )->count(), $totals)*100, 2);
            }
        }

        return $sprayAnglePercents;
    }

    private function sprayAngleExitVelocityAverageTeam($data)
    {
        $sprayAngleTotals = ['swings' => $data->count()];
        foreach ($this->rangesSprayAngles as $range => $limits) {
            $sprayAngleTotals[$range] = round($data->whereBetween('spray_angle', $limits)
                ->avg('launch_angle_velocity')??0, 2);
        }
        return $sprayAngleTotals;
    }

    private function sprayAngleExitVelocityAveragePlayers($data)
    {
        $sprayAngleExitVelocityAverage=[];
        $groupByPlayer = $data->groupBy('user_id');
        foreach ($groupByPlayer as $key => $item) {
            $totals = $item->count();
            $sprayAngleExitVelocityAverage[$key] = ['swings' => $totals];
            foreach ($this->rangesSprayAngles as $range => $limits) {
                $sprayAngleExitVelocityAverage[$key][$range] = round($item->whereBetween('spray_angle', $limits)
                    ->avg('launch_angle_velocity')??0, 2);
            }
        }

        return $sprayAngleExitVelocityAverage;
    }

    /**
     * Cage Fungo Score (FCS) — mirrors computeCageScore() in TeamStatsPanel/index.js
     *
     * Components:
     *   powerScore  (45%): EV + distance normalized to HS cohort targets
     *   launchScore (40%): per-swing launch angle / trajectory scoring
     *   approachScore (15%): spray-angle entropy (balanced field use)
     *
     * Returns array with 'fcs', 'total', 'powerScore', 'launchScore',
     * 'approachScore', 'avgEV', 'maxEV', 'avgDist', 'sweetSpotPct', 'ldPct',
     * 'pullPct', 'middlePct', 'oppoPct'
     */
    public function fcs($data): array
    {
        if (0 === $data->count()) {
            return [];
        }

        $ev50 = 78; $ev90 = 98;
        $d50  = 180; $d90  = 300;

        $clamp = fn($v, $min, $max) => max($min, min($max, $v));

        $launchScoreFn = function ($la, $traj) {
            if ($la !== null && $la >= -90 && $la <= 90) {
                if ($la >= 10 && $la <= 25) return 100;
                if ($la >= 26 && $la <= 35) return 82;
                if ($la >=  0 && $la <  10) return 72;
                if ($la >  35 && $la <= 50) return 60;
                return $la < 0 ? 40 : 45;
            }
            // fallback to trajectory
            return match (strtoupper((string) $traj)) {
                'LD'  => 100,
                'FB'  => 82,
                'PF'  => 68,
                'GB'  => 55,
                'PU'  => 35,
                default => 60,
            };
        };

        $powerScores  = [];
        $launchScores = [];
        $sprayAngles  = [];
        $evVals       = [];
        $distVals     = [];
        $sweetCount   = 0;
        $ldCount      = 0;

        foreach ($data as $row) {
            $ev   = is_numeric($row->launch_angle_velocity) && $row->launch_angle_velocity >= 10 && $row->launch_angle_velocity <= 130
                ? (float) $row->launch_angle_velocity : null;
            $dist = is_numeric($row->distance_travel) && $row->distance_travel >= 10 && $row->distance_travel <= 500
                ? (float) $row->distance_travel : null;
            $la   = is_numeric($row->launch_angle) && $row->launch_angle >= -90 && $row->launch_angle <= 90
                ? (float) $row->launch_angle : null;
            $spray = is_numeric($row->spray_angle) && $row->spray_angle >= -90 && $row->spray_angle <= 90
                ? (float) $row->spray_angle : null;
            $traj  = $row->type_of_hit ?? null;

            if ($ev === null && $dist === null && $la === null && $traj === null) {
                continue;
            }

            // power
            $evN   = $ev   !== null ? $clamp(($ev   - $ev50) / max(1, $ev90 - $ev50),  0, 1) : null;
            $dN    = $dist !== null ? $clamp(($dist - $d50)  / max(1, $d90  - $d50),   0, 1) : null;
            if ($evN !== null && $dN !== null) $powerScores[] = ($evN * 0.65 + $dN * 0.35) * 100;
            elseif ($evN !== null)             $powerScores[] = $evN * 100;
            elseif ($dN  !== null)             $powerScores[] = $dN  * 100;
            else                               $powerScores[] = 50;

            // launch
            $launchScores[] = $launchScoreFn($la, $traj);

            // spray
            if ($spray !== null) $sprayAngles[] = $spray;

            if ($ev   !== null) $evVals[]   = $ev;
            if ($dist !== null) $distVals[] = $dist;

            if ($la !== null && $la >= 10 && $la <= 25) $sweetCount++;
            if (strtoupper((string) $traj) === 'LD') $ldCount++;
        }

        $N = count($powerScores);
        if ($N < 3) return [];

        $powerScore  = array_sum($powerScores)  / $N;
        $launchScore = array_sum($launchScores) / $N;

        // approach: spray-angle entropy
        $pull = $middle = $oppo = 0;
        foreach ($sprayAngles as $a) {
            if ($a < -15)      $pull++;
            elseif ($a > 15)   $oppo++;
            else               $middle++;
        }
        $sprayTotal   = $pull + $middle + $oppo;
        $approachScore = 65;
        if ($sprayTotal > 0) {
            $probs = array_filter([$pull / $sprayTotal, $middle / $sprayTotal, $oppo / $sprayTotal]);
            $entropy = -array_sum(array_map(fn($p) => $p * log($p), $probs));
            $approachScore = $clamp(($entropy / log(3)) * 100, 0, 100);
        }

        $raw         = $powerScore * 0.45 + $launchScore * 0.40 + $approachScore * 0.15;
        $reliability = $clamp(sqrt($N / 30), 0.6, 1.0);
        $fcs         = round($clamp(50 + (($raw - 50) * $reliability), 0, 100), 1);

        return [
            'fcs'          => $fcs,
            'total'        => $N,
            'powerScore'   => round($powerScore,  1),
            'launchScore'  => round($launchScore, 1),
            'approachScore'=> round($approachScore, 1),
            'reliability'  => round($reliability, 2),
            'avgEV'        => count($evVals)   ? round(array_sum($evVals)   / count($evVals),   1) : null,
            'maxEV'        => count($evVals)   ? round(max($evVals), 1)   : null,
            'avgDist'      => count($distVals) ? round(array_sum($distVals) / count($distVals), 1) : null,
            'sweetSpotPct' => $N > 0 ? round(($sweetCount / $N) * 100, 1) : 0,
            'ldPct'        => $N > 0 ? round(($ldCount    / $N) * 100, 1) : 0,
            'pullPct'      => $sprayTotal > 0 ? round(($pull   / $sprayTotal) * 100, 1) : null,
            'middlePct'    => $sprayTotal > 0 ? round(($middle / $sprayTotal) * 100, 1) : null,
            'oppoPct'      => $sprayTotal > 0 ? round(($oppo   / $sprayTotal) * 100, 1) : null,
        ];
    }

}
