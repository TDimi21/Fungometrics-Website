# Ball Flight Research — Pre-Migration Report

No research migration was run, no research row was persisted, and calibration
remains inactive.

## Source inspection

The five unique TrackMan exports share this exact 167-column header:

```text
PitchNo,Date,Time,PAofInning,PitchofPA,Pitcher,PitcherId,PitcherThrows,PitcherTeam,Batter,BatterId,BatterSide,BatterTeam,PitcherSet,Inning,Top/Bottom,Outs,Balls,Strikes,TaggedPitchType,AutoPitchType,PitchCall,KorBB,TaggedHitType,PlayResult,OutsOnPlay,RunsScored,Notes,RelSpeed,VertRelAngle,HorzRelAngle,SpinRate,SpinAxis,Tilt,RelHeight,RelSide,Extension,VertBreak,InducedVertBreak,HorzBreak,PlateLocHeight,PlateLocSide,ZoneSpeed,VertApprAngle,HorzApprAngle,ZoneTime,ExitSpeed,Angle,Direction,HitSpinRate,PositionAt110X,PositionAt110Y,PositionAt110Z,Distance,LastTrackedDistance,Bearing,HangTime,pfxx,pfxz,x0,y0,z0,vx0,vy0,vz0,ax0,ay0,az0,HomeTeam,AwayTeam,Stadium,Level,League,GameID,PitchUID,EffectiveVelo,MaxHeight,MeasuredDuration,SpeedDrop,PitchLastMeasuredX,PitchLastMeasuredY,PitchLastMeasuredZ,ContactPositionX,ContactPositionY,ContactPositionZ,GameUID,UTCDate,UTCTime,LocalDateTime,UTCDateTime,AutoHitType,System,HomeTeamForeignID,AwayTeamForeignID,GameForeignID,Catcher,CatcherId,CatcherThrows,CatcherTeam,PlayID,PitchTrajectoryXc0,PitchTrajectoryXc1,PitchTrajectoryXc2,PitchTrajectoryYc0,PitchTrajectoryYc1,PitchTrajectoryYc2,PitchTrajectoryZc0,PitchTrajectoryZc1,PitchTrajectoryZc2,HitSpinAxis,HitTrajectoryXc0,HitTrajectoryXc1,HitTrajectoryXc2,HitTrajectoryXc3,HitTrajectoryXc4,HitTrajectoryXc5,HitTrajectoryXc6,HitTrajectoryXc7,HitTrajectoryXc8,HitTrajectoryYc0,HitTrajectoryYc1,HitTrajectoryYc2,HitTrajectoryYc3,HitTrajectoryYc4,HitTrajectoryYc5,HitTrajectoryYc6,HitTrajectoryYc7,HitTrajectoryYc8,HitTrajectoryZc0,HitTrajectoryZc1,HitTrajectoryZc2,HitTrajectoryZc3,HitTrajectoryZc4,HitTrajectoryZc5,HitTrajectoryZc6,HitTrajectoryZc7,HitTrajectoryZc8,ThrowSpeed,PopTime,ExchangeTime,TimeToBase,CatchPositionX,CatchPositionY,CatchPositionZ,ThrowPositionX,ThrowPositionY,ThrowPositionZ,BasePositionX,BasePositionY,BasePositionZ,ThrowTrajectoryXc0,ThrowTrajectoryXc1,ThrowTrajectoryXc2,ThrowTrajectoryYc0,ThrowTrajectoryYc1,ThrowTrajectoryYc2,ThrowTrajectoryZc0,ThrowTrajectoryZc1,ThrowTrajectoryZc2,PitchReleaseConfidence,PitchLocationConfidence,PitchMovementConfidence,HitLaunchConfidence,HitLandingConfidence,CatcherThrowCatchConfidence,CatcherThrowReleaseConfidence,CatcherThrowLocationConfidence
```

Statcast 100 header:

```text
sample_id,game_date,game_pk,batter_name,batter_mlbam_id,batter_side,pitcher_mlbam_id,home_team,away_team,inning,inning_half,at_bat_number,pitch_number,event,description,exit_velocity_mph,distance_ft,launch_angle_deg,spray_angle_deg,spray_field_direction,hc_x,hc_y,spray_angle_is_derived,data_source
```

Statcast 5,000 header:

```text
swing_id,exit_velocity_mph,distance_ft,launch_angle_deg,spray_angle_deg
```

The two `20250321-NorthOconeeMain-2` files are exact byte-for-byte duplicates:
SHA-256 `afc8ecd0dafcda9bd6232c13ca59f9ef77249cba7abddd8ea7ef1b54f970cf24`.

## Dry-run counts

| File | Source rows | Batted balls | Eligible calibration | Eligible external | Spin | Hang | Height |
|---|---:|---:|---:|---:|---:|---:|---:|
| 20250221-NorthOconeeMain-1 | 163 | 34 | 10 | 0 | 20 | 31 | 34 |
| 20250321-NorthOconeeMain-2 | 242 | 50 | 23 | 0 | 35 | 44 | 50 |
| 20260309-NorthOconeeMain-Private-1 | 193 | 30 | 13 | 0 | 24 | 23 | 30 |
| 20260313-NorthOconeeMain-2 | 210 | 56 | 24 | 0 | 33 | 43 | 56 |
| 20260506-NorthOconeeMain-3 | 141 | 48 | 25 | 0 | 33 | 42 | 48 |
| MLB Statcast 100 | 100 | 100 | 0 | 97 | 0 | 0 | 0 |
| MLB Statcast 5,000 | 5,000 | 5,000 | 0 | 4,998 | 0 | 0 | 0 |
| **Unique-file total** | **6,049** | **5,318** | **95** | **5,095** | **145** | **183** | **218** |

No within-file duplicate normalized rows were detected. Exclusion reasons overlap:

- TrackMan: missing primary measurement 35; extreme/backward spray 52;
  low/missing launch confidence 37; low/missing landing confidence 89; foul
  contact 66; bunt 6; invalid exit velocity 1.
- Statcast: bunt 3; invalid exit velocity 2.

## Deterministic session partitions

- Training: `20260309-NorthOconeeMain-Private-1`,
  `20250321-NorthOconeeMain-2`, `20260313-NorthOconeeMain-2`
- Validation: `20260506-NorthOconeeMain-3`
- Locked test: `20250221-NorthOconeeMain-1`
- Statcast: external validation only

## In-memory raw-physics accuracy

These comparisons used a 25-sample fast uncertainty sweep and wrote no data.
Ground balls have no modeled airborne carry and are therefore not included in
distance-error metrics.

| Cohort | Evaluated | Bias | MAE | RMSE | P90 abs | Within 10 | Within 15 | Within 25 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| TrackMan, estimated spin | 63 | +35.17 ft | 35.27 ft | 42.78 ft | 68.4 ft | 12.7% | 25.4% | 46.0% |
| TrackMan, measured spin | 51 | +35.83 ft | 36.04 ft | 44.84 ft | 71.9 ft | 27.5% | 31.4% | 43.1% |
| Statcast, estimated spin | 2,934 | +49.47 ft | 49.58 ft | 59.36 ft | 94.2 ft | 4.6% | 8.8% | 21.6% |

The raw model currently overestimates carry materially. This evidence blocks
activation of calibration or production display changes until source-specific
analysis, fitting, and held-out validation are completed.

## Runtime estimate

Measured benchmark: 20 fast predictions completed in 1.11 seconds locally.
Expected fast evaluation is roughly 5–8 minutes for the supplied files plus
database overhead. The full 500-sample sweep is approximately 20 times slower
and may take 90–120 minutes on comparable hardware.
