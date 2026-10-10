<?php
namespace App\Services\Planner;

final class WorkoutSkillResults
{
    public function validate(array $actual, string $bucket): void
    {
        $p = $actual['performance'] ?? [];
        if (!$p) return;
        abort_unless(in_array($bucket, ['hitting','pitching','throwing','defense'], true), 422, 'Skill results require a hitting, throwing or defensive section.');
        abort_if(!empty($actual['session_id']), 422, 'Use recorded session results or manual results, not both. Clear manual results before linking a session.');
        $keys = $bucket === 'defense' ? ['attempts','successful_reps','errors'] : ($bucket === 'hitting' ? ['swings','contacts','hard_contacts','exit_velocity_mph','measurement_source'] : ['throws','pitches','strikes','velocity_mph','measurement_source']);
        validator(['performance'=>$p], ['performance'=>'array:'.implode(',', $keys)])->validate();
        foreach (['swings','contacts','hard_contacts','throws','pitches','strikes','attempts','successful_reps','errors'] as $key) {
            validator($p, [$key=>'nullable|integer|min:0|max:10000'])->validate();
        }
        validator($p, ['exit_velocity_mph'=>'nullable|numeric|gt:0|max:200','velocity_mph'=>'nullable|numeric|gt:0|max:200','measurement_source'=>'nullable|string|max:200'])->validate();
        foreach ([['contacts','swings'],['hard_contacts','contacts'],['strikes','pitches'],['pitches','throws'],['successful_reps','attempts'],['errors','attempts']] as [$part,$total]) {
            if (isset($p[$part])) abort_unless(isset($p[$total]) && $p[$part] <= $p[$total], 422, "$part requires $total and cannot exceed it.");
        }
        if (isset($p['attempts'])) abort_unless(($p['successful_reps'] ?? 0) + ($p['errors'] ?? 0) <= $p['attempts'], 422, 'Successful reps plus errors cannot exceed attempts.');
        if (isset($p['exit_velocity_mph']) || isset($p['velocity_mph'])) abort_unless(trim($p['measurement_source'] ?? '') !== '', 422, 'Include the device or source for a measured velocity.');
    }

    public function summary(array $actual): array
    {
        $p = $actual['performance'] ?? [];
        $measured = array_filter($p, fn($v, $k) => $k !== 'measurement_source' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
        $rate = fn($part,$total) => isset($p[$part],$p[$total]) && $p[$total] > 0 ? round(100*$p[$part]/$p[$total],1) : null;
        return ['result_status'=>!empty($actual['session_id']) ? 'linked_session' : ($measured ? 'recorded' : 'not_recorded'),
            'session_id'=>$actual['session_id'] ?? null, 'recorded'=>$p,
            'contact_pct'=>$rate('contacts','swings'), 'hard_contact_pct'=>$rate('hard_contacts','contacts'), 'strike_pct'=>$rate('strikes','pitches'), 'defensive_success_pct'=>$rate('successful_reps','attempts')];
    }
}
