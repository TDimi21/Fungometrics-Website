<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BaseballDictionarySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $domains = ['hitting' => 'Hitting','pitching' => 'Pitching','throwing' => 'Throwing','defense' => 'Defense','strength' => 'Strength','mobility' => 'Mobility','speed_agility' => 'Speed and Agility','body_composition' => 'Body Composition','recovery' => 'Recovery','vision' => 'Vision','mental_performance' => 'Mental Performance','assessment' => 'Assessment','session_context' => 'Session Context','game_outcome' => 'Game Outcome'];
        foreach ($domains as $sort => $name) {
            $key = is_int($sort) ? Str::snake($name) : $sort;
            DB::table('baseball_domains')->updateOrInsert(['key' => $key], ['id' => $this->existingId('baseball_domains', 'key', $key),'name' => $name,'sort_order' => array_search($key, array_keys($domains), true) + 1,'is_active' => true,'created_at' => $now,'updated_at' => $now]);
        }
        $units = [
            'mph' => ['Miles per hour','mph','velocity','imperial'],'deg' => ['Degrees','°','angle','neutral'],'ft' => ['Feet','ft','distance','imperial'],
            'in' => ['Inches','in','distance','imperial'],'rpm' => ['Revolutions per minute','rpm','rotation','neutral'],'sec' => ['Seconds','s','time','neutral'],
            'lbs' => ['Pounds','lb','mass','imperial'],'oz' => ['Ounces','oz','mass','imperial'],'count' => ['Count','#','count','neutral'],
            'score_0_10' => ['Score 0–10','score','score','neutral'],'hours' => ['Hours','hr','time','neutral'],
            'score_1_5' => ['Score 1–5','score','score','neutral'],'kph' => ['Kilometers per hour','kph','velocity','metric'],
            'm' => ['Meters','m','distance','metric'],'cm' => ['Centimeters','cm','distance','metric'],'kg' => ['Kilograms','kg','mass','metric'],
        ];
        foreach ($units as $key => $unit) {
            DB::table('unit_definitions')->updateOrInsert(['key' => $key], ['id' => $this->existingId('unit_definitions', 'key', $key),'display_name' => $unit[0],'symbol' => $unit[1],'measurement_family' => $unit[2],'system' => $unit[3],'created_at' => $now,'updated_at' => $now]);
        }
        $concepts = [
            ['hitting.exit_velocity','hitting','Exit Velocity','Speed of the batted ball immediately after contact.','numeric','mph',0,130],
            ['hitting.launch_angle','hitting','Launch Angle','Vertical angle of the batted ball after contact.','numeric','deg',-90,90],
            ['hitting.spray_angle','hitting','Spray Angle','Horizontal batted-ball direction using the declared source convention.','numeric','deg',null,null],
            ['hitting.measured_carry_distance','hitting','Measured Carry Distance','Measured airborne carry distance.','numeric','ft',0,null],
            ['hitting.projected_distance','hitting','Projected Distance','Platform-projected total batted-ball distance.','numeric','ft',0,null],
            ['hitting.last_tracked_distance','hitting','Last Tracked Distance','Distance at the final tracked point.','numeric','ft',0,null],
            ['hitting.hang_time','hitting','Hang Time','Elapsed airborne time.','numeric','sec',0,null],
            ['hitting.maximum_height','hitting','Maximum Height','Maximum batted-ball height.','numeric','ft',0,null],
            ['hitting.ball_spin_rate','hitting','Batted Ball Spin Rate','Batted-ball rotational rate.','numeric','rpm',0,null],
            ['hitting.ball_spin_axis','hitting','Batted Ball Spin Axis','Batted-ball spin-axis angle under the declared convention.','numeric','deg',null,null],
            ['hitting.contact_quality','hitting','Contact Quality','Observed quality-of-contact classification.','text',null,null,null],
            ['hitting.trajectory','hitting','Hit Trajectory','Batted-ball trajectory classification.','text',null,null,null],
            ['hitting.field_direction','hitting','Field Direction','Pull, middle, or opposite-field classification.','text',null,null,null],
            ['hitting.bat_speed','hitting','Bat Speed','Measured bat speed under the declared device definition.','numeric','mph',0,null],
            ['pitching.release_velocity','pitching','Release Velocity','Ball velocity at or near release under the declared source method.','numeric','mph',0,110],
            ['pitching.tagged_pitch_type','pitching','Tagged Pitch Type','Human-tagged pitch classification.','text',null,null,null],
            ['pitching.automatic_pitch_type','pitching','Automatic Pitch Type','Platform-generated pitch classification.','text',null,null,null],
            ['pitching.spin_rate','pitching','Pitch Spin Rate','Pitch rotational rate.','numeric','rpm',0,null],
            ['pitching.spin_axis','pitching','Pitch Spin Axis','Pitch spin-axis angle under the declared source convention.','numeric','deg',null,null],
            ['pitching.induced_vertical_break','pitching','Induced Vertical Break','Vertical movement relative to gravity-only trajectory.','numeric','in',null,null],
            ['pitching.horizontal_break','pitching','Horizontal Break','Horizontal pitch movement under the declared source convention.','numeric','in',null,null],
            ['pitching.extension','pitching','Extension','Release extension toward home plate.','numeric','ft',0,null],
            ['pitching.release_height','pitching','Release Height','Vertical release position.','numeric','ft',0,null],
            ['pitching.release_side','pitching','Release Side','Horizontal release position under the declared convention.','numeric','ft',null,null],
            ['pitching.plate_location_height','pitching','Plate Location Height','Vertical plate-crossing location.','numeric','ft',null,null],
            ['pitching.plate_location_side','pitching','Plate Location Side','Horizontal plate-crossing location under the declared convention.','numeric','ft',null,null],
            ['pitching.strike_result','pitching','Strike Result','Whether the pitch was recorded as a strike.','boolean',null,null,null],
            ['throwing.weighted_ball_velocity','throwing','Weighted-Ball Velocity','Velocity of a weighted-ball throw.','numeric','mph',0,110],
            ['throwing.ball_weight','throwing','Ball Weight','Weight of the thrown ball.','numeric','oz',0,null],
            ['throwing.long_toss_distance','throwing','Long-Toss Distance','Throwing distance during long toss.','numeric','ft',0,null],
            ['throwing.long_toss_hops','throwing','Long-Toss Hops','Ground hops before the target.','integer','count',0,null],
            ['strength.bench_press','strength','Bench Press','Bench-press load.','numeric','lbs',0,null], ['strength.front_squat','strength','Front Squat','Front-squat load.','numeric','lbs',0,null],
            ['strength.back_squat','strength','Back Squat','Back-squat load.','numeric','lbs',0,null], ['strength.deadlift','strength','Deadlift','Deadlift load.','numeric','lbs',0,null],
            ['strength.power_clean','strength','Power Clean','Power-clean load.','numeric','lbs',0,null],
            ['speed_agility.sprint_10yd','speed_agility','10-Yard Sprint','Elapsed time over ten yards.','numeric','sec',0,null],
            ['speed_agility.sprint_40yd','speed_agility','40-Yard Sprint','Elapsed time over forty yards.','numeric','sec',0,null],
            ['speed_agility.sprint_60yd','speed_agility','60-Yard Sprint','Elapsed time over sixty yards.','numeric','sec',0,null],
            ['body_composition.body_weight','body_composition','Body Weight','Measured body weight.','numeric','lbs',0,null],
            ['recovery.sleep_duration','recovery','Sleep Duration','Reported sleep duration.','numeric','hours',0,24],
            ['recovery.sleep_quality','recovery','Sleep Quality','Reported sleep quality on a 1–5 scale.','integer','score_1_5',1,5],
            ['mobility.hip','mobility','Hip Mobility','Coach-rated hip mobility.','integer','score_0_10',0,10],
            ['mobility.shoulder','mobility','Shoulder Mobility','Coach-rated shoulder mobility.','integer','score_0_10',0,10],
            ['mobility.ankle','mobility','Ankle Mobility','Coach-rated ankle mobility.','integer','score_0_10',0,10],
            ['strength.vertical_jump','strength','Vertical Jump','Measured vertical-jump height.','numeric','in',0,null],
            ['strength.broad_jump','strength','Broad Jump','Measured standing broad-jump distance.','numeric','in',0,null],
            ['strength.hand_grip','strength','Hand Grip Strength','Measured hand-grip force.','numeric','lbs',0,null],
            ['session_context.player_identity','session_context','Player Identity','Source participant identity used for player mapping.','text',null,null,null],
            ['session_context.event_date','session_context','Event Date','Source date associated with an event or session.','date',null,null,null],
            ['session_context.facility','session_context','Facility','Source facility or venue name.','text',null,null,null],
            ['session_context.system','session_context','Measurement System','Source measurement system identifier.','text',null,null,null],
            ['session_context.event_identifier','session_context','Event Identifier','Stable source event identifier.','text',null,null,null],
        ];
        foreach ($concepts as $c) {
            $domainId = DB::table('baseball_domains')->where('key', $c[1])->value('id');
            DB::table('baseball_concepts')->updateOrInsert(['canonical_key' => $c[0]], ['id' => $this->existingId('baseball_concepts', 'canonical_key', $c[0]),'domain_id' => $domainId,'display_name' => $c[2],'definition' => $c[3],'data_type' => $c[4],'canonical_unit_key' => $c[5],'valid_min' => $c[6],'valid_max' => $c[7],'validation_severity' => 'warning','research_eligible' => false,'profile_visible' => true,'status' => 'active','created_at' => $now,'updated_at' => $now]);
        }
        DB::table('platform_definitions')->updateOrInsert(['key' => 'trackman'], ['id' => $this->existingId('platform_definitions', 'key', 'trackman'),'name' => 'TrackMan','description' => 'TrackMan CSV data exports.','is_active' => true,'created_at' => $now,'updated_at' => $now]);
        $platformId = DB::table('platform_definitions')->where('key', 'trackman')->value('id');
        $conversions = [['kph','mph','kph_to_mph'],['mph','kph','mph_to_kph'],['m','ft','m_to_ft'],['ft','m','ft_to_m'],['cm','in','cm_to_in'],['in','cm','in_to_cm'],['kg','lbs','kg_to_lbs'],['lbs','kg','lbs_to_kg']];
        foreach($conversions as [$source,$target,$key]) {
            DB::table('unit_conversions')->updateOrInsert(['source_unit_id' => DB::table('unit_definitions')->where('key', $source)->value('id'),'target_unit_id' => DB::table('unit_definitions')->where('key', $target)->value('id')], ['id' => $this->existingId('unit_conversions', 'transformation_key', $key),'transformation_key' => $key,'is_active' => true,'created_at' => $now,'updated_at' => $now]);
        }
        $aliases = ['ExitSpeed' => 'hitting.exit_velocity','Angle' => 'hitting.launch_angle','Direction' => 'hitting.spray_angle','Distance' => 'hitting.projected_distance','LastTrackedDistance' => 'hitting.last_tracked_distance','HangTime' => 'hitting.hang_time','MaxHeight' => 'hitting.maximum_height','HitSpinRate' => 'hitting.ball_spin_rate','HitSpinAxis' => 'hitting.ball_spin_axis','TaggedHitType' => 'hitting.trajectory','AutoHitType' => 'hitting.trajectory','RelSpeed' => 'pitching.release_velocity','TaggedPitchType' => 'pitching.tagged_pitch_type','AutoPitchType' => 'pitching.automatic_pitch_type','SpinRate' => 'pitching.spin_rate','SpinAxis' => 'pitching.spin_axis','InducedVertBreak' => 'pitching.induced_vertical_break','HorzBreak' => 'pitching.horizontal_break','Extension' => 'pitching.extension','ReleaseHeight' => 'pitching.release_height','ReleaseSide' => 'pitching.release_side','PlateLocHeight' => 'pitching.plate_location_height','PlateLocSide' => 'pitching.plate_location_side'];
        $aliases = ['Batter' => 'session_context.player_identity','Pitcher' => 'session_context.player_identity','Date' => 'session_context.event_date','Stadium' => 'session_context.facility','System' => 'session_context.system','PitchUID' => 'session_context.event_identifier'] + $aliases;
        $aliases += [
            'BatterName' => 'session_context.player_identity','Batter Name' => 'session_context.player_identity','Hitter' => 'session_context.player_identity',
            'PitcherName' => 'session_context.player_identity','Pitcher Name' => 'session_context.player_identity','GameDate' => 'session_context.event_date','SessionDate' => 'session_context.event_date',
            'Venue' => 'session_context.facility','Facility' => 'session_context.facility','TrackingSystem' => 'session_context.system','RadarSystem' => 'session_context.system',
            'PitchNo' => 'session_context.event_identifier','PitchNumber' => 'session_context.event_identifier','EventId' => 'session_context.event_identifier',
            'ExitVelocity' => 'hitting.exit_velocity','Exit Velo' => 'hitting.exit_velocity','ExitSpeedMPH' => 'hitting.exit_velocity',
            'LaunchAngle' => 'hitting.launch_angle','Launch Angle' => 'hitting.launch_angle','SprayAngle' => 'hitting.spray_angle','Spray Angle' => 'hitting.spray_angle',
            'CarryDistance' => 'hitting.projected_distance','HitDistance' => 'hitting.projected_distance','Last Tracked Distance' => 'hitting.last_tracked_distance',
            'Hit Spin Rate' => 'hitting.ball_spin_rate','Hit Spin Axis' => 'hitting.ball_spin_axis','Hang Time' => 'hitting.hang_time','MaximumHeight' => 'hitting.maximum_height','Max Height' => 'hitting.maximum_height',
            'Tagged Hit Type' => 'hitting.trajectory','AutomaticHitType' => 'hitting.trajectory','ReleaseSpeed' => 'pitching.release_velocity','PitchVelocity' => 'pitching.release_velocity',
            'Tagged Pitch Type' => 'pitching.tagged_pitch_type','AutomaticPitchType' => 'pitching.automatic_pitch_type','PitchSpinRate' => 'pitching.spin_rate',
            'IVB' => 'pitching.induced_vertical_break','HorizontalBreak' => 'pitching.horizontal_break','ReleaseExtension' => 'pitching.extension',
        ];
        foreach($aliases as $alias => $key) {
            $conceptId = DB::table('baseball_concepts')->where('canonical_key', $key)->value('id');
            $normalized = Str::lower(preg_replace('/[^a-z0-9]/i', '', $alias));
            DB::table('baseball_concept_aliases')->updateOrInsert(['platform_definition_id' => $platformId,'normalized_alias' => $normalized], ['id' => $this->existingId('baseball_concept_aliases', 'normalized_alias', $normalized, 'platform_definition_id', $platformId),'baseball_concept_id' => $conceptId,'alias' => $alias,'relationship_type' => 'exact_equivalent','confidence' => 100,'is_official' => true,'status' => 'active','created_at' => $now,'updated_at' => $now]);
        }
    }

    private function existingId(string $table, string $column, string $value, ?string $scopeColumn = null, mixed $scopeValue = null): string
    {
        $q = DB::table($table)->where($column, $value);
        if($scopeColumn) {
            $q->where($scopeColumn, $scopeValue);
        }
        return (string)($q->value('id') ?: Str::uuid());
    }
}
