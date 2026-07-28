# Chapter 8 — Certification & Governance

## Proving and Protecting the Translation Engine

The FMTRX Translation Engine is only valuable if its translations can be trusted.

Trust requires more than working code.

It requires evidence that:

- Source files are recognized correctly
- File structures are interpreted correctly
- Players are mapped correctly
- Source fields are translated correctly
- Controlled values are preserved correctly
- Units are handled correctly
- Semantic equivalence is proven
- Protected distinctions remain protected
- Translation Reviews are reproducible
- Approved Translation Snapshots are deterministic
- Future changes do not silently alter historical meaning

Certification proves that the Translation Engine behaves correctly.

Governance ensures that it continues to behave correctly as FMTRX evolves.

Together, Certification and Governance formally close Era 1.

---

## Constitutional Relationship

Certification and Governance enforce every Constitutional Invariant:

- Data Integrity Chain
- Rule of One Meaning
- Source Preservation
- Translation Before Persistence
- Source Independence
- Reproducibility
- Platform Neutrality
- Context Matters
- Non-Destructive Evolution

No Translation Engine component, Baseball Concept, Platform Dictionary, controlled-value rule, or certification fixture may bypass these laws.

---

## Purpose

Certification exists to prove that the Translation Engine produces correct, deterministic, explainable, attributable, and reproducible translations.

Governance exists to control how certified behavior may change.

This chapter defines:

- Certification levels
- Certification fixtures
- Semantic equivalence certification
- Protected non-equivalence
- Deterministic regression testing
- Platform Dictionary certification
- Baseball Concept governance
- Controlled-value governance
- Translation Snapshot certification
- Versioning requirements
- Architecture Review requirements
- Freeze rules
- Era 1 completion criteria

---

## Scope

This chapter governs:

- File recognition
- File inspection
- File Structure detection
- Player Mapping
- Column Mapping
- Controlled-Value Mapping
- Unit translation
- Destination compatibility
- Warning behavior
- Exclusion behavior
- Normalized sample generation
- Translation Review
- Approval
- Translation Snapshot generation
- Baseball Dictionary changes
- Platform Dictionary changes
- Translation Engine maintenance

This chapter does not authorize:

- Persistence
- Import Batch implementation
- External Session creation
- Canonical Event creation
- Canonical Metric creation
- Statistics Projection
- Benchmarks
- Player DNA
- AI

Those belong to later Eras.

---

# Certification Principles

## Evidence Over Assumption

No translation is certified because a label looks familiar.

Certification must be supported by evidence such as:

- Official platform documentation
- Official field dictionaries
- Real export files
- Verified sample values
- Units
- Measurement method
- Event context
- Controlled values
- Source structure
- Cross-platform comparison
- Deterministic fixtures
- Architecture review

---

## Real Files Over Synthetic Guesswork

Certification should prioritize real exports whenever available.

Synthetic fixtures may supplement testing, but they must not replace real source evidence for known platforms.

The certification suite should include real or verified representative exports for:

- TrackMan
- HitTrax
- Rapsodo
- Blast Motion
- FMTRX Templates
- Generic Spreadsheets

---

## Determinism

Certification must prove that identical inputs and versions produce identical outputs.

Given identical:

- Source bytes
- Platform selection
- Destination
- File Structure
- Player Mapping
- Column Mapping
- Controlled-Value Mapping
- Platform Dictionary version
- Baseball Dictionary version
- Translation Engine version

The system must produce identical:

- Inspection results
- Source-player lists
- Source-field lists
- Mapping recommendations
- Warnings
- Exclusions
- Normalized samples
- Translation Reviews
- Translation Snapshots

---

## Preservation

Certification must prove that the source is never lost.

Every certified translation must preserve:

- Source file identity
- Source checksum
- Source worksheet
- Source row or record
- Original header
- Original value
- Original unit
- Original controlled value
- Canonical mapping
- Dictionary versions
- Approval identity
- Approval timestamp

---

# Certification Levels

## Field-Level Certification

Proves that one source field is correctly understood and mapped.

Example:

```text
TrackMan: ExitSpeed
→ FMTRX: hitting.exit_velocity
````

Field-level certification verifies:

* Source definition
* Source unit
* Event context
* Canonical concept
* Semantic equivalence
* Validation
* Protected distinctions

---

## Controlled-Value Certification

Proves that one finite source value translates correctly.

Example:

```text
FF
→ Four-Seam Fastball
```

Controlled-value certification verifies:

* Source meaning
* Canonical value
* Context
* Unknown-value behavior
* Version
* Regression coverage

---

## File-Structure Certification

Proves that the Translation Engine correctly interprets a known file layout.

Examples:

* Events in rows
* Players in rows
* Players in columns
* Worksheet per player
* Metadata before header row
* Single-player session

---

## Platform Certification

Proves that a Platform Dictionary correctly recognizes and translates supported exports.

Platform Certification includes:

* File recognition
* Header recognition
* Unit interpretation
* Controlled values
* Destination compatibility
* Warnings
* Protected non-equivalence
* Fixture coverage
* Deterministic Translation Review

---

## Workflow Certification

Proves that the complete Translation Engine workflow behaves correctly from source file through Translation Snapshot.

Workflow Certification includes:

```text
Platform
    ↓
File
    ↓
Destination
    ↓
File Structure
    ↓
Player Mapping
    ↓
Column Mapping
    ↓
Controlled-Value Mapping
    ↓
Translation Review
    ↓
Approval
    ↓
Translation Snapshot
```

---

# Certification Fixtures

A Certification Fixture is a deterministic, version-controlled source example used to prove Translation Engine behavior.

Each fixture should define:

* Fixture identifier
* Platform
* File type
* Source file or generated file
* Expected Platform detection
* Expected File Structure
* Expected source players
* Expected source fields
* Expected mappings
* Expected controlled-value translations
* Expected warnings
* Expected exclusions
* Expected normalized samples
* Expected Snapshot representation
* Required dictionary versions
* Required Translation Engine version

---

## Fixture Categories

The suite should cover:

### Generic Spreadsheet Fixtures

* Players in rows
* Players in columns
* Events in rows
* Worksheet per player
* Single-player session
* Metadata before headers
* Unknown columns
* Duplicate source players
* Missing players
* Unknown units
* Bad spreadsheets
* FMTRX Templates

### Known Platform Fixtures

* TrackMan
* HitTrax
* Rapsodo
* Blast Motion

### Failure Fixtures

* Unsupported file type
* Corrupted spreadsheet
* Missing header
* Ambiguous platform
* Unknown required unit
* Invalid structure
* Unresolved player
* Conflicting mappings
* Unknown required controlled value
* Destination incompatibility

---

# Semantic Equivalence Certification

Semantic equivalence must be explicitly proven.

Certified examples include:

```text
TrackMan: ExitSpeed
HitTrax: Velo
Blast: Exit Velocity
        ↓
FMTRX: hitting.exit_velocity
```

```text
TrackMan: Angle
HitTrax: LA
Blast: Launch Angle
        ↓
FMTRX: hitting.launch_angle
```

```text
TrackMan: Direction
HitTrax: Horiz. Angle
        ↓
FMTRX: hitting.spray_angle
```

```text
TrackMan: RelSpeed
Rapsodo: velocity
        ↓
FMTRX: pitching.release_velocity
```

Certification must compare canonical keys and semantic identity.

Tests must not depend on hardcoded database UUID values when stable canonical keys are available.

---

# Protected Non-Equivalence

Some distinctions are too important to leave implicit.

The certification suite must permanently protect the following:

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

```text
True Spin
≠
Total Spin
```

A regression that collapses one of these distinctions is a certification failure.

---

# Platform Certification Requirements

A Platform Dictionary may be marked Certified only when:

* Real or verified representative exports exist
* Recognition rules are documented
* Source fields are defined
* Source units are defined
* Controlled values are defined
* Candidate mappings are documented
* Semantic equivalence is proven
* Protected non-equivalence is documented
* Unknown-field behavior is defined
* Unknown-unit behavior is defined
* Missing-value behavior is defined
* Destination compatibility is documented
* Certification fixtures pass
* Regression tests pass
* Architecture review is complete

---

# Translation Review Certification

Certification must prove that Translation Review displays:

* Source Summary
* Player Translation
* Concept Translation
* Controlled-Value Translation
* Warnings
* Not Importing Summary
* Normalized Sample Records
* Dictionary versions
* Translation Engine version

It must also prove that:

* Excluded players remain visible
* Excluded fields remain visible
* Warnings are not hidden
* Blocking errors prevent approval
* Confidence is not presented as certainty
* Preview records are not persisted

---

# Translation Snapshot Certification

Certification must prove that the Snapshot captures the exact approved state.

The Snapshot must include:

* Source checksum
* Platform
* Platform recognition evidence
* File Structure
* Destination
* Player Mapping
* Column Mapping
* Controlled-Value Mapping
* Units
* Conversion rules
* Exclusions
* Warnings
* Warning acknowledgments
* Normalized interpretation
* Platform Dictionary version
* Baseball Dictionary version
* Translation Engine version
* Approval identity
* Approval timestamp

The Snapshot must be:

* Immutable
* Deterministic
* Reproducible
* Explainable
* Attributable

---

# Regression Suite

Every proposed change to the Translation system must run the full applicable regression suite.

This includes changes to:

* Platform Dictionaries
* Baseball Concepts
* Aliases
* Units
* Controlled values
* Recognition rules
* File Structure rules
* Warning rules
* Compatibility rules
* Translation logic
* Review generation
* Snapshot generation

A change may not be certified if it causes unexplained changes to existing fixture outputs.

Expected changes must be:

* Documented
* Versioned
* Reviewed
* Approved
* Reflected in updated fixtures

---

# Deterministic Fixture Generation

Generated fixtures must be deterministic.

The same generator version and inputs must produce identical fixture bytes or identical normalized source content.

Generated fixture metadata should include:

* Generator version
* Seed, if a seed exists
* Platform
* Layout
* Expected row count
* Expected player count
* Expected field count
* Expected warnings
* Expected mappings

Random fixture generation without reproducible control is prohibited.

---

# Certification Manifest

The certification suite should be manifest-driven.

A fixture manifest may define:

* Fixture path
* Platform
* File type
* Layout
* Expected detection
* Expected mappings
* Expected warnings
* Expected exclusions
* Expected status
* Applicable test groups

The manifest becomes the test contract.

Adding a fixture should not require rewriting the entire test harness.

---

# Backend Certification

Backend certification should verify:

* File inspection
* Platform detection
* Structure normalization
* Player discovery
* Field discovery
* Mapping recommendation
* Unit interpretation
* Controlled-value translation
* Warning generation
* Exclusion handling
* Deterministic review payload
* Deterministic Snapshot payload

Backend tests must not create production records.

---

# Frontend Certification

Frontend certification should verify:

* Workflow sequence
* Conditional File Structure step
* Player Mapping display
* Not Importing behavior
* Column Mapping selector
* Grouped Baseball Domains
* Search behavior
* Warning visibility
* Blocking behavior
* Translation Review sections
* Approval behavior
* Snapshot handoff request

Frontend certification must not authorize Persistence.

---

# Baseball Concept Governance

A new Baseball Concept requires:

1. Proposed canonical key
2. Canonical name
3. Official definition
4. Domain
5. Data type
6. Unit family
7. Canonical unit
8. Validation rules
9. Compatibility
10. Aliases
11. Examples
12. Semantic distinction from existing concepts
13. Certification evidence
14. Lifecycle status
15. Architecture review

A new concept must not be created merely because a new source header appears.

The source field may remain unsupported or source-specific until the canonical meaning is established.

---

# Platform Dictionary Governance

A Platform Dictionary change requires:

* Version increment
* Change summary
* Evidence
* Fixture updates
* Regression testing
* Historical behavior review
* Architecture review when meaning changes

Minor additions such as verified aliases may use lighter review.

Changes affecting semantic meaning require full architecture review.

---

# Controlled-Value Governance

A controlled-value change requires:

* Source value
* Canonical value
* Context
* Evidence
* Version
* Unknown behavior
* Regression fixture
* Review

Controlled values may not be silently merged or renamed when historical records depend on them.

---

# Versioning Governance

Every approved Translation Snapshot must capture:

* Platform Dictionary version
* Baseball Dictionary version
* Translation Engine version

Version changes must be intentional.

Historical translation behavior must remain reproducible.

A current dictionary may not be substituted silently when replaying a historical Snapshot.

---

# Architecture Review

An Architecture Review is required when a proposed change affects:

* Constitutional Invariants
* Data Integrity Chain
* Rule of One Meaning
* Canonical meaning
* Translation workflow order
* Approval boundary
* Snapshot immutability
* Source Preservation
* Determinism
* Platform Neutrality
* Historical translation behavior
* Translation-to-Persistence boundary

An Architecture Review is different from ordinary code review.

It must document:

* Proposed change
* Reason
* Affected chapters
* Affected invariants
* Migration or versioning impact
* Historical compatibility
* Certification impact
* Approval decision

---

# Translation Engine Freeze

Approval of this specification freezes the Translation Engine architecture. Formal Certified and Complete status remains pending until the required Era 1 certification evidence and regression gates pass.

Allowed changes include:

* Verified bug fixes
* New Platform Dictionaries
* Missing Baseball Concepts
* New certified controlled values
* Certification improvements
* Performance improvements that do not alter meaning
* Explicitly approved architecture changes

Routine maintenance may not:

* Reorder the constitutional workflow
* Skip Translation Review
* Weaken approval
* Remove provenance
* Merge Translation and Persistence
* Introduce hidden imports
* Introduce platform favoritism
* Alter historical translation behavior without versioning
* Allow raw source files to feed downstream intelligence

---

# Governance Roles

The system should distinguish responsibilities such as:

## Architecture Owner

Approves constitutional and semantic changes.

## Dictionary Maintainer

Researches and proposes Baseball Concept and Platform Dictionary updates.

## Certification Maintainer

Maintains fixtures, manifests, regression tests, and deterministic generators.

## Reviewer

Verifies evidence, invariants, compatibility, and regression impact.

## Implementer

Builds approved behavior without redefining the architecture.

One person may hold multiple roles in a small team, but the responsibilities remain distinct.

---

# Change Classification

Changes should be classified as:

## Documentation-Only

Clarifies language without changing behavior.

## Certification Improvement

Adds fixtures or tests without changing meaning.

## Compatible Dictionary Addition

Adds a verified field, alias, unit, or controlled value without changing existing behavior.

## Bug Fix

Corrects behavior that violates the approved specification.

## Semantic Change

Changes canonical interpretation or protected distinctions.

## Constitutional Change

Changes an invariant or core architecture.

Semantic and Constitutional changes require explicit Architecture Review.

---

# Explicit Prohibitions

The following are prohibited:

* Certifying mappings based only on similar labels
* Removing fixtures because they expose a regression
* Updating expected snapshots merely to make tests pass
* Silently changing historical dictionary behavior
* Using non-deterministic fixtures
* Allowing unknown units to pass as known
* Converting missing values to zero
* Collapsing protected non-equivalences
* Letting Platform Dictionaries redefine Baseball Concepts
* Introducing persistence into Translation Engine tests
* Allowing unreviewed semantic changes
* Treating ordinary code review as sufficient for constitutional changes
* Deleting retired versions required for reproducibility
* Declaring Era 1 complete while certification failures remain

---

# Era 1 Completion Criteria

Era 1 — Translation may be declared complete only when:

* Chapters 1 through 8 are approved.
* The Translation Engine workflow is fully specified.
* The Baseball Dictionary is governed.
* Supported Platform Dictionaries are documented.
* Certification fixtures exist for supported platforms and generic layouts.
* Semantic equivalence is tested.
* Protected non-equivalence is tested.
* Translation Review is certified.
* Translation Snapshot output is deterministic.
* The regression suite passes.
* The Translation Engine is formally frozen.
* No Persistence implementation is mixed into the Translation Engine.

---

# Specification Completion Is Not Certification Execution

Approval of Chapters 1 through 8 completes the Era 1 Translation specification.

It does not independently prove that every certification fixture, semantic-equivalence test, protected non-equivalence test, deterministic Translation Snapshot comparison, frontend workflow test, backend certification test, and regression suite currently passes.

The formal Era 1 Certified and Complete declaration may be made only after the evidence required by this chapter has been executed, reviewed, and approved.

Until then:

```text
Era 1 Translation Specification: Complete
Translation Engine Architecture: Frozen
Certification Evidence: Pending Verification
Era 1 Certified and Complete Declaration: Pending
Era 2 Persistence: Not Authorized
```

---

# Era 1 Declaration

When all completion criteria are satisfied, the FMTRX Data Hub Backbone may declare:

```text
Era 1 — Translation

Status: Certified
Status: Frozen
Status: Complete
```

This declaration does not authorize Era 2 automatically.

Era 2 begins only after:

* The complete Era 1 Backbone is reviewed
* The repository change is approved
* The architecture milestone is committed
* Persistence work is explicitly authorized

---

# Chapter Certification

This chapter is complete when:

* Certification proves semantic correctness rather than label similarity.
* Real and deterministic fixtures cover supported platforms.
* Generic layouts and failure scenarios are covered.
* Semantic equivalence is explicitly tested.
* Protected non-equivalence is permanently protected.
* Translation Review and Snapshot behavior are deterministic.
* Dictionary and engine changes require versioning.
* Architecture Review governs semantic and constitutional changes.
* The Translation Engine freeze is enforceable.
* Era 1 completion criteria are explicit.
* Era 2 remains blocked until separately authorized.

With Certification and Governance established, Part I of the FMTRX Data Hub Backbone is complete.

The next architectural work belongs to Part II — Persistence, beginning with the full engineering specification for the Immutable Translation Snapshot.
