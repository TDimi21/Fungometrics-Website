# FMTRX Baseball Translation Engine v1 Certification

## Scope

Phase 2B.1F certifies the inspection-only translation boundary:

- source file and platform detection;
- file-structure interpretation;
- player translation;
- Baseball Concept translation;
- unit and controlled-value translation;
- explicit Not Importing decisions;
- warnings and coach confirmations;
- cross-platform semantic equivalence and non-equivalence.

It does not certify Import Batch persistence or any write into FMTRX performance records.

## Exact equivalence

| Group | Platform | Source field | Canonical key | Source unit | Canonical unit | Relationship |
|---|---|---|---|---|---|---|
| Exit Velocity | TrackMan | `ExitSpeed` | `hitting.exit_velocity` | mph | mph | exact equivalent |
| Exit Velocity | HitTrax | `Velo` | `hitting.exit_velocity` | mph | mph | exact equivalent |
| Exit Velocity | Blast Motion | `Exit Velocity (mph)` | `hitting.exit_velocity` | mph | mph | exact equivalent |
| Launch Angle | TrackMan | `Angle` | `hitting.launch_angle` | degrees | degrees | exact equivalent |
| Launch Angle | HitTrax | `LA` | `hitting.launch_angle` | degrees | degrees | exact equivalent |
| Launch Angle | Blast Motion | `Launch Angle (deg)` | `hitting.launch_angle` | degrees | degrees | exact equivalent |
| Spray Angle | TrackMan | `Direction` | `hitting.spray_angle` | degrees | degrees | exact equivalent |
| Spray Angle | HitTrax | `Horiz. Angle` | `hitting.spray_angle` | degrees | degrees | exact equivalent |
| Release Velocity | TrackMan | `RelSpeed` | `pitching.release_velocity` | mph | mph | exact equivalent |
| Release Velocity | Rapsodo | `velocity` | `pitching.release_velocity` | mph | mph | exact equivalent |

Concept UUIDs are environment-owned database identifiers. The semantic certification test resolves and compares the exact UUIDs at runtime rather than hardcoding installation-specific values.

## Protected distinctions

- HitTrax `Pitch` remains inbound pitch velocity, separate from pitcher release velocity.
- Blast Bat Speed remains separate from Exit Velocity.
- Blast Peak Hand Speed remains separate from Bat Speed.
- TrackMan Distance remains projected distance, separate from measured carry.
- HitTrax `Res` remains a simulated outcome, not an official game result.
- TrackMan tagged and automatic hit trajectory remain separate concepts.
- Pitch Spin Axis remains separate from Spin Direction Clock.
- Vertical Break remains separate from Induced Vertical Break.

## Value normalization

The certification covers identity conversions for mph, percentages, and degrees; approved kph-to-mph and meter-to-feet conversions; null preservation for unavailable values; and preservation of zero unless a platform-specific parser explicitly defines zero as unavailable.

## Persistence boundary

The tests snapshot protected tables before and after resolution. No practice, session result, assessment, profile, mapping template, import batch, or canonical event record may be created.

## Release gate

The Translation Engine v1 can be frozen only when:

1. semantic certification passes;
2. the complete Data Hub backend suite passes;
3. the Translation Review and complete frontend suites pass;
4. the production frontend build succeeds;
5. no production or performance persistence occurs.

## Certification result

Certified locally on July 27, 2026:

- semantic and complete Data Hub backend suite: 59 passed;
- complete frontend suite: 148 passed;
- production Vite build: passed;
- PHP formatting and syntax: passed;
- JSON manifest validation: passed;
- Git whitespace validation: passed;
- protected persistence snapshots: unchanged.

The Vite build retains an existing performance warning for the approximately
1 MB main application chunk. This does not invalidate semantic certification,
but bundle splitting remains a separate performance task.
