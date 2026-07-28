# Chapter 7 — The FMTRX Translation Engine

## One Controlled Translation Workflow

The FMTRX Translation Engine is the system that converts external baseball information into an approved canonical interpretation.

It is not a persistence engine.

It does not create permanent sessions, events, metrics, statistics, benchmarks, Player DNA, or AI insights.

Its responsibility ends when it produces an approved, immutable Translation Snapshot.

The Translation Engine exists to answer one question:

> **What does this source information mean in the FMTRX Baseball Language?**

Every supported source follows one shared workflow:

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
Immutable Translation Snapshot
````

The workflow may simplify itself when a step is already known or unnecessary, but no required meaning may be skipped.

---

## Constitutional Relationship

The Translation Engine implements:

* Data Integrity Chain
* Rule of One Meaning
* Source Preservation
* Translation Before Persistence
* Source Independence
* Reproducibility
* Platform Neutrality
* Context Matters
* Non-Destructive Evolution

The Translation Engine is the enforcement point for those laws before any imported information becomes durable.

---

## Purpose

The Translation Engine exists to:

* Inspect source files
* Recognize platforms
* Understand spreadsheet structure
* Identify source players
* Resolve athlete ownership
* Identify source fields
* Recommend Baseball Concepts
* Translate controlled values
* Surface ambiguity
* Preserve exclusions
* Produce normalized sample records
* Present the complete Translation Review
* Capture explicit approval
* Produce an immutable Translation Snapshot

The Translation Engine converts source-specific structure and language into a coach-approved canonical interpretation.

---

## Scope

The Translation Engine is responsible for:

* Platform selection
* Source file intake
* File inspection
* File type validation
* Destination selection
* File Structure detection
* Player discovery
* Player Mapping
* Column discovery
* Column Mapping
* Unit interpretation
* Controlled-Value Mapping
* Compatibility validation
* Warning generation
* Exclusion handling
* Translation Review
* Approval
* Translation Snapshot creation

The Translation Engine is not responsible for:

* Import Batch execution
* Import History
* External Session creation
* Canonical Event creation
* Canonical Metric creation
* Duplicate persistence resolution
* Statistics Projection
* Player profile changes
* Benchmark calculation
* Player DNA updates
* AI reasoning

Those responsibilities begin only after the Translation Snapshot handoff.

---

## The Translation Boundary

The Translation Engine begins with an untrusted external source.

It ends with an approved canonical interpretation.

```text
Untrusted External Source
        ↓
Temporary Inspection
        ↓
Proposed Translation
        ↓
Coach Review
        ↓
Explicit Approval
        ↓
Immutable Translation Snapshot
```

Before approval, all translation state is temporary.

After approval, the Snapshot becomes immutable.

No permanent athlete-development records are created by this engine.

---

# Workflow Stage 1 — Platform

## Purpose

The Platform stage identifies the source technology or source category.

Examples include:

* TrackMan
* HitTrax
* Rapsodo
* Blast Motion
* FMTRX Template
* Generic Spreadsheet

Platform answers:

> **Where did this information come from?**

Platform does not answer:

> **What FMTRX session should this become?**

That is the responsibility of Destination.

---

## Platform Selection

The engine may:

* Auto-detect a likely Platform
* Rank possible Platforms
* Show confidence
* Display recognition evidence
* Allow manual correction
* Fall back to Generic Spreadsheet

The coach retains final control before approval.

---

## Platform Invariants

* Platform recognition must be evidence-based.
* Low-confidence matches must remain reviewable.
* Platform identity must be preserved in provenance.
* Platform selection must not permanently restrict Destination.
* Platform identity must not define canonical meaning by itself.

---

## Platform Prohibitions

* Silently assigning a platform from one ambiguous header
* Treating a vendor name as proof of field meaning
* Locking the coach out of correction
* Giving one platform special canonical treatment
* Creating permanent records at platform selection

---

# Workflow Stage 2 — File

## Purpose

The File stage receives and inspects the source document.

Supported structured translation formats currently include:

* CSV
* XLSX
* TSV

PDF is not a supported structured translation format unless a separately certified extraction system is introduced.

---

## File Inspection

Inspection may identify:

* File type
* File checksum
* File size
* Worksheets
* Candidate header rows
* Candidate player fields
* Candidate player IDs
* Candidate event rows
* Source units
* Date fields
* Session fields
* Platform signatures
* Warnings
* Sample records

Inspection is temporary and non-persistent.

---

## File Invariants

* The original source file must be preserved.
* A deterministic checksum must identify the source bytes.
* Inspection must not alter the source.
* Unsupported file types must be rejected or routed outside the Translation Engine.
* The same file and inspection rules must produce the same inspection result.

---

## File Prohibitions

* Modifying the source file
* Persisting source rows as canonical records
* Treating malformed data as valid without warning
* Silently skipping unreadable worksheets
* Treating PDF text extraction as certified table structure without a separate specification

---

# Workflow Stage 3 — Destination

## Purpose

Destination identifies the FMTRX activity type that the approved data is intended to become during Persistence.

Platform answers:

> **Where did the data come from?**

Destination answers:

> **What FMTRX session type should it become?**

These are intentionally separate.

---

## Supported Destination Families

### Game

* Live AB

### Hitting

* Cage
* Batting Practice

### Pitching

* Bullpen
* Pitching Practice

### Throwing

* Long Toss
* Weighted Balls

### Performance

* Exit Velocity
* Assessment
* Strength
* Mobility
* Recovery
* Speed & Agility
* Body Composition

---

## Destination Behavior

Destination may affect:

* Concept recommendation ranking
* Compatibility validation
* Warning severity
* Session reconstruction rules
* Required contextual fields
* Sample normalization

Destination must not:

* Change the canonical meaning of a Baseball Concept
* Override verified source definitions
* Force incompatible fields into approximate concepts

---

## Destination Invariants

* The coach must be able to review Destination.
* Platform may recommend but not permanently dictate Destination.
* Destination context must be captured in the Snapshot.
* Incompatible concept mappings must generate warnings or blocks.
* Destination changes must deterministically update recommendations and validation.

---

## Destination Prohibitions

* Hard-coding one Destination per Platform
* Treating Destination as source provenance
* Using Destination to redefine source measurements
* Persisting a session before approval

---

# Workflow Stage 4 — File Structure

## Purpose

The File Structure stage explains how data is organized when the structure is not already certain.

The engine supports structures such as:

* Players in rows
* Players in columns
* Events in rows
* Worksheet per player
* Single-player session
* Metadata before headers
* Unknown structure requiring coach input

---

## File Structure Configuration

The coach may identify:

* Header row
* Player column
* Player ID column
* Worksheet
* Rows to ignore
* Columns to ignore
* Event start row
* Metadata rows
* Session context fields

This step should appear only when needed.

Known certified platform structures may be resolved automatically.

---

## File Structure Invariants

* Structure interpretation must be deterministic.
* The chosen structure must be captured in the Snapshot.
* Hidden rows and ignored columns must remain explainable.
* Ambiguous structure must route to review.
* File Structure determines inspection organization, not canonical meaning.

---

## File Structure Prohibitions

* Guessing through ambiguity without warning
* Permanently ignoring rows without recording the exclusion rule
* Treating worksheet layout as athlete identity
* Creating canonical records from unresolved structure

---

# Workflow Stage 5 — Player Mapping

## Purpose

Player Mapping establishes who owns the imported information.

Every unique source player must appear exactly once in the mapping interface.

Example:

```text
Tom Dimitroff
        ↓
Thomas Dimitroff
```

Once approved, every eligible source record belonging to Tom Dimitroff maps to the FMTRX Athlete Thomas Dimitroff.

---

## Player Mapping States

Each source player may be:

* Mapped to an existing FMTRX Athlete
* Marked Not Importing
* Left unresolved and blocked from approval
* Eligible for a separately governed Athlete creation workflow, if authorized in a future specification

The default import workflow must not automatically create Athlete records for every source player.

---

## Partial Player Import

A file may contain many players while only some are relevant to the coach.

Example:

* 20 source players detected
* 2 mapped to FMTRX Athletes
* 18 marked Not Importing

Only the two mapped players are eligible for Persistence.

For the other eighteen:

* No Athlete is created
* No External Session is created
* No Canonical Event is created
* No Canonical Metric is created

Their exclusion remains preserved in the Snapshot.

---

## Player Mapping Identity

Player Mapping may use:

* Source player name
* Source player ID
* Team
* Organization
* Date of birth, when appropriately available
* Existing FMTRX identity
* Prior approved source-player aliases

Name similarity alone must not silently establish identity.

---

## Player Mapping Invariants

* Every unique source player appears once.
* Every eligible source record resolves through one approved mapping.
* Not Importing players create no permanent athlete-development records.
* Source player identity remains preserved in provenance.
* Player Mapping must be included in the Snapshot.
* Duplicate source identities must generate review.

---

## Player Mapping Prohibitions

* Automatically importing every source player
* Creating hidden player records
* Mapping one source player to multiple Athletes within one Snapshot
* Assigning records based on row order alone
* Treating a platform ID as universal Athlete identity
* Persisting unresolved players

---

# Workflow Stage 6 — Column Mapping

## Purpose

Column Mapping associates source fields with Baseball Concepts.

The coach maps baseball meanings, not database columns.

Example:

```text
ExitSpeed
        ↓
Exit Velocity
```

Another example:

```text
Ball Speed
        ↓
Exit Velocity
```

Only when source context and certification support that meaning.

---

## Column Mapping Interface

Each source header should appear once with:

* Source header
* Sample values
* Source unit
* Platform definition, when available
* Recommended Baseball Concept
* Confidence
* Compatibility
* Warning
* Mapping status

Possible states include:

* Mapped
* Not Importing
* Manual review required
* Unsupported
* Blocked

---

## Baseball Concept Selector

The selector should organize concepts by Baseball Domain.

Recommended order:

* Recommended
* Session Context
* Hitting
* Pitching
* Throwing
* Strength
* Mobility
* Speed & Agility
* Body Composition
* Recovery
* Assessment
* Game Outcome
* Defense
* Vision
* Mental Performance

Search should support:

* Canonical names
* Canonical keys
* Definitions
* Aliases
* Units
* Related terms

The selector should remain coach-friendly and avoid exposing physical database structure.

---

## Column Mapping Invariants

* Every mapped source field references exactly one Baseball Concept.
* One source field may not map to multiple canonical meanings within one context.
* Similar labels do not establish equivalence.
* Units and context must be reviewed.
* Not Importing fields create no Canonical Metrics.
* All mappings must be captured in the Snapshot.
* Mapping recommendations must be deterministic for the same inputs and versions.

---

## Column Mapping Prohibitions

* Mapping based only on spelling similarity
* Allowing one canonical concept to represent multiple meanings
* Hiding source definitions
* Mapping unavailable values to zero
* Exposing physical database columns as coach-facing concepts
* Persisting unmapped fields
* Silently converting unsupported source-specific scores into broad canonical concepts

---

# Workflow Stage 7 — Controlled-Value Mapping

## Purpose

Controlled-Value Mapping translates finite source classifications into approved canonical values.

Examples include:

* Pitch type
* Handedness
* Contact type
* Play result
* Session classification
* Test side
* Movement direction
* Recovery response

---

## Example

```text
FF
FourSeam
4-Seam
        ↓
Four-Seam Fastball
```

Only when the source values are certified as equivalent.

---

## Unknown Controlled Values

Unknown values must be:

* Manually mapped
* Left unknown
* Marked Not Importing
* Blocked when required for meaning

They must not be silently forced into the closest available option.

---

## Controlled-Value Invariants

* Source value remains preserved.
* Canonical value must be versioned.
* Context must be considered.
* Unknown behavior must be explicit.
* Controlled mappings must be captured in the Snapshot.
* The same source value, context, and versions must produce the same recommendation.

---

## Controlled-Value Prohibitions

* Guessing an unknown pitch type
* Collapsing distinct classifications without certification
* Deleting source enumerations
* Treating blank as a valid canonical value
* Silently changing historical controlled-value rules

---

# Workflow Stage 8 — Translation Review

## Purpose

Translation Review presents the complete proposed interpretation before approval.

It is the final inspection boundary between temporary translation and durable history.

---

## Required Review Sections

### Source Summary

Includes:

* Platform
* File name
* File checksum
* File type
* Worksheets
* Destination
* File Structure
* Dictionary versions
* Translation Engine version

### Player Translation

Shows:

* Every unique source player
* Mapped FMTRX Athlete
* Source identifiers
* Team context
* Not Importing status
* Warnings

### Concept Translation

Shows:

* Source headers
* Source definitions
* Source units
* Sample values
* Canonical concepts
* Confidence
* Compatibility
* Exclusions
* Warnings

### Controlled-Value Translation

Shows:

* Source value
* Canonical value
* Context
* Unknown values
* Manual mappings
* Warnings

### Warnings

Shows:

* Blocking warnings
* High-severity warnings
* Informational warnings
* Unresolved ambiguity
* Missing required context
* Unsupported fields
* Unknown units

### Not Importing Summary

Shows:

* Excluded players
* Excluded fields
* Excluded worksheets
* Excluded rows
* Unsupported values
* Reasons for exclusion

### Normalized Sample Records

Shows representative proposed records in canonical language before persistence.

Examples should demonstrate:

* Athlete
* Destination
* Event type
* Canonical concept
* Canonical value
* Canonical unit
* Source provenance

These are previews only.

They are not permanent Canonical Events or Canonical Metrics.

---

## Translation Review Invariants

* Review must show enough information for informed approval.
* All exclusions must remain visible.
* Warnings must not be hidden.
* Normalized samples must be generated deterministically.
* Review must identify all dictionary and engine versions.
* Review remains temporary until approval.
* Review must not create permanent athlete-development records.

---

## Translation Review Prohibitions

* Hiding unmapped players
* Hiding excluded fields
* Omitting dictionary versions
* Showing normalized samples as if already imported
* Persisting partial results before approval
* Allowing approval with unresolved blocking errors
* Presenting confidence as certainty

---

# Workflow Stage 9 — Approval

## Purpose

Approval is the coach’s explicit confirmation that the proposed translation correctly represents:

* Source Platform
* Destination
* File Structure
* Player Mapping
* Column Mapping
* Controlled-Value Mapping
* Exclusions
* Warnings
* Normalized interpretation

Approval is the constitutional boundary between temporary translation and an immutable approved Snapshot.

---

## Approval Requirements

Approval must record:

* Approving user
* Approval timestamp
* Source checksum
* Platform
* Destination
* File Structure
* Player mappings
* Column mappings
* Controlled-value mappings
* Exclusions
* Warning acknowledgments
* Platform Dictionary version
* Baseball Dictionary version
* Translation Engine version

---

## Approval Invariants

* Approval must be explicit.
* Approval must be attributable.
* Approval must capture exact reviewed state.
* Any material change after approval requires a new review and new approval.
* Approval does not itself create Sessions, Events, Metrics, or Statistics.
* Approval authorizes creation of the immutable Translation Snapshot only.

---

## Approval Prohibitions

* Implied approval
* Automatic approval
* Approval without identity
* Approval without version capture
* Editing mappings after approval without a new Snapshot
* Beginning Persistence from unapproved transient state

---

# Workflow Stage 10 — Immutable Translation Snapshot

## Purpose

The Translation Snapshot is the final product of the Translation Engine.

It is the immutable handoff contract to the future Persistence Engine.

It captures exactly what the coach approved.

---

## Snapshot Contents

The Snapshot must include:

* Source identity
* Source checksum
* Platform
* Platform recognition evidence
* File Structure
* Destination
* Source players
* Player mappings
* Not Importing players
* Source fields
* Column mappings
* Not Importing fields
* Controlled-value mappings
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

---

## Snapshot Invariants

* The Snapshot is immutable.
* The Snapshot is reproducible.
* The Snapshot is attributable.
* The Snapshot is explainable.
* The Snapshot contains no unapproved material changes.
* The Snapshot may exist before any Import Batch is created.
* Persistence may consume only an approved Snapshot.
* Changes require a new Snapshot.

---

## Snapshot Prohibitions

* Editing an approved Snapshot
* Replacing historical Snapshots
* Referencing only mutable current rules
* Omitting exclusions
* Omitting warnings
* Omitting source identity
* Creating an Import Batch from temporary review state
* Creating permanent baseball records inside the Translation Engine

---

# Translation State Model

The high-level state model is:

```text
File Selected
    ↓
Inspecting
    ↓
Inspection Ready
    ↓
Configuration Required
    ↓
Mapping In Progress
    ↓
Review Ready
    ↓
Approval Blocked
    or
Approved
    ↓
Snapshot Created
```

Additional terminal or recoverable states may include:

* Unsupported File
* Inspection Failed
* Ambiguous Platform
* Invalid Structure
* Unresolved Players
* Unresolved Required Concepts
* Unknown Required Units
* Cancelled
* Superseded

Exact implementation states may vary, but they must preserve the constitutional lifecycle.

---

# Determinism

The Translation Engine must be deterministic.

Given identical:

* Source bytes
* Platform selection
* Destination
* File Structure
* Player Mapping
* Column Mapping
* Controlled-Value Mapping
* Platform Dictionary version
* Baseball Dictionary version
* Translation Engine version

The engine must produce identical:

* Inspection result
* Player list
* Source field list
* Mapping recommendations
* Warnings
* Exclusions
* Normalized sample records
* Translation Review
* Translation Snapshot

Time-dependent, random, or uncontrolled model behavior may not determine permanent translation.

AI may assist with non-binding suggestions in the future only if:

* Suggestions are clearly identified
* Deterministic rules remain authoritative
* Approval remains explicit
* The exact accepted mapping is captured in the Snapshot

---

# Warnings and Blocking Errors

The Translation Engine must distinguish severity.

Suggested levels:

## Informational

The translation is valid, but the coach should understand context.

## Warning

The translation may be valid, but uncertainty or quality concerns exist.

## High Severity

Approval is permitted only with explicit acknowledgment, if policy allows.

## Blocking

Approval is prohibited until resolved.

Possible blocking conditions include:

* Unsupported file
* Unresolved required player identity
* Unknown required unit
* Incompatible required concept
* Invalid File Structure
* Missing required Destination
* Corrupted source
* Conflicting mappings
* Non-deterministic interpretation

Severity rules must be versioned and testable.

---

# Exclusions

The Translation Engine must preserve every intentional exclusion.

Exclusions may include:

* Not Importing players
* Not Importing fields
* Ignored worksheets
* Ignored rows
* Ignored columns
* Unsupported fields
* Invalid values
* Unknown controlled values
* Records outside selected scope

An exclusion is not deletion.

It is an explicit decision recorded in the Translation Snapshot.

---

# Templates and Known Platforms

## FMTRX Templates

FMTRX Templates may auto-resolve:

* Platform
* File Structure
* Player IDs
* Canonical concept keys
* Units
* Destination recommendations

They still require Translation Review and approval.

---

## Known Platform Exports

Certified Platform Dictionaries may auto-resolve:

* Platform identity
* Known headers
* Known units
* Controlled values
* Recommended concepts
* Known structures

They still require coach review.

---

## Generic Spreadsheets

Generic spreadsheets require more explicit inspection.

The engine may assist through:

* Structure detection
* Header recommendations
* Unit detection
* Player discovery
* Alias search
* Sample normalization

Generic spreadsheets must not receive false confidence.

---

# Responsibilities

The Translation Engine is responsible for:

* Temporary source interpretation
* Workflow orchestration
* Platform recognition
* Structure inspection
* Player ownership mapping
* Concept mapping
* Controlled-value mapping
* Validation
* Warnings
* Exclusions
* Review
* Approval capture
* Snapshot production

It is not responsible for:

* Durable imported sessions
* Durable canonical events
* Durable canonical metrics
* Import execution
* Import rollback
* Statistics
* Benchmarks
* Player DNA
* AI recommendations

---

# Explicit Prohibitions

The following are prohibited:

* Direct file-to-database import
* Persistence before approval
* Hidden partial imports
* Automatic creation of all source players
* Creating records for Not Importing players
* Mapping by label similarity alone
* Converting missing values to zero
* Hiding warnings
* Hiding exclusions
* Allowing unresolved blocking errors
* Editing approved Snapshots
* Calling preview records Canonical Events
* Embedding persistence logic into the Translation Engine
* Allowing downstream systems to read raw source files instead of canonical records
* Modifying the frozen engine architecture incidentally during Era 2

---

# Translation Engine Freeze

The FMTRX Translation Engine v1 is considered certified and frozen once Chapter 8 confirms its certification framework.

After freeze, changes are limited to:

* Verified bug fixes
* New Platform Dictionaries
* Missing Baseball Concepts
* Certification improvements
* Performance improvements that do not alter meaning
* Explicitly reviewed constitutional changes

The following are not permitted under routine maintenance:

* Reordering the constitutional workflow
* Bypassing Translation Review
* Combining Translation and Persistence
* Removing provenance
* Weakening approval
* Introducing platform favoritism
* Changing historical translation behavior without versioning

---

# Chapter Certification

This chapter is complete when:

* Every source follows one shared translation workflow.
* Platform and Destination remain separate.
* File Structure is reviewed only when needed.
* Every unique source player appears once in Player Mapping.
* Not Importing players are fully excluded from Persistence.
* Coaches map Baseball Concepts rather than database fields.
* Controlled values are explicit and versioned.
* Translation Review shows source, mappings, warnings, exclusions, and normalized samples.
* Approval is explicit and attributable.
* The Translation Snapshot captures the exact approved state.
* No permanent Sessions, Events, Metrics, or Statistics are created by the Translation Engine.
* Identical inputs and versions produce identical results.
* The engine can support new platforms through dictionaries rather than architectural branches.
* The Translation-to-Persistence boundary is unambiguous.

With the Translation Engine now fully specified, the final chapter of Era 1 must prove that the engine behaves correctly and continues to do so over time: Certification.
