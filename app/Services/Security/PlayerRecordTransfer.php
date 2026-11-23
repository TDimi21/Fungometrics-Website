<?php
namespace App\Services\Security;

use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/** Explicit inventory: transfer subjects, never credentials, consent or billing identities. */
class PlayerRecordTransfer
{
    public const REFERENCES = [
        'batting_practice_results'=>['batter_id'], 'bullpen_practice_results'=>['pitcher_id'],
        'cage_practice_results'=>['user_id'], 'long_toss_practices'=>['user_id'],
        'exit_velocity_practices'=>['user_id'], 'weight_ball_practices'=>['user_id'],
        'practice_line_ups'=>['user_id'], 'practices'=>['user_id'], 'scripted_bp_swings'=>['batter_id'],
        'player_fitnesses'=>['user_id'], 'player_assessments'=>['user_id'],
        'athletic_performance_scores'=>['player_id'], 'assessment_drafts'=>['user_id'],
        'daily_plan_assignments'=>['user_id'], 'daily_plan_progress'=>['user_id'],
        'workout_session_links'=>['user_id'], 'benchmark_collection_tasks'=>['assigned_to_player_id'],
        'weekly_report_notes'=>['player_id'], 'arm_care_sessions'=>['user_id'],
        'player_daily_readiness'=>['user_id'], 'long_toss_plans'=>['player_id'],
        'long_toss_guides'=>['player_id'], 'player_videos'=>['player_id'],
        'external_player_mappings'=>['fmtrx_player_id'], 'translation_snapshots'=>['player_id'],
        'external_sessions'=>['player_id'], 'canonical_events'=>['player_id'],
        'free_assessment_players'=>['player_id'], 'free_assessment_results'=>['player_id'],
        'player_positions'=>['player_id'],
    ];
    public const JSON_REFERENCES = [
        'practices'=>['script_plan','liveab_config','liveab_state'],
        'liveab_web_events'=>['before_state','after_state'],
        'live_a_b_practice_results'=>['runners_before','runners_after'],
        'player_groups'=>['member_ids'], 'daily_plans'=>['buckets','settings'],
        'daily_plan_assignments'=>['prescription_override'],
        'long_toss_plans'=>['settings'], 'long_toss_guides'=>['snapshot','review'],
    ];
    public function has(string $table, string $column): bool {
        return Schema::hasTable($table) && Schema::hasColumn($table,$column);
    }
    public function preview(string $source): array {
        $counts=[];
        foreach (self::REFERENCES as $table=>$columns) foreach ($columns as $column) {
            if ($this->has($table,$column)) {
                $n=DB::table($table)->where($column,$source)->count();
                if ($n) $counts[$table]=($counts[$table]??0)+$n;
            }
        }
        return $counts;
    }
    /** Replace exact JSON identifiers, including map keys; never text substrings. */
    public function replace(mixed $value, string $source, string $target): mixed {
        if (is_string($value)) return $value===$source ? $target : $value;
        if (!is_array($value)) return $value;
        if (array_key_exists($source,$value) && array_key_exists($target,$value)) {
            throw ValidationException::withMessages(['connection'=>'Both profiles have settings in the same session. Contact support to reconcile them before connecting.']);
        }
        $result=[];
        foreach ($value as $key=>$item) $result[$key===$source?$target:$key]=$this->replace($item,$source,$target);
        // Preserve array order and duplicate values (for example inning scores).
        return $result;
    }
    public function transfer(string $source, string $target): array {
        $summary=$this->preview($source);
        // Collisions are deliberately not discarded: a DB uniqueness conflict rolls back everything.
        foreach (self::REFERENCES as $table=>$columns) foreach ($columns as $column) {
            if ($this->has($table,$column)) DB::table($table)->where($column,$source)->update([$column=>$target]);
        }
        foreach (self::JSON_REFERENCES as $table=>$columns) foreach ($columns as $column) {
            if (!$this->has($table,$column)) continue;
            $key=Schema::hasColumn($table,'id')?'id':null;
            if (!$key) continue;
            foreach (DB::table($table)->where($column,'like','%'.$source.'%')->lockForUpdate()->get([$key,$column]) as $row) {
                $json=json_decode($row->$column,true,512,JSON_THROW_ON_ERROR);
                $changed=$this->replace($json,$source,$target);
                DB::table($table)->where($key,$row->$key)->update([$column=>json_encode($changed,JSON_THROW_ON_ERROR)]);
            }
        }
        return $summary;
    }
}
