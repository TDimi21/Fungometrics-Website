<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\{DailyPlan, DailyPlanAssignment, DailyPlanProgress};
use Illuminate\Http\Exceptions\HttpResponseException;

/** Explicit decisions only. Call inside the publication transaction with the player locked. */
final class PlannerConflictResolutionService
{
    public function resolve(string $teamId, string $playerId, string $date, array $buckets, array $exclude, array $decisions): array
    {
        $conflicts = app(PlannerConflictService::class)->check($teamId, $playerId, $date, $buckets, $exclude);
        $replaced = [];
        foreach ($conflicts as $conflict) {
            $decision = collect($decisions)->first(fn($d) => ($d['player_id'] ?? '') === $playerId && ($d['plan_id'] ?? '') === $conflict['plan_id']);
            if (!$decision) throw new HttpResponseException(response()->json(['message'=>'SESSION CONFLICT', 'conflicts'=>$conflicts], 409));
            $old = DailyPlan::lockForUpdate()->findOrFail($conflict['plan_id']);
            abort_unless((int)($decision['version'] ?? -1) === $old->version, 409, 'The conflicting workout changed. Review it again.');
            abort_unless((int)($decision['assignment_version']??0)===(int)($conflict['assignment_version']??0),409,'The player day changed. Review the conflict again.');
            $action = $decision['action'] ?? '';
            if ($action === 'keep_existing') return ['skip'=>true, 'date'=>$date, 'buckets'=>$buckets, 'replaced'=>[]];
            if ($action === 'move') {
                $next = $decision['date'] ?? '';
                validator(['date'=>$next], ['date'=>'required|date_format:Y-m-d'])->validate();
                abort_if($next === $date, 422, 'Choose a different day.');
                $nextConflicts = app(PlannerConflictService::class)->check($teamId, $playerId, $next, $buckets, $exclude);
                if ($nextConflicts) throw new HttpResponseException(response()->json(['message'=>'SESSION CONFLICT','conflicts'=>$nextConflicts],409));
                return ['skip'=>false,'date'=>$next,'buckets'=>$buckets,'replaced'=>[]];
            }
            abort_unless(in_array($action,['replace','merge'],true),422,'Choose a conflict resolution.');
            abort_if(DailyPlanProgress::where('plan_id',$old->id)->where('user_id',$playerId)->exists()
                || \Illuminate\Support\Facades\DB::table('workout_session_links')->where('plan_id',$old->id)->where('user_id',$playerId)->exists(),409,'Recorded work cannot be replaced or merged. Keep it or move the new workout.');
            if ($action === 'merge') $buckets = $this->merge($old->bucketsFor($playerId),$buckets);
            $replaced[]=$old->id;
        }
        return ['skip'=>false,'date'=>$date,'buckets'=>$buckets,'replaced'=>$replaced];
    }

    private function merge(array $existing, array $incoming): array
    {
        $result=[];
        $launchItems=[];
        foreach(array_merge($existing,$incoming) as $bucket) {
            $type=$bucket['type']??'custom';
            $result[$type] ??= array_merge($bucket,['items'=>[],'source_programs'=>[]]);
            if(isset($bucket['program_source']))$result[$type]['source_programs'][]=$bucket['program_source'];
            foreach($bucket['items']??[] as $item) {
                $definition=app(LinkedSessionRegistry::class)->item($item,$type);
                $sessionType=$definition['linked_session']['type']??null;
                if($sessionType && isset($launchItems[$sessionType])) {
                    [$targetType,$index]=$launchItems[$sessionType];
                    // One canonical launcher; retain both exact prescriptions for the coach/player.
                    $target=&$result[$targetType]['items'][$index];
                    $target['merged_prescriptions'] ??= [$target];
                    $target['merged_prescriptions'][]=$item;
                    unset($target);
                } else {
                    if($sessionType)$launchItems[$sessionType]=[$type,count($result[$type]['items'])];
                    $result[$type]['items'][]=$item;
                }
            }
        }
        foreach($result as &$bucket) $bucket['source_programs']=array_values(array_unique($bucket['source_programs'],SORT_REGULAR));
        return array_values($result);
    }
}
