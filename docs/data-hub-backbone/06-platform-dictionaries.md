# Chapter 6 — Platform Dictionaries

## Many Platforms, One Canonical Language

Every baseball technology speaks its own language.

TrackMan uses one set of field names, units, event structures, and classifications.

HitTrax uses another.

Rapsodo describes similar measurements differently.

Blast Motion focuses on swing-sensor observations that may not exist in ball-flight systems.

FMTRX Templates and coach spreadsheets may use familiar baseball terms without any formal vendor specification.

The Platform Dictionary layer exists to understand those differences without allowing them to alter canonical FMTRX meaning.

A Platform Dictionary explains how one source platform speaks.

It does not define what baseball means inside FMTRX.

The FMTRX Baseball Dictionary remains the single canonical authority.

Platform Dictionaries translate toward it.

---

## Constitutional Relationship

Platform Dictionaries implement:

- Data Integrity Chain
- Rule of One Meaning
- Source Preservation
- Source Independence
- Reproducibility
- Platform Neutrality
- Context Matters
- Non-Destructive Evolution

A Platform Dictionary must never weaken those laws.

It must preserve source meaning while translating source-specific terminology into canonical FMTRX concepts.

---

## Purpose

A Platform Dictionary is the versioned translation specification for one external source.

Its purpose is to define:

- How a platform is recognized
- How its files are structured
- What each source field means
- Which source units are used
- Which controlled values exist
- Which Baseball Concepts are valid translation candidates
- Which source fields are semantically equivalent
- Which fields are intentionally not equivalent
- Which destinations are compatible
- Which warnings should be displayed
- How translations are validated and certified

A Platform Dictionary enables FMTRX to support a new platform without changing the core Translation Engine architecture.

---

## Scope

Platform Dictionaries may define:

- Platform identity
- Product identity
- Device identity
- Export format
- File signatures
- Header signatures
- Worksheet signatures
- Source field definitions
- Source aliases
- Source data types
- Source units
- Controlled values
- Destination compatibility
- Candidate canonical concepts
- Confidence levels
- Warning rules
- Protected non-equivalences
- Version history
- Certification fixtures

Platform Dictionaries do not define:

- Canonical Baseball Concepts
- Canonical units
- Athlete identity
- Permanent sessions
- Canonical Events
- Canonical Metrics
- Statistics
- Benchmarks
- Player DNA
- AI logic

---

## Platform Identity

Each Platform Dictionary must define the source it represents.

At minimum:

- Platform key
- Platform name
- Vendor
- Product name, when applicable
- Device or system version, when relevant
- Export type
- Supported file formats
- Dictionary version
- Lifecycle status

Example:

```text
Platform Key: trackman
Platform Name: TrackMan
Vendor: TrackMan
Export Type: Ball and pitch tracking export
Supported Formats: CSV, XLSX
Dictionary Version: 1.0.0
Status: Active
```

A single vendor may require multiple Platform Dictionaries when products, export generations, or measurement definitions differ materially.

---

## Platform Recognition

A Platform Dictionary may define evidence used to identify a source file.

Recognition evidence may include:

* File name patterns
* Worksheet names
* Required headers
* Optional headers
* Header combinations
* Product identifiers
* Device identifiers
* Embedded metadata
* Export version markers
* Controlled-value signatures
* Unit patterns

Recognition must be evidence-based.

The presence of one familiar field is not always sufficient.

For example, a column named `Velocity` does not prove that a file came from a particular platform or that it represents one specific concept.

---

## Recognition Confidence

Platform recognition may produce:

* Exact match
* Strong match
* Probable match
* Ambiguous match
* Unknown

The Translation Engine may recommend a platform.

The coach must be able to review and correct the selection before approval.

A low-confidence platform match must never silently become permanent truth.

---

## Source Field Definition

Every recognized source field should define:

* Exact source header
* Source aliases
* Source description
* Source data type
* Source unit
* Event context
* Session context
* Controlled values, if applicable
* Candidate Baseball Concept
* Translation confidence
* Destination compatibility
* Validation rules
* Known ambiguity
* Source documentation
* Certification evidence

Example:

```text
Source Header: ExitSpeed
Platform: TrackMan
Definition: Speed of the ball immediately after bat contact
Source Unit: mph
Candidate Concept: hitting.exit_velocity
Confidence: Certified
```

The Platform Dictionary must describe the source meaning before assigning a canonical meaning.

---

## Source Aliases

A platform may change capitalization, spacing, punctuation, or export naming across versions.

Examples:

```text
ExitSpeed
Exit Speed
exit_speed
EXIT_SPEED
```

Source aliases may be grouped only when they are verified as representing the same source field meaning.

Similar spelling alone is not enough.

---

## Source Units

Each source field must declare its source unit when known.

Examples:

* mph
* km/h
* rpm
* degrees
* feet
* meters
* seconds
* milliseconds
* inches
* centimeters
* pounds
* kilograms
* percentage
* unitless score

If a source unit varies by file or user setting, the Platform Dictionary must define how that unit is detected.

Unit conversion occurs only after the source unit is known.

Unknown units must generate a warning rather than an invented conversion.

---

## Candidate Canonical Concepts

A Platform Dictionary may recommend one or more candidate Baseball Concepts for a source field.

Example:

```text
Source Header: Velo
Platform: HitTrax
Context: Batted-ball result
Candidate Concept: hitting.exit_velocity
```

Another example:

```text
Source Header: Pitch
Platform: HitTrax
Context: Inbound pitch shown during a hitting session
Candidate Concept: pitching.inbound_pitch_velocity
```

The Platform Dictionary must use context to distinguish similar labels.

A source field may remain unmapped if no certified canonical concept exists.

---

## Mapping Confidence

Recommended translations may carry confidence classifications.

Suggested classifications:

* Certified
* High confidence
* Moderate confidence
* Low confidence
* Manual review required
* Unsupported
* Protected non-equivalence

Confidence helps the coach understand the strength of the recommendation.

Confidence does not replace approval.

---

## Semantic Equivalence Certification

Two source fields may map to one Baseball Concept only when their meanings are certified as equivalent.

Certification may use:

* Official vendor documentation
* Field dictionaries
* Export documentation
* Real sample files
* Unit comparison
* Measurement-method comparison
* Event-context comparison
* Controlled-value comparison
* Regression fixtures
* Manual architecture review

Certified example:

```text
TrackMan: ExitSpeed
HitTrax: Velo
Blast: Exit Velocity
        ↓
FMTRX: hitting.exit_velocity
```

Certification must establish shared meaning, not merely similar output ranges.

---

## Protected Non-Equivalence

Platform Dictionaries must explicitly preserve important distinctions.

Examples:

```text
TrackMan RelSpeed
→ Pitch Release Velocity
```

```text
HitTrax Pitch
→ Inbound Pitch Velocity
```

These are not equivalent.

Additional protected distinctions include:

```text
Bat Speed
≠
Exit Velocity
```

```text
Peak Hand Speed
≠
Bat Speed
```

```text
Spin Direction
≠
Spin Axis
```

```text
Vertical Break
≠
Induced Vertical Break
```

```text
Projected Distance
≠
Measured Carry Distance
```

```text
Tagged Classification
≠
Automatically Measured Classification
```

Protected non-equivalences should be documented and tested.

---

## Controlled-Value Dictionaries

Platforms often use different labels for the same controlled value.

Example pitch types:

```text
FF
FourSeam
Four-Seam
4-Seam Fastball
```

These may translate to:

```text
Four-Seam Fastball
```

A Controlled-Value Dictionary must define:

* Source value
* Source description
* Canonical value
* Context
* Confidence
* Version
* Unknown-value behavior
* Deprecation behavior

Unknown controlled values must not be silently forced into the closest known value.

They must remain unknown, require manual mapping, or remain Not Importing.

---

## Destination Compatibility

Platform Dictionaries may recommend destinations based on the source platform and file contents.

Examples:

TrackMan may commonly support:

* Bullpen
* Pitching Practice
* Cage
* Batting Practice
* Live AB
* Game

Blast Motion may commonly support:

* Cage
* Batting Practice
* Live AB
* Assessment

Rapsodo pitching exports may commonly support:

* Bullpen
* Pitching Practice
* Assessment

These are recommendations.

Platform identity must not permanently restrict destination.

Destination compatibility influences:

* Ranking
* Warnings
* Suggested concepts
* Validation
* Session reconstruction

The coach retains final control.

---

## File Structure Rules

A Platform Dictionary may define known file structures.

Examples:

* One event per row
* One athlete per worksheet
* Metadata before the header row
* Session summary followed by event rows
* Multiple tables in one worksheet
* Players represented in columns
* Controlled values embedded in formatted text

Known structures may be auto-detected.

Unknown or conflicting structures must route through File Structure review.

---

## Source Classification

A Platform Dictionary should identify how each value was produced when known.

Possible classifications include:

* Directly measured
* Derived by platform
* Projected
* Estimated
* Manually tagged
* Coach entered
* Athlete entered
* Calculated from other source fields
* Source-specific score
* Unknown

Classification is part of provenance.

It must not be discarded during translation.

---

## Platform-Specific Scores

Some platforms produce proprietary scores.

Examples may include:

* Blast scores
* Platform readiness scores
* Platform quality scores
* Device-specific efficiency scores

A proprietary score may remain source-specific when its exact meaning is not canonically equivalent to an FMTRX concept.

It may be preserved as:

```text
source_specific.blast.connection_score
```

or an equivalent governed source-specific concept namespace.

It must not be renamed into a broader canonical meaning without certification.

---

## Missing and Unavailable Fields

A Platform Dictionary must distinguish:

* Zero
* Blank
* Not measured
* Not available
* Not applicable
* Invalid
* Unsupported
* Filtered out

Missing information must not become zero.

For example, a Blast file without Exit Velocity does not imply:

```text
Exit Velocity = 0
```

It implies:

```text
Exit Velocity = unavailable
```

---

## Warnings

Platform Dictionaries may define warnings for:

* Unknown units
* Ambiguous fields
* Unsupported fields
* Unexpected values
* Destination mismatch
* Missing player identity
* Duplicate player identifiers
* Mixed-session files
* Unrecognized controlled values
* Potentially derived values
* Proprietary scores
* Version mismatch
* Partial exports
* Missing required context

Warnings must be visible during Translation Review.

Warnings may block approval when the risk is severe.

---

## Platform Dictionary Versioning

Every Platform Dictionary is versioned.

A version change may include:

* New recognized headers
* New aliases
* New controlled values
* New supported export versions
* Improved warnings
* Additional certification
* Corrected source definitions
* New protected non-equivalences

A version change may not silently alter previously approved translations.

Historical Translation Snapshots must retain the exact Platform Dictionary version used.

---

## Lifecycle

Each Platform Dictionary follows a governed lifecycle:

```text
Proposed
    ↓
Research
    ↓
Draft
    ↓
Certified
    ↓
Active
    ↓
Deprecated
    ↓
Retired
```

A Deprecated dictionary may remain necessary for historical files.

A Retired dictionary must remain available for reproducibility and audit.

---

## Adding a New Platform

A new platform should require:

1. Source research
2. Real export samples
3. File recognition rules
4. Source field definitions
5. Unit definitions
6. Controlled-value definitions
7. Candidate concept mappings
8. Protected non-equivalences
9. Destination compatibility
10. Certification fixtures
11. Architecture review
12. Dictionary activation

Adding a platform must not require redesigning:

* Player Mapping
* Column Mapping
* Translation Review
* Translation Snapshot
* Import Batch
* Canonical Events
* Canonical Metrics

If a new platform requires a new core import architecture, the abstraction should be reviewed before implementation.

---

## Known Platform Examples

### TrackMan

The TrackMan dictionary may define source fields such as:

* ExitSpeed
* Angle
* Direction
* RelSpeed
* SpinRate
* SpinAxis
* Extension
* RelHeight
* RelSide
* InducedVertBreak
* HorzBreak

The dictionary must preserve distinctions between:

* Release Velocity and inbound pitch velocity
* Spin Axis and Spin Direction
* Induced Vertical Break and other vertical-break definitions
* Projected and measured distance

---

### HitTrax

The HitTrax dictionary may define source fields such as:

* Velo
* LA
* Horiz. Angle
* Pitch
* Distance

Important distinctions include:

```text
Velo
→ Hitting Exit Velocity
```

```text
Pitch
→ Inbound Pitch Velocity
```

```text
Distance
→ Projected Distance
```

Distance must not be treated as measured carry unless certified evidence supports that meaning.

---

### Rapsodo

The Rapsodo dictionary may define source fields such as:

* velocity
* Total Spin
* True Spin
* Spin Direction
* Horizontal Break
* Vertical Break
* Release Height
* Release Side

Important distinctions include:

```text
velocity
→ Pitch Release Velocity
```

```text
Spin Direction
→ Clock representation
```

Spin Direction must not automatically become Spin Axis.

True Spin must remain distinct from Total Spin.

---

### Blast Motion

The Blast Motion dictionary may define source fields such as:

* Bat Speed
* Peak Hand Speed
* Attack Angle
* Time to Contact
* Connection Score
* Rotational Acceleration

Important distinctions include:

```text
Bat Speed
≠
Exit Velocity
```

```text
Peak Hand Speed
≠
Bat Speed
```

Source-specific Blast scores remain source-specific unless canonical equivalence is certified.

Fields absent from the supplied file remain unavailable.

---

### FMTRX Templates

FMTRX Templates are controlled source formats designed to match actual FMTRX forms.

They may include:

* FMTRX Player ID
* Player Name
* Team ID
* Date
* Canonical concept keys
* Canonical units

FMTRX Templates may receive high-confidence automatic mappings.

They still require Translation Review and approval.

---

### Generic Spreadsheet

A Generic Spreadsheet has no trusted vendor dictionary.

Its dictionary primarily defines:

* Structure inspection
* Header discovery
* Unit discovery
* Player discovery
* Manual concept mapping
* Warnings
* FMTRX alias recommendations

Generic spreadsheets must not receive false confidence merely because a header resembles a known concept.

---

## Responsibilities

Platform Dictionaries are responsible for:

* Understanding source platforms
* Preserving source definitions
* Recognizing files
* Recommending canonical translations
* Translating controlled values
* Identifying ambiguity
* Protecting non-equivalence
* Providing warnings
* Supporting deterministic certification
* Preserving version history

They are not responsible for:

* Defining canonical meaning
* Approving translations
* Persisting data
* Creating sessions
* Creating events
* Calculating statistics
* Updating Player DNA
* Producing AI recommendations

---

## Explicit Prohibitions

The following are prohibited:

* Allowing a Platform Dictionary to redefine a Baseball Concept
* Mapping fields solely because their labels look similar
* Hiding ambiguity from the coach
* Treating unknown units as known
* Converting unavailable values to zero
* Forcing unknown controlled values into the nearest known value
* Granting one vendor architectural preference
* Silently changing historical dictionary behavior
* Removing retired dictionaries needed for reproducibility
* Encoding persistence logic inside a Platform Dictionary
* Creating platform-specific branches throughout the core Translation Engine
* Treating proprietary scores as canonical concepts without certification

---

## Chapter Certification

This chapter is complete when:

* Every supported platform has a versioned dictionary.
* Every recognized source field has a documented source meaning.
* Units and controlled values are explicit.
* Candidate canonical mappings are evidence-based.
* Protected non-equivalences are documented and tested.
* Missing values remain distinguishable from zero.
* Platform-specific scores remain clearly identified.
* Platform recognition is reviewable.
* Historical dictionary versions remain reproducible.
* New platforms can be added without redesigning the core Translation Engine.
* Platform Dictionaries translate into the FMTRX Baseball Dictionary without redefining it.

With the canonical language and source dictionaries established, the Backbone can next define the system that executes those translations: the FMTRX Translation Engine.
