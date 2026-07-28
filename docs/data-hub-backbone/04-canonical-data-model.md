# Chapter 4 — The Canonical Data Model

## One Structure

Translation alone is not enough.

Knowing what a baseball measurement means does not determine where it belongs.

The Canonical Data Model defines the permanent logical structure that every approved translated baseball fact must occupy inside FMTRX.

Regardless of whether information originates from TrackMan, HitTrax, Rapsodo, Blast Motion, an FMTRX Template, a coach spreadsheet, or a future platform, approved translated observations must conform to one canonical model.

The model is independent of:

* Database engine
* Application framework
* Programming language
* Platform vendor
* File format
* User interface

It defines the logical shape of permanent baseball history.

---

## Constitutional Relationship

The Canonical Data Model implements the Constitutional Invariants.

It protects:

* The Data Integrity Chain through required ownership relationships
* The Rule of One Meaning through Baseball Concept references
* Source Preservation through provenance
* Translation Before Persistence through the required Translation Snapshot
* Source Independence through canonical representation
* Reproducibility through versioned lineage
* Platform Neutrality through platform-independent objects
* Context Matters through Session and Event context
* Non-Destructive Evolution through immutable facts and versioned derived systems

---

## Scope

This chapter defines the logical objects that exist at and after the approved handoff from Translation into Persistence.

It defines:

* Athlete ownership
* Translation Snapshot handoff
* Import Batch transaction
* External Session context
* Canonical Event occurrence
* Canonical Metric measurement
* Provenance relationships
* Immutable facts
* Derived information boundaries

It does not yet define:

* Physical database tables
* Column types
* Laravel models
* API endpoints
* Queue jobs
* Authorization policies
* Persistence implementation
* Statistics formulas
* Benchmark formulas
* Player DNA algorithms
* AI behavior

Those will be governed by later chapters.

---

## The Translation-to-Persistence Handoff

The approved boundary is:

```text
Approved Translation Review
        ↓
Immutable Translation Snapshot
        ↓
Import Batch
        ↓
External Session
        ↓
Canonical Event
        ↓
Canonical Metric
```

The Translation Snapshot is the final product of the Translation Engine.

The Import Batch is the first transaction of the Persistence Engine.

No persistence object may exist without an approved Translation Snapshot.

---

## The Canonical Hierarchy

The canonical model has two complementary logical views.

The persistence transaction lineage is:

```text
Approved Translation Snapshot
        ↓
Import Batch
        ├── External Session — Athlete A
        │       └── Canonical Event
        │               └── Canonical Metric
        │
        ├── External Session — Athlete B
        │       └── Canonical Event
        │               └── Canonical Metric
        │
        └── External Session — Athlete C
                └── Canonical Event
                        └── Canonical Metric
```

The athlete-centered permanent development-history view is:

```text
Athlete
    └── External Sessions
            └── Canonical Events
                    └── Canonical Metrics
```

The Import Batch view explains persistence transaction lineage.

The Athlete view explains permanent development-history ownership.

One Import Batch may contain multiple approved mapped Athletes. The Import Batch represents one approved persistence transaction and does not belong to one Athlete.

Athlete ownership begins at the External Session. Every External Session belongs to exactly one Import Batch and exactly one Athlete.

Each object has one primary responsibility.

---

## Athlete

### Purpose

Represents the person whose permanent development history is being preserved.

### Responsibilities

The Athlete:

* Provides permanent identity
* Owns a continuous development history
* May be associated with multiple teams and organizations over time
* Receives approved canonical records
* Serves as the subject for statistics, benchmarks, Player DNA, and AI

### Invariants

* Imported information may only be persisted for source players mapped to an Athlete.
* A source player marked Not Importing creates no Athlete data.
* Athlete ownership begins at the External Session and continues through its Canonical Events and Canonical Metrics.
* Team or organization changes must not fragment one Athlete into multiple histories.
* Athlete identity must remain separate from platform identity.

### Prohibitions

* Automatically creating Athlete records for every source player without approval
* Treating a platform player ID as the permanent FMTRX Athlete identity
* Assigning imported records to unmapped players
* Allowing an External Session, Canonical Event, or Canonical Metric to cross Athlete ownership
* Making Team the permanent owner of the Athlete’s history

---

## Translation Snapshot

### Purpose

Represents exactly what the coach approved at the end of Translation Review.

### Responsibilities

The Translation Snapshot preserves:

* Source checksum
* Source identity
* Platform
* File Structure
* Destination
* Player Mapping
* Column Mapping
* Controlled-value mappings
* Units
* Exclusions
* Warnings
* Normalized interpretation
* Platform Dictionary version
* Baseball Dictionary version
* Translation Engine version
* Approval identity
* Approval timestamp

### Invariants

* The Snapshot is immutable after approval.
* One Import Batch must reference exactly one approved Snapshot.
* Revisions require a new Snapshot.
* A Snapshot may exist before persistence begins.
* Snapshot contents must be sufficient to reproduce the approved Translation Review.

### Prohibitions

* Editing an approved Snapshot in place
* Allowing persistence to use unapproved transient mappings
* Replacing prior Snapshot history when a mapping changes
* Storing only a pointer to mutable current dictionary rules without version capture

---

## Import Batch

### Purpose

Represents one controlled persistence transaction from one approved Translation Snapshot.

### Responsibilities

The Import Batch records:

* Snapshot reference
* Initiating user
* Organization context
* Start and completion timestamps
* Persistence status
* Source platform
* Destination
* Number of eligible players
* Number of excluded players
* Number of sessions
* Number of events
* Number of metrics
* Warnings
* Failures
* Rollback status
* Replay lineage

### Invariants

* Every Import Batch references exactly one approved Translation Snapshot.
* An Import Batch is a transaction-level and provenance object, not an athlete-owned object.
* One Import Batch may include multiple approved mapped Athletes.
* An Import Batch may produce records only for approved mapped players.
* Import Batch execution must be auditable.
* Import Batch results must be reproducible from the Snapshot and persistence version.
* A failure must not create unexplained partial history.
* Retry and replay behavior must be explicit.

### Prohibitions

* Persisting from a live, mutable Translation Review
* Combining unrelated Snapshots into one Import Batch
* Assigning an Import Batch to one Athlete when the approved transaction covers multiple Athletes
* Creating imported records outside an Import Batch
* Silently ignoring partial failures
* Deleting the Import Batch after rollback

---

## External Session

### Purpose

Represents the activity context reconstructed from approved external information.

Examples include:

* TrackMan Bullpen
* HitTrax Cage Session
* Rapsodo Pitching Practice
* Blast Batting Practice
* Imported Strength Assessment
* Imported Mobility Assessment

### Responsibilities

An External Session preserves:

* Athlete
* Import Batch
* Destination
* Session date and time
* Platform
* Source session identifiers
* Venue, when available
* Team and organization context, when available
* Session labels
* Session-level metadata
* Provenance

### Invariants

* Every External Session belongs to exactly one Import Batch.
* Every External Session belongs to exactly one Athlete.
* Every imported Canonical Event belongs to exactly one External Session.
* External Session context may help interpret an event but may not redefine a Baseball Concept.
* Session grouping rules must be deterministic and versioned.
* A Session must not contain events belonging to different Athletes.

### Prohibitions

* Creating External Sessions before approval
* Creating External Sessions without an Import Batch
* Combining multiple Athletes into one athlete-owned session
* Creating an External Session for a Not Importing player
* Using a platform-specific session structure as the canonical model
* Storing aggregate statistics as the Session’s canonical facts

---

## Canonical Event

### Purpose

Represents one permanent, indivisible athlete-development occurrence.

Examples include:

* One pitch
* One batted-ball event
* One swing-sensor event
* One throw
* One sprint
* One jump
* One strength test event
* One mobility measurement event
* One recovery observation
* One assessment event

### Responsibilities

A Canonical Event preserves:

* Athlete
* External Session
* Event type
* Event order
* Event timestamp, when available
* Source record identity
* Event-level context
* Event-level provenance
* Attached Canonical Metrics

### Invariants

* Every Canonical Event belongs to exactly one External Session.
* Every Canonical Event belongs to exactly one Athlete.
* A Canonical Event’s Athlete must be the same Athlete that owns its External Session.
* An Event represents an occurrence, not an aggregate.
* Event identity and deduplication rules must be deterministic.
* Event type must be canonical rather than vendor-specific.
* Source record lineage must remain available.

### Prohibitions

* Storing a season average as a Canonical Event
* Storing a source row without determining its event meaning
* Combining multiple independent occurrences into one Event
* Creating Events for Not Importing players
* Creating Events without an approved Snapshot and Import Batch
* Using raw vendor event names as the canonical event taxonomy without translation

---

## Canonical Metric

### Purpose

Represents one measured or observed canonical value attached to a Canonical Event.

Examples include:

```text
Exit Velocity = 98.4 mph
```

```text
Launch Angle = 24 degrees
```

```text
Pitch Release Velocity = 91.2 mph
```

```text
Spin Rate = 2,380 rpm
```

### Responsibilities

A Canonical Metric preserves:

* Canonical Event
* Baseball Concept
* Canonical value
* Canonical unit
* Original value
* Original unit
* Original header
* Source field identity
* Measurement classification
* Quality or confidence metadata, when applicable
* Provenance

### Invariants

* Every Canonical Metric belongs to exactly one Canonical Event.
* Every Canonical Metric references exactly one Baseball Concept.
* A Canonical Metric inherits Athlete ownership through its Canonical Event and may not cross that ownership boundary.
* The value must satisfy the Baseball Concept’s data type and validation rules.
* Unit conversion must be deterministic and traceable.
* Missing values remain unavailable rather than becoming zero.
* A source-specific score may remain source-specific if no certified canonical equivalence exists.
* Two semantically different meanings may not share one concept.

### Prohibitions

* Storing raw platform field names as canonical concepts
* Converting unavailable values to zero
* Attaching a Metric to multiple Events
* Storing a Metric without provenance
* Treating projected, estimated, tagged, and measured values as identical without explicit classification
* Treating HitTrax inbound Pitch velocity as Pitch Release Velocity
* Treating Blast Bat Speed as Exit Velocity
* Treating projected distance as measured carry

---

## Provenance Relationship

Provenance must connect every permanent imported metric back through the full chain:

```text
Canonical Metric
        ↓
Canonical Event
        ↓
External Session
        ↓
Import Batch
        ↓
Translation Snapshot
        ↓
Source Record
        ↓
Source File
        ↓
Source Platform
```

The system must always be able to explain:

* Which source value created this Metric?
* Which source row or record contained it?
* What did the source call it?
* What was the source unit?
* Which mapping translated it?
* Which dictionary versions were used?
* Who approved it?
* Which Import Batch persisted it?
* Whether it has been rolled back, superseded, or replayed?

---

## Relationship Cardinality

The logical relationships are:

* One approved Translation Snapshot may produce one or more governed Import Batch attempts, subject to future replay rules.
* One Import Batch references exactly one approved Translation Snapshot.
* One Import Batch may include multiple approved mapped Athletes.
* One Import Batch may create many External Sessions.
* Every External Session belongs to exactly one Import Batch.
* Every External Session belongs to exactly one Athlete.
* One Athlete may have multiple External Sessions within the same Import Batch when deterministic session-grouping rules require it.
* One External Session may contain many Canonical Events.
* Every Canonical Event belongs to exactly one External Session and one Athlete.
* One Canonical Event may contain many Canonical Metrics.
* Every Canonical Metric belongs to exactly one Canonical Event.
* Every Canonical Metric references exactly one Baseball Concept.

Exact replay cardinality and active-state rules will be defined in the Persistence chapters.

---

## Immutable Facts and Derived Information

The Canonical Data Model separates historical facts from interpretation.

### Immutable or Historically Preserved Facts

* Source File
* Source Record
* Translation Snapshot
* Import Batch history
* External Session
* Canonical Event
* Canonical Metric
* Provenance
* Approval
* Rollback and replay history

These records may be superseded, excluded, or marked inactive through governed lifecycle actions.

They may not be silently rewritten.

### Derived Information

* Statistics
* Percentiles
* Benchmarks
* Ratings
* Trends
* Development summaries
* Player DNA
* AI insights
* Projections
* Recommendations

Derived information may be recalculated.

It must always identify the canonical facts and rule versions from which it was derived.

---

## Lifecycle

The high-level lifecycle is:

```text
Source File Selected
        ↓
File Inspected
        ↓
Platform Identified
        ↓
Destination Selected
        ↓
File Structure Resolved
        ↓
Players Mapped
        ↓
Columns Mapped
        ↓
Controlled Values Mapped
        ↓
Translation Reviewed
        ↓
Coach Approves
        ↓
Translation Snapshot Created
        ↓
Import Batch Executes
        ↓
External Sessions Created
        ↓
Canonical Events Created
        ↓
Canonical Metrics Created
        ↓
Statistics Projected
        ↓
Benchmarks Applied
        ↓
Player DNA Updated
        ↓
AI Consumes Approved Canonical Information
```

This chapter defines the logical flow.

Later chapters will define exact state machines and failure behavior.

---

## Example — TrackMan Batted Ball

Source:

```text
Platform: TrackMan
Header: ExitSpeed
Value: 101.7
Unit: mph
Player: Tom Dimitroff
Session: 2026-07-15
```

Approved translation:

```text
Source Player
→ FMTRX Athlete: Thomas Dimitroff

ExitSpeed
→ Baseball Concept: Hitting Exit Velocity
```

Canonical structure:

```text
Athlete
└── Thomas Dimitroff

Import Batch
└── Approved TrackMan import

External Session
└── Cage session on 2026-07-15

Canonical Event
└── Batted-ball event 14

Canonical Metric
└── Hitting Exit Velocity = 101.7 mph
```

The Canonical Metric retains provenance to:

* TrackMan
* Original file
* Original row
* `ExitSpeed`
* `101.7`
* `mph`
* Dictionary versions
* Translation Snapshot
* Import Batch
* Approving coach

---

## Example — Blast Motion

Source:

```text
Platform: Blast Motion
Header: Bat Speed
Value: 71.4
Unit: mph
```

Canonical result:

```text
Baseball Concept: Bat Speed
Canonical Value: 71.4 mph
```

It must not become Exit Velocity.

If the source file contains no Exit Velocity, Exit Velocity remains unavailable.

It must not be stored as zero.

---

## Example — Excluded Player

A spreadsheet contains 20 players.

The coach maps two players and leaves eighteen as Not Importing.

Canonical result:

* Records may be created for the two approved mapped Athletes.
* No Athlete is created for the other eighteen.
* No External Session is created for them.
* No Canonical Event is created for them.
* No Canonical Metric is created for them.
* The Translation Snapshot preserves that they were intentionally excluded.

---

## Explicit Prohibitions

The following are prohibited:

* Metrics existing without Events
* Events existing without Sessions
* Sessions existing without Import Batches
* Import Batches existing without approved Translation Snapshots
* Platform fields being stored directly as Canonical Metrics
* Statistics being stored as source-of-truth Canonical Events
* Derived ratings being treated as historical measurements
* Unmapped players receiving imported records
* Platform-specific terminology becoming the canonical model
* Missing measurements becoming zero
* Silent mutation of approved or persisted history
* Downstream systems bypassing the canonical hierarchy

---

## Chapter Certification

This chapter is complete when:

* Every approved translated baseball fact has a defined home.
* Every canonical object has one primary responsibility.
* The Translation-to-Persistence handoff is unambiguous.
* Required ownership relationships are explicit.
* Immutable facts are separated from derived intelligence.
* Provenance can trace every Metric to its Source File.
* Unmapped players cannot enter the canonical model.
* Missing values cannot become false measurements.
* The model supports existing and future platforms without platform-specific structural redesign.
* Later persistence chapters can implement the model without redefining it.

With the canonical structure established, the Backbone can next define the concepts that populate Canonical Metrics: the FMTRX Baseball Dictionary.
