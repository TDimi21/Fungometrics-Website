# FMTRX Ball Flight Intelligence Engine

`BallFlightEngine` is the shared orchestration boundary for Cage, Live AB,
reports, recruiting, and future intelligence consumers.

Current production-safe flow:

1. Normalize environment, spin, and ball profile inputs.
2. Run the existing validated 3D RK4 physics through `PhysicsEngine`.
3. Apply a calibration profile only when one was explicitly fitted from paired
   predicted/measured observations.
4. Return carry, range, hang time, apex, landing coordinates, assumptions,
   provenance, and a numeric confidence score.

Research flow:

1. Import TrackMan or Statcast CSV data through `ResearchDatabase`.
2. Normalize it to the shared research-row contract.
3. Run `ValidationEngine` to produce count, MAE, RMSE, bias, and per-ball
   residuals.
4. Review source quality and exclusions.
5. Fit a versioned calibration profile from a training partition.
6. Validate that profile against a held-out partition before enabling it.

No TrackMan or Statcast files are committed to this repository. The engine does
not invent coefficients when those datasets are absent, and the current Cage
path remains uncalibrated until a reviewed profile is supplied.

Required normalized fields:

- `exit_velocity_mph`
- `launch_angle_deg`
- `spray_angle_deg` (defaults to center field when a source does not provide it)
- `measured_carry_ft`

Optional measured fields:

- `spin_rate_rpm`
- `hang_time_seconds`
- `maximum_height_ft`

Statcast `hit_distance_sc` is useful for broad external validation but is not
treated as equivalent to a paired TrackMan carry measurement. Calibration
reports must remain segmented by source.
