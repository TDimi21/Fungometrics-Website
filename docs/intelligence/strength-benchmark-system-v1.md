# FMTRX Strength Benchmark System v1

Version: 1.0.0

Status: active

Effective date: 2026-08-05

## Purpose and authority

Strength Benchmark System v1 is the backend authority for strength benchmarking and the canonical `strength_score`. It extends the existing `BenchmarkLibrary`, `BenchmarkDefinitions`, `PopulationMetricRepository`, `PopulationPercentileEngine`, `ResearchPercentileEngine`, and `CompositeBenchmarkEngine`. It does not scrape at runtime and does not introduce an independent percentile engine.

The legacy browser calculators remain only for transitional preview behavior. A client-supplied `strength_score` is not authoritative; the save workflow recalculates and mirrors the governed backend result.

## Metric inventory

| Canonical key | Label | Unit | Category | Storage/input/import status | V1 benchmark status |
|---|---|---:|---|---|---|
| `body_weight` | Body Weight | lb | body context | fitness, assessment, Strength template | descriptive population only |
| `front_squat` | Front Squat | lb | maximum strength | fitness, assessment modal, template | composite/reference; separate from back squat |
| `back_squat` | Back Squat | lb | maximum strength | fitness, assessment modal, template | composite/reference; legacy `squat` retained only for compatibility |
| `bench_press` | Bench Press | lb | maximum strength | fitness, assessment, template | composite/reference |
| `deadlift` | Conventional Deadlift | lb | maximum strength | fitness, assessment, template | composite/reference |
| `trap_bar_deadlift` | Trap-bar Deadlift | lb | maximum strength | fitness, assessment modal, template | population first; needs data when unusable |
| `power_clean` | Power Clean | lb | explosive strength | fitness, assessment modal, template | population, then approved provisional reference |
| `pull_ups` | Pull-ups | reps | strength endurance | fitness, assessment modal, template | composite/reference; protocol warning when unknown |
| `pushups` | Push-ups | reps | strength endurance | fitness `push_ups`, assessment modal, template | composite/reference; protocol warning when unknown |
| `plank_hold` | Plank Hold | sec | strength endurance | fitness, assessment modal, template | population first; needs data when unusable |
| `grip_strength_left` | Grip Strength Left | lb | grip | fitness, assessment modal, template | population/research candidate; left retained |
| `grip_strength_right` | Grip Strength Right | lb | grip | fitness, assessment modal, template | population/research candidate; right retained |
| `grip_strength_average` | Average Grip | lb | grip | derived | derived, never replaces either side |
| `grip_strength_best` | Best-hand Grip | lb | grip | derived | derived |
| `grip_asymmetry_percentage` | Grip Asymmetry | % | balance | derived | descriptive balance evidence |
| `vertical_jump` | Vertical Jump | in | explosive strength | fitness, assessment, template | existing composite age benchmark |
| `broad_jump` | Broad Jump | in | explosive strength | fitness, assessment, template | existing composite age benchmark |
| `med_ball_rotational_throw` | Medicine-ball Rotational Throw | ft | explosive strength | fitness, assessment modal, template | population/provisional; protocol and ball weight retained |
| `sprint_10yd` | 10 Yard Sprint | sec | speed support | fitness, assessment, template | population/provisional; excluded from Strength Score |
| `forty_yard_dash` | 40 Yard Dash | sec | speed support | fitness/template | existing composite/provisional; excluded from Strength Score |
| `sixty_yard_dash` | 60 Yard Dash | sec | speed support | fitness/template | existing composite/provisional; excluded from Strength Score |

Accepted aliases are normalized at boundaries only. `dead_lift` maps to conventional `deadlift`; it never maps to trap-bar deadlift. `front_squat` and `back_squat` no longer normalize to one metric. The legacy `squat` definition remains for old assessment consumers.

Hang clean is not an accepted input in v1 and is not inferred from power clean. Timed and untimed endurance protocols are retained as metadata and are not declared equivalent.

## Raw facts and versioned 1RM

`player_fitnesses` stores body weight on the same dated row as the test. V1 adds separate trap-bar, left grip, right grip, plank, and `strength_test_metadata` fields. The JSON metadata retains repetitions, declared method, grip device/protocol, push-up/plank protocol, and medicine-ball weight/protocol.

Epley v1 is a pure calculation:

```text
estimated_1rm = load × (1 + repetitions / 30)
```

- One repetition returns the tested load and is labeled tested 1RM.
- Two through ten repetitions return a labeled estimate.
- More than ten repetitions returns `rep_range_unsupported`; it does not produce an estimate.
- Actual load and repetitions remain in the result.
- Pull-ups, push-ups, grip, plank, jumps, throws, and sprints never use a 1RM estimate.

Applicable lifts calculate `relative_strength = tested_or_estimated_1rm / body_weight_at_test`. A missing or invalid at-test weight produces `null`, never zero. A later body-weight change does not rewrite the historical row.

## Context and buckets

Age groups are the existing `BenchmarkDefinitions` groups: `10U_12U`, `13U_14U`, `15U_16U`, `17U_18U`, `COLLEGE_19_PLUS`, and `UNKNOWN`. Missing age remains `UNKNOWN` and cannot produce an age-specific claim.

General benchmark buckets remain unchanged for backward compatibility. Strength-specific bodyweight bands are:

- Under age 15: `under_90`, `90_109`, `110_129`, `130_149`, `150_169`, `170_189`, `190_plus`.
- Age 15+: `under_130`, `130_149`, `150_169`, `170_189`, `190_209`, `210_229`, `230_249`, `250_plus`.

The population fallback remains exact peer → athletic peer → age and role → age only → global clean. Constitutionally established usable thresholds remain 30/100/300 guarded values for low/medium/high confidence. Counts below 30 are evidence but not an authoritative population percentile.

## Source policy and evidence

The result contract exposes source type/name/version, bucket, sample size, confidence, age group, strength bodyweight band, level, test method, evidence, and quality flags.

Order:

1. Usable FMTRX exact-peer population.
2. Usable broader FMTRX population through the existing fallback.
3. Existing governed Benchmark Library reference.
4. Existing approved repository community/provisional reference.
5. `Benchmark Needs Data`.

No external values are fetched during scoring. Existing repository anchors are labeled low-confidence operational references. Competitive powerlifting data must never be described as a baseball-population norm.

Reference candidates and licenses reviewed for future versioned artifacts:

- [OpenPowerlifting bulk CSV documentation](https://openpowerlifting.gitlab.io/opl-csv/bulk-csv-docs.html) — public-domain competition data; strength-sport population only.
- [Published powerlifting normative-data study](https://www.sciencedirect.com/science/article/pii/S1440244024002469) — competition-entry norms, not general baseball norms.
- [Pediatric handgrip population study (PubMed 28350764)](https://pubmed.ncbi.nlm.nih.gov/28350764/) — age/weight context candidate for a reviewed grip artifact.
- [NSCA youth resistance-training position statements](https://www.nsca.com/about-us/position-statements/) — supports supervised, age-appropriate youth testing; maturation limits universal claims.
- [Strength Level standards](https://strengthlevel.com/strength-standards) — community-submission source already represented in repository behavior; it must remain labeled community data.

## Result, goals, and classifications

Every row returns `test`, `benchmark`, `goal`, `trend`, `evidence`, and `data_quality`. Missing measurement and missing benchmark are distinct states. Body weight may show a peer percentile but always uses the `Descriptive` label.

Labels are consistent:

- 90–100: Elite
- 75–89: Above Average
- 50–74: Average
- 25–49: Below Average
- Under 25: Needs Development
- Missing/unusable: Benchmark Needs Data

Goals advance to the next governed 25th, 50th, 75th, or 90th-percentile tier when the selected reference exposes a defensible target value. A missing target remains null.

## Strength Score v1

Available subscores are reweighted using:

- Maximum Strength: 45%
- Explosive Strength: 30%
- Strength Endurance: 15%
- Strength Balance: 10%

Eligibility requires valid at-test body weight, at least two maximum-strength measurements, at least one lower-body measurement, at least one explosive or endurance measurement, and usable benchmark coverage for maximum strength plus explosive or endurance. Missing coverage returns:

```json
{"score": null, "status": "needs_data", "missing_requirements": ["..."]}
```

Strength Balance uses left/right grip only when both sides exist and push/pull balance only when both tests exist. An untested side or movement is not labeled an imbalance. Body weight and sprint results do not add achievement points to Strength Score.

## Dashboard and data quality

Player intelligence exposes the full result at `benchmark_profile.strength_v1` and projects its available metric results into the standard benchmark rows consumed by Percentile Rankings. Rows show raw and relative values, percentile/classification, peer group, goal/gap, trend, confidence, and source evidence. Mobile/accessibility labels include relative value, peer group, and confidence. Missing benchmarks retain a dashed track and explicit needs-data state.

Quality flags include `missing_body_weight`, `missing_age`, `missing_repetitions`, `rep_range_unsupported`, `protocol_unknown`, `insufficient_population`, `provisional_reference`, and `benchmark_unavailable`.

## Known limitations

- No reviewed static OpenPowerlifting or pediatric grip artifact is shipped in v1; the sources above are documented candidates, not silently imported values.
- Trap-bar and plank remain population-first and may show Benchmark Needs Data.
- Medicine-ball comparisons are provisional when ball weight or protocol is absent.
- Legacy `PlayerAssessment.squat_lbs` cannot distinguish squat variation. New full assessments preserve the separate facts in the synchronized fitness snapshot; old rows remain legacy squat evidence.
- Data Hub Strength templates now expose the missing fields and protocol columns. Existing Data Hub execution/authorization rules are unchanged.
