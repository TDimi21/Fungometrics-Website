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
            'percent' => ['Percent','%','ratio','neutral'],
            'g_force' => ['G-force','g','acceleration','neutral'],'kw' => ['Kilowatts','kW','power','neutral'],
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
            ['strength.trap_bar_deadlift','strength','Trap-bar Deadlift','Trap-bar deadlift load; not interchangeable with conventional deadlift.','numeric','lbs',0,null],
            ['strength.grip_left','strength','Left Grip Strength','Left-hand dynamometer grip force.','numeric','lbs',0,null],
            ['strength.grip_right','strength','Right Grip Strength','Right-hand dynamometer grip force.','numeric','lbs',0,null],
            ['strength.med_ball_rotational_throw','strength','Medicine-ball Rotational Throw','Rotational medicine-ball throw distance with governed ball weight and protocol.','numeric','ft',0,null],
            ['strength.plank_hold','strength','Plank Hold','Plank hold duration under a declared protocol.','numeric','sec',0,null],
            ['strength.med_ball_weight','strength','Medicine-ball Weight','Medicine-ball weight used for the recorded throw.','numeric','lbs',0,null],
            ['strength.med_ball_protocol','strength','Medicine-ball Protocol','Protocol used for the rotational throw.','text',null,null,null],
            ['strength.plank_protocol','strength','Plank Protocol','Protocol used for the timed plank.','text',null,null,null],
            ['strength.push_up_protocol','strength','Push-up Protocol','Timed or untimed push-up protocol.','text',null,null,null],
            ['strength.grip_device','strength','Grip Device','Device used for grip testing.','text',null,null,null],
            ['strength.grip_protocol','strength','Grip Protocol','Protocol used for grip testing.','text',null,null,null],
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
            ['session_context.event_number','session_context','Event Number','Sequential event number supplied by the source platform.','integer','count',0,null],
            ['game_outcome.plate_appearance_number','game_outcome','Plate Appearance Number','Source plate-appearance sequence number.','integer','count',0,null],
            ['session_context.event_timestamp','session_context','Event Timestamp','Source timestamp associated with an event.','datetime',null,null,null],
            ['session_context.elapsed_time','session_context','Session Elapsed Time','Elapsed time from the start of the source session.','text',null,null,null],
            ['hitting.inbound_pitch_velocity','hitting','Inbound Pitch Velocity','Pitch velocity observed by a hitting system at its declared measurement point.','numeric','mph',0,110],
            ['pitching.zone_number','pitching','Strike Zone Number','Source-defined strike-zone cell or region number.','integer','count',null,null],
            ['hitting.inbound_pitch_type','hitting','Inbound Pitch Type','Source classification of the pitch delivered to the hitter.','text',null,null,null],
            ['game_outcome.simulated_play_result','game_outcome','Simulated Play Result','Platform-simulated outcome; not an official real-game result.','text',null,null,null],
            ['hitting.trajectory_automatic','hitting','Automatic Hit Trajectory','Platform-generated batted-ball trajectory classification.','text',null,null,null],
            ['hitting.hand_speed','hitting','Hand Speed','Source-measured hand speed during the swing.','numeric','mph',0,null],
            ['hitting.bat_velocity','hitting','Bat Velocity','Source-measured bat velocity; distinct from hand speed.','numeric','mph',0,null],
            ['hitting.trigger_to_impact','hitting','Trigger to Impact','Elapsed time from swing trigger to impact.','numeric','sec',0,null],
            ['hitting.attack_angle','hitting','Attack Angle','Vertical direction of bat travel at impact.','numeric','deg',null,null],
            ['hitting.impact_momentum','hitting','Impact Momentum','Source-defined impact momentum measurement.','numeric',null,0,null],
            ['session_context.strike_zone_bottom','session_context','Strike Zone Bottom','Configured bottom of the source session strike zone.','numeric','in',null,null],
            ['session_context.strike_zone_top','session_context','Strike Zone Top','Configured top of the source session strike zone.','numeric','in',null,null],
            ['session_context.strike_zone_width','session_context','Strike Zone Width','Configured width of the source session strike zone.','numeric','in',0,null],
            ['pitching.location_vertical_distance','pitching','Location Vertical Distance','Source-specific vertical pitch-location distance with an unverified origin and sign convention.','numeric','in',null,null],
            ['pitching.location_horizontal_distance','pitching','Location Horizontal Distance','Source-specific horizontal pitch-location distance with an unverified origin and sign convention.','numeric','in',null,null],
            ['hitting.point_of_impact_x','hitting','Point of Impact X','Source-specific X coordinate of point of impact.','numeric','in',null,null],
            ['hitting.point_of_impact_y','hitting','Point of Impact Y','Source-specific Y coordinate of point of impact.','numeric','in',null,null],
            ['hitting.point_of_impact_z','hitting','Point of Impact Z','Source-specific Z coordinate of point of impact.','numeric','in',null,null],
            ['hitting.bat_material','hitting','Bat Material','Source-reported bat material.','text',null,null,null],
            ['hitting.inbound_pitch_angle','hitting','Inbound Pitch Angle','Source-measured inbound pitch angle.','numeric','deg',null,null],
            ['hitting.batter_side','hitting','Batter Side','Side from which the hitter batted.','text',null,null,null],
            ['session_context.competition_level','session_context','Competition Level','Source-reported competition or player level.','text',null,null,null],
            ['session_context.opposing_player_identity','session_context','Opposing Player Identity','Optional opposing-player identity from the source event.','text',null,null,null],
            ['session_context.event_note','session_context','Event Note','Source note or tag associated with an event.','text',null,null,null],
            ['hitting.hittrax_points','hitting','HitTrax Points','Proprietary HitTrax platform score.','numeric','count',0,null],
            ['session_context.event_time','session_context','Event Time','Displayed source time associated with an event.','time',null,null,null],
            ['pitching.true_spin_rate','pitching','True Spin Rate','Component of total spin contributing to pitch movement.','numeric','rpm',0,null],
            ['pitching.spin_efficiency','pitching','Spin Efficiency','Percentage of total spin contributing to movement.','numeric','percent',0,100],
            ['pitching.spin_direction_clock','pitching','Spin Direction Clock','Clock-face representation of pitch spin direction.','text',null,null,null],
            ['pitching.vertical_break','pitching','Vertical Break','Observed vertical pitch break under the source definition; distinct from induced vertical break.','numeric','in',null,null],
            ['pitching.gyro_degree','pitching','Gyro Degree','Source-measured gyro degree.','numeric','deg',null,null],
            ['pitching.release_angle','pitching','Release Angle','Source-specific release-angle measurement whose plane must be confirmed.','numeric','deg',null,null],
            ['pitching.horizontal_release_angle','pitching','Horizontal Release Angle','Horizontal pitch angle at release.','numeric','deg',null,null],
            ['hitting.bat_equipment','hitting','Bat Equipment','Exact source description of the bat or training implement.','text',null,null,null],
            ['hitting.swing_details','hitting','Swing Details','Source-provided swing session or drill classification.','text',null,null,null],
            ['hitting.blast_plane_score','hitting','Blast Plane Score','Proprietary Blast Motion plane composite score.','numeric',null,null,null],
            ['hitting.blast_connection_score','hitting','Blast Connection Score','Proprietary Blast Motion connection composite score.','numeric',null,null,null],
            ['hitting.blast_rotation_score','hitting','Blast Rotation Score','Proprietary Blast Motion rotation composite score.','numeric',null,null,null],
            ['hitting.rotational_acceleration','hitting','Rotational Acceleration','Measured rotational acceleration under the declared device definition.','numeric','g_force',0,null],
            ['hitting.on_plane_efficiency','hitting','On-Plane Efficiency','Percentage of the swing during which the bat is on the source-defined swing plane.','numeric','percent',0,100],
            ['hitting.early_connection','hitting','Early Connection','Source-measured connection angle early in the swing.','numeric','deg',null,null],
            ['hitting.connection_at_impact','hitting','Connection at Impact','Source-measured connection angle at impact.','numeric','deg',null,null],
            ['hitting.vertical_bat_angle','hitting','Vertical Bat Angle','Vertical orientation of the bat at impact under the declared convention.','numeric','deg',null,null],
            ['hitting.blast_swing_power','hitting','Blast Swing Power','Proprietary Blast Motion calculated swing-power value.','numeric','kw',0,null],
            ['hitting.time_to_contact','hitting','Time to Contact','Elapsed time to contact under the declared sensor definition.','numeric','sec',0,null],
            ['hitting.peak_hand_speed','hitting','Peak Hand Speed','Peak measured hand speed during the swing.','numeric','mph',0,null],
        ];
        foreach ($concepts as $c) {
            $domainId = DB::table('baseball_domains')->where('key', $c[1])->value('id');
            DB::table('baseball_concepts')->updateOrInsert(['canonical_key' => $c[0]], ['id' => $this->existingId('baseball_concepts', 'canonical_key', $c[0]),'domain_id' => $domainId,'display_name' => $c[2],'definition' => $c[3],'data_type' => $c[4],'canonical_unit_key' => $c[5],'valid_min' => $c[6],'valid_max' => $c[7],'validation_severity' => 'warning','research_eligible' => false,'profile_visible' => true,'status' => 'active','created_at' => $now,'updated_at' => $now]);
        }
        foreach (['session_context.strike_zone_bottom','session_context.strike_zone_top','session_context.strike_zone_width'] as $key) {
            DB::table('baseball_concepts')->where('canonical_key', $key)->update(['profile_visible' => false,'research_eligible' => false]);
        }
        foreach (['pitching.location_vertical_distance','pitching.location_horizontal_distance','hitting.point_of_impact_x','hitting.point_of_impact_y','hitting.point_of_impact_z','hitting.impact_momentum','hitting.hittrax_points'] as $key) {
            DB::table('baseball_concepts')->where('canonical_key', $key)->update([
                'research_eligible' => false,
                'metadata' => json_encode(['source_specific' => true,'source_platform' => 'hittrax','comparison_status' => 'comparable_with_caution']),
            ]);
        }
        DB::table('baseball_concepts')->where('canonical_key', 'hitting.hittrax_points')->update([
            'profile_visible' => false,
            'metadata' => json_encode(['source_specific' => true,'source_platform' => 'hittrax','derived' => true,'comparison_status' => 'source_only']),
        ]);
        DB::table('baseball_concepts')->where('canonical_key', 'pitching.release_angle')->update([
            'research_eligible' => false,
            'metadata' => json_encode(['source_specific' => true,'source_platform' => 'rapsodo','comparison_status' => 'definition_unverified']),
        ]);
        foreach (['hitting.blast_plane_score','hitting.blast_connection_score','hitting.blast_rotation_score','hitting.blast_swing_power'] as $key) {
            DB::table('baseball_concepts')->where('canonical_key', $key)->update([
                'research_eligible' => false,
                'metadata' => json_encode(['source_specific' => true,'source_platform' => 'blast-motion','derived' => true,'comparison_status' => 'source_only']),
            ]);
        }
        DB::table('platform_definitions')->updateOrInsert(['key' => 'trackman'], ['id' => $this->existingId('platform_definitions', 'key', 'trackman'),'name' => 'TrackMan','description' => 'TrackMan CSV data exports.','is_active' => true,'created_at' => $now,'updated_at' => $now]);
        $platformId = DB::table('platform_definitions')->where('key', 'trackman')->value('id');
        $conversions = [['kph','mph','kph_to_mph'],['mph','kph','mph_to_kph'],['m','ft','m_to_ft'],['ft','m','ft_to_m'],['cm','in','cm_to_in'],['in','cm','in_to_cm'],['kg','lbs','kg_to_lbs'],['lbs','kg','lbs_to_kg']];
        foreach($conversions as [$source,$target,$key]) {
            DB::table('unit_conversions')->updateOrInsert(['source_unit_id' => DB::table('unit_definitions')->where('key', $source)->value('id'),'target_unit_id' => DB::table('unit_definitions')->where('key', $target)->value('id')], ['id' => $this->existingId('unit_conversions', 'transformation_key', $key),'transformation_key' => $key,'is_active' => true,'created_at' => $now,'updated_at' => $now]);
        }
        $aliases = ['ExitSpeed' => 'hitting.exit_velocity','Angle' => 'hitting.launch_angle','Direction' => 'hitting.spray_angle','Distance' => 'hitting.projected_distance','LastTrackedDistance' => 'hitting.last_tracked_distance','HangTime' => 'hitting.hang_time','MaxHeight' => 'hitting.maximum_height','HitSpinRate' => 'hitting.ball_spin_rate','HitSpinAxis' => 'hitting.ball_spin_axis','TaggedHitType' => 'hitting.trajectory','AutoHitType' => 'hitting.trajectory_automatic','RelSpeed' => 'pitching.release_velocity','TaggedPitchType' => 'pitching.tagged_pitch_type','AutoPitchType' => 'pitching.automatic_pitch_type','SpinRate' => 'pitching.spin_rate','SpinAxis' => 'pitching.spin_axis','InducedVertBreak' => 'pitching.induced_vertical_break','HorzBreak' => 'pitching.horizontal_break','Extension' => 'pitching.extension','ReleaseHeight' => 'pitching.release_height','ReleaseSide' => 'pitching.release_side','PlateLocHeight' => 'pitching.plate_location_height','PlateLocSide' => 'pitching.plate_location_side'];
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
            'Tagged Hit Type' => 'hitting.trajectory','AutomaticHitType' => 'hitting.trajectory_automatic','ReleaseSpeed' => 'pitching.release_velocity','PitchVelocity' => 'pitching.release_velocity',
            'Tagged Pitch Type' => 'pitching.tagged_pitch_type','AutomaticPitchType' => 'pitching.automatic_pitch_type','PitchSpinRate' => 'pitching.spin_rate',
            'IVB' => 'pitching.induced_vertical_break','HorizontalBreak' => 'pitching.horizontal_break','ReleaseExtension' => 'pitching.extension',
        ];
        $trackManUnits = [
            'ExitSpeed' => 'mph','ExitVelocity' => 'mph','Exit Velo' => 'mph','ExitSpeedMPH' => 'mph',
            'Angle' => 'deg','LaunchAngle' => 'deg','Launch Angle' => 'deg','Direction' => 'deg','SprayAngle' => 'deg','Spray Angle' => 'deg',
            'Distance' => 'ft','CarryDistance' => 'ft','HitDistance' => 'ft','LastTrackedDistance' => 'ft','Last Tracked Distance' => 'ft',
            'HangTime' => 'sec','Hang Time' => 'sec','MaxHeight' => 'ft','MaximumHeight' => 'ft','Max Height' => 'ft',
            'HitSpinRate' => 'rpm','Hit Spin Rate' => 'rpm','HitSpinAxis' => 'deg','Hit Spin Axis' => 'deg',
            'RelSpeed' => 'mph','ReleaseSpeed' => 'mph','PitchVelocity' => 'mph','SpinRate' => 'rpm','PitchSpinRate' => 'rpm',
            'SpinAxis' => 'deg','InducedVertBreak' => 'in','IVB' => 'in','HorzBreak' => 'in','HorizontalBreak' => 'in',
            'Extension' => 'ft','ReleaseExtension' => 'ft','ReleaseHeight' => 'ft','ReleaseSide' => 'ft',
            'PlateLocHeight' => 'ft','PlateLocSide' => 'ft',
        ];
        foreach($aliases as $alias => $key) {
            $conceptId = DB::table('baseball_concepts')->where('canonical_key', $key)->value('id');
            $normalized = Str::lower(preg_replace('/[^a-z0-9]/i', '', $alias));
            DB::table('baseball_concept_aliases')->updateOrInsert(['platform_definition_id' => $platformId,'normalized_alias' => $normalized], ['id' => $this->existingId('baseball_concept_aliases', 'normalized_alias', $normalized, 'platform_definition_id', $platformId),'baseball_concept_id' => $conceptId,'alias' => $alias,'relationship_type' => 'exact_equivalent','source_unit_key' => $trackManUnits[$alias] ?? null,'confidence' => 100,'is_official' => true,'status' => 'active','created_at' => $now,'updated_at' => $now]);
        }
        DB::table('platform_definitions')->updateOrInsert(['key' => 'hittrax'], ['id' => $this->existingId('platform_definitions', 'key', 'hittrax'),'name' => 'HitTrax','description' => 'HitTrax hitting-session CSV exports.','is_active' => true,'created_at' => $now,'updated_at' => $now]);
        $hitTraxPlatformId = DB::table('platform_definitions')->where('key', 'hittrax')->value('id');
        $hitTraxAliases = [
            '#' => 'session_context.event_number','AB' => 'game_outcome.plate_appearance_number','Date' => 'session_context.event_timestamp',
            'Time Stamp' => 'session_context.elapsed_time','Pitch' => 'hitting.inbound_pitch_velocity','Strike Zone' => 'pitching.zone_number',
            'P. Type' => 'hitting.inbound_pitch_type','Velo' => 'hitting.exit_velocity','LA' => 'hitting.launch_angle',
            'Dist' => 'hitting.projected_distance','Res' => 'game_outcome.simulated_play_result','Type' => 'hitting.trajectory_automatic',
            'Horiz. Angle' => 'hitting.spray_angle','Pts' => 'hitting.hittrax_points','Hand Speed' => 'hitting.hand_speed',
            'BV' => 'hitting.bat_velocity','Trigger to Impact' => 'hitting.trigger_to_impact','AA' => 'hitting.attack_angle',
            'Impact Momentum' => 'hitting.impact_momentum','Strike Zone Bottom' => 'session_context.strike_zone_bottom',
            'Strike Zone Top' => 'session_context.strike_zone_top','Strike Zone Width' => 'session_context.strike_zone_width',
            'Vertical Distance' => 'pitching.location_vertical_distance','Horizontal Distance' => 'pitching.location_horizontal_distance',
            'POI X' => 'hitting.point_of_impact_x','POI Y' => 'hitting.point_of_impact_y','POI Z' => 'hitting.point_of_impact_z',
            'Bat Material' => 'hitting.bat_material','User' => 'session_context.player_identity','Pitch Angle' => 'hitting.inbound_pitch_angle',
            'Batting' => 'hitting.batter_side','Level' => 'session_context.competition_level',
            'Opposing Player' => 'session_context.opposing_player_identity','Tag' => 'session_context.event_note',
        ];
        $hitTraxUnits = [
            'Pitch' => 'mph','Velo' => 'mph','LA' => 'deg','Dist' => 'ft','Horiz. Angle' => 'deg',
            'Hand Speed' => 'mph','BV' => 'mph','Trigger to Impact' => 'sec','AA' => 'deg',
            'Strike Zone Bottom' => 'in','Strike Zone Top' => 'in','Strike Zone Width' => 'in',
            'Vertical Distance' => 'in','Horizontal Distance' => 'in','Pitch Angle' => 'deg',
        ];
        foreach($hitTraxAliases as $alias => $key) {
            $conceptId = DB::table('baseball_concepts')->where('canonical_key', $key)->value('id');
            $normalized = Str::lower(preg_replace('/[^a-z0-9]/i', '', $alias));
            $relationship = in_array($alias, ['Vertical Distance','Horizontal Distance'], true) ? 'comparable_with_caution' : 'exact_equivalent';
            DB::table('baseball_concept_aliases')->updateOrInsert(
                ['platform_definition_id' => $hitTraxPlatformId,'normalized_alias' => $normalized],
                ['id' => $this->existingId('baseball_concept_aliases', 'normalized_alias', $normalized, 'platform_definition_id', $hitTraxPlatformId),'baseball_concept_id' => $conceptId,'alias' => $alias,'relationship_type' => $relationship,'source_unit_key' => $hitTraxUnits[$alias] ?? null,'confidence' => 'exact_equivalent' === $relationship ? 100 : 75,'is_official' => true,'status' => 'active','metadata' => json_encode(['source_platform' => 'hittrax']),'created_at' => $now,'updated_at' => $now]
            );
        }
        DB::table('platform_definitions')->updateOrInsert(['key' => 'rapsodo'], ['id' => $this->existingId('platform_definitions', 'key', 'rapsodo'),'name' => 'Rapsodo','description' => 'Rapsodo pitching XLSX exports.','is_active' => true,'created_at' => $now,'updated_at' => $now]);
        $rapsodoPlatformId = DB::table('platform_definitions')->where('key', 'rapsodo')->value('id');
        $rapsodoAliases = [
            'no' => ['session_context.event_number', null],
            'time' => ['session_context.event_time', null],
            'pitch_type' => ['pitching.tagged_pitch_type', null],
            'velocity' => ['pitching.release_velocity', 'mph'],
            'spin_rate' => ['pitching.spin_rate', 'rpm'],
            'true_spin' => ['pitching.true_spin_rate', 'rpm'],
            'spin_eff' => ['pitching.spin_efficiency', 'percent'],
            'spin_direction' => ['pitching.spin_direction_clock', null],
            'horz_break' => ['pitching.horizontal_break', 'in'],
            'vert_break' => ['pitching.vertical_break', 'in'],
            'strike' => ['pitching.strike_result', null],
            'rel_ht' => ['pitching.release_height', 'ft'],
            'rel_side' => ['pitching.release_side', 'ft'],
            'r_angle' => ['pitching.release_angle', 'deg'],
            'h_angle' => ['pitching.horizontal_release_angle', 'deg'],
            'gyro' => ['pitching.gyro_degree', 'deg'],
        ];
        foreach($rapsodoAliases as $alias => [$key, $unit]) {
            $conceptId = DB::table('baseball_concepts')->where('canonical_key', $key)->value('id');
            $normalized = Str::lower(preg_replace('/[^a-z0-9]/i', '', $alias));
            DB::table('baseball_concept_aliases')->updateOrInsert(
                ['platform_definition_id' => $rapsodoPlatformId,'normalized_alias' => $normalized],
                ['id' => $this->existingId('baseball_concept_aliases', 'normalized_alias', $normalized, 'platform_definition_id', $rapsodoPlatformId),'baseball_concept_id' => $conceptId,'alias' => $alias,'relationship_type' => 'exact_equivalent','source_unit_key' => $unit,'confidence' => 100,'is_official' => true,'status' => 'active','metadata' => json_encode(['source_platform' => 'rapsodo']),'created_at' => $now,'updated_at' => $now]
            );
        }
        DB::table('platform_definitions')->updateOrInsert(['key' => 'blast-motion'], ['id' => $this->existingId('platform_definitions', 'key', 'blast-motion'),'name' => 'Blast Motion','description' => 'Blast Motion baseball swing-sensor CSV exports.','is_active' => true,'created_at' => $now,'updated_at' => $now]);
        $blastPlatformId = DB::table('platform_definitions')->where('key', 'blast-motion')->value('id');
        $blastAliases = [
            'Date' => ['session_context.event_timestamp', null],
            'Equipment' => ['hitting.bat_equipment', null],
            'Handedness' => ['hitting.batter_side', null],
            'Swing Details' => ['hitting.swing_details', null],
            'Plane Score' => ['hitting.blast_plane_score', null],
            'Connection Score' => ['hitting.blast_connection_score', null],
            'Rotation Score' => ['hitting.blast_rotation_score', null],
            'Bat Speed (mph)' => ['hitting.bat_speed', 'mph'],
            'Rotational Acceleration (g)' => ['hitting.rotational_acceleration', 'g_force'],
            'On Plane Efficiency (%)' => ['hitting.on_plane_efficiency', 'percent'],
            'Attack Angle (deg)' => ['hitting.attack_angle', 'deg'],
            'Early Connection (deg)' => ['hitting.early_connection', 'deg'],
            'Connection at Impact (deg)' => ['hitting.connection_at_impact', 'deg'],
            'Vertical Bat Angle (deg)' => ['hitting.vertical_bat_angle', 'deg'],
            'Power (kW)' => ['hitting.blast_swing_power', 'kw'],
            'Time to Contact (sec)' => ['hitting.time_to_contact', 'sec'],
            'Peak Hand Speed (mph)' => ['hitting.peak_hand_speed', 'mph'],
            'Exit Velocity (mph)' => ['hitting.exit_velocity', 'mph'],
            'Launch Angle (deg)' => ['hitting.launch_angle', 'deg'],
            'Estimated Distance (feet)' => ['hitting.projected_distance', 'ft'],
        ];
        foreach($blastAliases as $alias => [$key, $unit]) {
            $conceptId = DB::table('baseball_concepts')->where('canonical_key', $key)->value('id');
            $normalized = Str::lower(preg_replace('/[^a-z0-9]/i', '', $alias));
            DB::table('baseball_concept_aliases')->updateOrInsert(
                ['platform_definition_id' => $blastPlatformId,'normalized_alias' => $normalized],
                ['id' => $this->existingId('baseball_concept_aliases', 'normalized_alias', $normalized, 'platform_definition_id', $blastPlatformId),'baseball_concept_id' => $conceptId,'alias' => $alias,'relationship_type' => 'exact_equivalent','source_unit_key' => $unit,'confidence' => 100,'is_official' => true,'status' => 'active','metadata' => json_encode(['source_platform' => 'blast-motion']),'created_at' => $now,'updated_at' => $now]
            );
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
