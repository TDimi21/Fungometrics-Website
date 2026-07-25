# FMTRX Baseball Dictionary Review

Generated from the Phase 2B.1 seed on MariaDB 10.1.48. Review before canonical import storage begins.

> Compatible sessions are not yet persisted as concept metadata in Phase 2B.1. The report marks them as “Not constrained” rather than inferring compatibility.

| Domain | Concept | Canonical key | Definition | Unit | Valid range | Compatible sessions | TrackMan aliases | Research eligible | Profile visible |
|---|---|---|---|---|---|---|---|---:|---:|
| Body Composition | Body Weight | `body_composition.body_weight` | Measured body weight. | lbs | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Hitting | Bat Speed | `hitting.bat_speed` | Measured bat speed under the declared device definition. | mph | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Hitting | Batted Ball Spin Axis | `hitting.ball_spin_axis` | Batted-ball spin-axis angle under the declared convention. | deg | Unbounded warning range | Not constrained | Hit Spin Axis | No | Yes |
| Hitting | Batted Ball Spin Rate | `hitting.ball_spin_rate` | Batted-ball rotational rate. | rpm | 0.000000 to ∞ | Not constrained | Hit Spin Rate | No | Yes |
| Hitting | Contact Quality | `hitting.contact_quality` | Observed quality-of-contact classification. | — | Unbounded warning range | Not constrained | — | No | Yes |
| Hitting | Exit Velocity | `hitting.exit_velocity` | Speed of the batted ball immediately after contact. | mph | 0.000000 to 130.000000 | Not constrained | Exit Velo, ExitSpeed, ExitSpeedMPH, ExitVelocity | No | Yes |
| Hitting | Field Direction | `hitting.field_direction` | Pull, middle, or opposite-field classification. | — | Unbounded warning range | Not constrained | — | No | Yes |
| Hitting | Hang Time | `hitting.hang_time` | Elapsed airborne time. | sec | 0.000000 to ∞ | Not constrained | Hang Time | No | Yes |
| Hitting | Hit Trajectory | `hitting.trajectory` | Batted-ball trajectory classification. | — | Unbounded warning range | Not constrained | AutoHitType, AutomaticHitType, Tagged Hit Type | No | Yes |
| Hitting | Last Tracked Distance | `hitting.last_tracked_distance` | Distance at the final tracked point. | ft | 0.000000 to ∞ | Not constrained | Last Tracked Distance | No | Yes |
| Hitting | Launch Angle | `hitting.launch_angle` | Vertical angle of the batted ball after contact. | deg | -90.000000 to 90.000000 | Not constrained | Angle, Launch Angle | No | Yes |
| Hitting | Maximum Height | `hitting.maximum_height` | Maximum batted-ball height. | ft | 0.000000 to ∞ | Not constrained | Max Height, MaximumHeight | No | Yes |
| Hitting | Measured Carry Distance | `hitting.measured_carry_distance` | Measured airborne carry distance. | ft | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Hitting | Projected Distance | `hitting.projected_distance` | Platform-projected total batted-ball distance. | ft | 0.000000 to ∞ | Not constrained | CarryDistance, Distance, HitDistance | No | Yes |
| Hitting | Spray Angle | `hitting.spray_angle` | Horizontal batted-ball direction using the declared source convention. | deg | Unbounded warning range | Not constrained | Direction, Spray Angle | No | Yes |
| Mobility | Ankle Mobility | `mobility.ankle` | Coach-rated ankle mobility. | score_0_10 | 0.000000 to 10.000000 | Not constrained | — | No | Yes |
| Mobility | Hip Mobility | `mobility.hip` | Coach-rated hip mobility. | score_0_10 | 0.000000 to 10.000000 | Not constrained | — | No | Yes |
| Mobility | Shoulder Mobility | `mobility.shoulder` | Coach-rated shoulder mobility. | score_0_10 | 0.000000 to 10.000000 | Not constrained | — | No | Yes |
| Pitching | Automatic Pitch Type | `pitching.automatic_pitch_type` | Platform-generated pitch classification. | — | Unbounded warning range | Not constrained | AutomaticPitchType, AutoPitchType | No | Yes |
| Pitching | Extension | `pitching.extension` | Release extension toward home plate. | ft | 0.000000 to ∞ | Not constrained | Extension, ReleaseExtension | No | Yes |
| Pitching | Horizontal Break | `pitching.horizontal_break` | Horizontal pitch movement under the declared source convention. | in | Unbounded warning range | Not constrained | HorizontalBreak, HorzBreak | No | Yes |
| Pitching | Induced Vertical Break | `pitching.induced_vertical_break` | Vertical movement relative to gravity-only trajectory. | in | Unbounded warning range | Not constrained | InducedVertBreak, IVB | No | Yes |
| Pitching | Pitch Spin Axis | `pitching.spin_axis` | Pitch spin-axis angle under the declared source convention. | deg | Unbounded warning range | Not constrained | SpinAxis | No | Yes |
| Pitching | Pitch Spin Rate | `pitching.spin_rate` | Pitch rotational rate. | rpm | 0.000000 to ∞ | Not constrained | PitchSpinRate, SpinRate | No | Yes |
| Pitching | Plate Location Height | `pitching.plate_location_height` | Vertical plate-crossing location. | ft | Unbounded warning range | Not constrained | PlateLocHeight | No | Yes |
| Pitching | Plate Location Side | `pitching.plate_location_side` | Horizontal plate-crossing location under the declared convention. | ft | Unbounded warning range | Not constrained | PlateLocSide | No | Yes |
| Pitching | Release Height | `pitching.release_height` | Vertical release position. | ft | 0.000000 to ∞ | Not constrained | ReleaseHeight | No | Yes |
| Pitching | Release Side | `pitching.release_side` | Horizontal release position under the declared convention. | ft | Unbounded warning range | Not constrained | ReleaseSide | No | Yes |
| Pitching | Release Velocity | `pitching.release_velocity` | Ball velocity at or near release under the declared source method. | mph | 0.000000 to 110.000000 | Not constrained | PitchVelocity, ReleaseSpeed, RelSpeed | No | Yes |
| Pitching | Strike Result | `pitching.strike_result` | Whether the pitch was recorded as a strike. | — | Unbounded warning range | Not constrained | — | No | Yes |
| Pitching | Tagged Pitch Type | `pitching.tagged_pitch_type` | Human-tagged pitch classification. | — | Unbounded warning range | Not constrained | Tagged Pitch Type | No | Yes |
| Recovery | Sleep Duration | `recovery.sleep_duration` | Reported sleep duration. | hours | 0.000000 to 24.000000 | Not constrained | — | No | Yes |
| Recovery | Sleep Quality | `recovery.sleep_quality` | Reported sleep quality on a 1–5 scale. | score_1_5 | 1.000000 to 5.000000 | Not constrained | — | No | Yes |
| Session Context | Event Date | `session_context.event_date` | Source date associated with an event or session. | — | Unbounded warning range | Not constrained | Date, GameDate, SessionDate | No | Yes |
| Session Context | Event Identifier | `session_context.event_identifier` | Stable source event identifier. | — | Unbounded warning range | Not constrained | EventId, PitchNo, PitchNumber, PitchUID | No | Yes |
| Session Context | Facility | `session_context.facility` | Source facility or venue name. | — | Unbounded warning range | Not constrained | Facility, Stadium, Venue | No | Yes |
| Session Context | Measurement System | `session_context.system` | Source measurement system identifier. | — | Unbounded warning range | Not constrained | RadarSystem, System, TrackingSystem | No | Yes |
| Session Context | Player Identity | `session_context.player_identity` | Source participant identity used for player mapping. | — | Unbounded warning range | Not constrained | Batter, Batter Name, Hitter, Pitcher, Pitcher Name | No | Yes |
| Speed and Agility | 10-Yard Sprint | `speed_agility.sprint_10yd` | Elapsed time over ten yards. | sec | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Speed and Agility | 40-Yard Sprint | `speed_agility.sprint_40yd` | Elapsed time over forty yards. | sec | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Speed and Agility | 60-Yard Sprint | `speed_agility.sprint_60yd` | Elapsed time over sixty yards. | sec | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Back Squat | `strength.back_squat` | Back-squat load. | lbs | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Bench Press | `strength.bench_press` | Bench-press load. | lbs | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Broad Jump | `strength.broad_jump` | Measured standing broad-jump distance. | in | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Deadlift | `strength.deadlift` | Deadlift load. | lbs | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Front Squat | `strength.front_squat` | Front-squat load. | lbs | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Hand Grip Strength | `strength.hand_grip` | Measured hand-grip force. | lbs | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Power Clean | `strength.power_clean` | Power-clean load. | lbs | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Strength | Vertical Jump | `strength.vertical_jump` | Measured vertical-jump height. | in | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Throwing | Ball Weight | `throwing.ball_weight` | Weight of the thrown ball. | oz | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Throwing | Long-Toss Distance | `throwing.long_toss_distance` | Throwing distance during long toss. | ft | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Throwing | Long-Toss Hops | `throwing.long_toss_hops` | Ground hops before the target. | count | 0.000000 to ∞ | Not constrained | — | No | Yes |
| Throwing | Weighted-Ball Velocity | `throwing.weighted_ball_velocity` | Velocity of a weighted-ball throw. | mph | 0.000000 to 110.000000 | Not constrained | — | No | Yes |
