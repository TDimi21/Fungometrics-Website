# Chapter 2 — Constitutional Invariants

## The Permanent Laws of the Data Hub

A vision without boundaries eventually loses its identity.

For that reason, the Data Hub is governed by permanent architectural laws known as the Constitutional Invariants.

These are not implementation details.

They are not coding standards.

They are not user-interface preferences.

They are the laws that define what the Data Hub is permitted to become.

Every service, workflow, database structure, API, report, benchmark, Player DNA model, and AI capability must comply with these invariants.

If an implementation conflicts with an invariant, the implementation must change.

The invariant does not.

Changes to these invariants require an explicit architecture review and approval. They may not be altered incidentally during implementation.

---

## Invariant 1 — Data Integrity Chain

Every baseball fact must pass through every required stage in order.

No later stage may bypass an earlier stage.

```text
Raw Source Data
        ↓
Platform Dictionary
        ↓
FMTRX Baseball Dictionary
        ↓
Translation Review
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
        ↓
Statistics Projection
        ↓
Player Record
        ↓
Benchmarks
        ↓
Player DNA
        ↓
AI
```

### Required Behavior

Every downstream record must be traceable through this chain.

### Allowed

```text
TrackMan Source File
→ Platform Translation
→ Translation Review
→ Approved Snapshot
→ Import Batch
→ External Session
→ Canonical Events
```

### Prohibited

```text
TrackMan CSV
→ Statistics
```

```text
Rapsodo Workbook
→ Player Profile
```

```text
Blast Export
→ AI Recommendation
```

---

## Invariant 2 — Rule of One Meaning

Every Baseball Concept has exactly one canonical meaning inside FMTRX.

Different platform fields may share a canonical concept only when semantic equivalence has been verified.

Similar wording is not sufficient.

### Certified Example

```text
TrackMan: ExitSpeed
HitTrax: Velo
Blast: Exit Velocity
        ↓
FMTRX: Hitting Exit Velocity
```

### Protected Non-Equivalence

```text
Pitch Release Velocity
≠
Inbound Pitch Velocity
```

```text
Bat Speed
≠
Exit Velocity
```

```text
Spin Direction
≠
Spin Axis
```

```text
Projected Distance
≠
Measured Carry Distance
```

A canonical concept may never become a container for multiple approximate meanings.

---

## Invariant 3 — Source Preservation

Original source information is never destroyed.

Every translated value must permanently preserve enough provenance to explain:

* Source platform
* Source product or device, when known
* Source file
* Source worksheet, when applicable
* Source row or record
* Original header
* Original value
* Original unit
* Original controlled value
* Canonical translation
* Platform Dictionary version
* Baseball Dictionary version
* Mapping version
* Translation version
* Approval identity
* Approval timestamp
* Import Batch, after persistence

The translation exists alongside the source.

It never replaces it.

---

## Invariant 4 — Translation Before Persistence

No imported baseball information becomes durable until the Translation Review is explicitly approved.

Before approval, FMTRX may perform temporary inspection and translation work.

### Allowed Before Approval

* Platform detection
* File inspection
* File Structure configuration
* Destination selection
* Player Mapping
* Column Mapping
* Controlled-value translation
* Warnings
* Recommendations
* Normalized sample records
* Translation Review

### Prohibited Before Approval

* Durable Translation Snapshot
* Import Batch
* External Session
* Canonical Event
* Canonical Metric
* Statistics update
* Player profile update
* Benchmark inclusion
* Player DNA update
* AI consumption

No hidden or partial persistence is permitted.

---

## Invariant 5 — Source Independence

Once information has been translated and persisted canonically, the athlete’s history must no longer depend upon the originating platform.

The source remains permanently attributable through provenance, but the canonical meaning belongs to FMTRX.

A platform may:

* Change its export format
* Change its terminology
* Change ownership
* End support
* Become unavailable
* Be replaced by another technology

The athlete’s canonical FMTRX history must remain intact.

---

## Invariant 6 — Reproducibility

Given identical:

* Source bytes
* File Structure configuration
* Destination
* Player Mapping
* Column Mapping
* Controlled-value mappings
* Platform Dictionary version
* Baseball Dictionary version
* Translation Engine version

FMTRX must produce an identical Translation Review and identical approved Translation Snapshot.

Translation must be deterministic.

Randomness, time-dependent interpretation, and uncontrolled external inference are prohibited.

---

## Invariant 7 — Platform Neutrality

Platforms contribute measurements.

They do not receive architectural privilege after translation.

Once two values are certified as representing the same canonical concept, their canonical meaning is equal.

Their provenance remains different.

Platform reputation, price, popularity, or market position must not silently alter canonical meaning.

Quality, confidence, device limitations, measurement method, and source classification may be represented explicitly, but platform identity alone may not create hidden preference.

---

## Invariant 8 — Context Matters

Labels alone never establish meaning.

Meaning is determined through the combination of:

* Source platform
* Source field definition
* Destination
* Event context
* Units
* Source data type
* Controlled values
* Platform Dictionary
* Baseball Dictionary
* Semantic certification
* Coach approval

For example, the label `Velocity` may represent:

* Hitting Exit Velocity
* Pitch Release Velocity
* Inbound Pitch Velocity
* Throwing Velocity
* Weighted Ball Velocity
* Sprint Velocity

The label itself is insufficient.

---

## Invariant 9 — Non-Destructive Evolution

FMTRX never silently rewrites baseball history.

The system may improve its understanding as:

* Platform definitions become clearer
* New research becomes available
* Dictionary concepts are refined
* Translation errors are discovered
* Better mapping rules are certified

However, prior approved translations must remain explainable.

A later interpretation may:

* Supersede a previous translation
* Create a new Translation Snapshot
* Trigger an authorized replay
* Trigger a new projection version
* Mark a previous interpretation as deprecated

It may not erase or silently mutate the original approved history.

---

## Constitutional Contract

Every baseball fact inside FMTRX must remain:

### Explainable

FMTRX can explain why the value exists and how it was interpreted.

### Reproducible

FMTRX can recreate the translation from the same source and versions.

### Attributable

FMTRX can identify where the value originated and who approved it.

### Reversible

FMTRX can safely exclude, roll back, supersede, or replay the persisted result without destroying the historical record.

---

## Responsibilities

These invariants govern:

* File inspection
* Platform detection
* FMTRX Templates
* Generic spreadsheets
* Platform Dictionaries
* Baseball Dictionary concepts
* Player Mapping
* Column Mapping
* Controlled-value mapping
* Translation Review
* Translation Snapshots
* Import Batches
* External Sessions
* Canonical Events
* Canonical Metrics
* Provenance
* Duplicate Detection
* Rollback
* Replay
* Statistics Projection
* Benchmarks
* Player DNA
* AI

No downstream chapter may weaken or redefine them.

---

## Explicit Prohibitions

The following are constitutionally prohibited:

* Writing platform files directly into statistics
* Writing platform files directly into athlete profiles
* Skipping Translation Review
* Creating durable data for unmapped players
* Destroying original source values
* Using one canonical concept for two different meanings
* Inferring equivalence from labels alone
* Silently changing approved historical translations
* Allowing AI to consume raw platform files
* Embedding platform-specific terminology into Player DNA
* Granting hidden preference to one platform after translation
* Persisting partial imports without explicit approval
* Reprocessing historical records without versioning and auditability

---

## Chapter Certification

This chapter is complete when:

* Every invariant is individually testable.
* Every later chapter identifies the invariants it implements.
* No invariant contradicts another.
* No later chapter silently creates an exception.
* Proposed architectural changes can be evaluated against these laws.
* Any modification to an invariant requires a documented architecture review.

With the permanent laws established, the Backbone must next define the official language used to describe the system.
