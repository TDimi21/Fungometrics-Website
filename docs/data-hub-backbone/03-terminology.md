# Chapter 3 — Terminology

## One Language

A constitutional architecture cannot function without a common language.

The Data Hub cannot remain trustworthy if developers, coaches, services, or AI systems use the same word to represent different ideas.

Ambiguity creates inconsistency.

Inconsistency creates incompatible records.

Incompatible records eventually create corrupted history.

For that reason, the Rule of One Meaning applies to the architecture itself.

Every term in this chapter has one official definition within the FMTRX Data Hub Backbone.

Later chapters may expand a term’s implementation, but they may not redefine its meaning.

---

## Athlete

A person whose baseball development history is tracked within FMTRX.

The Athlete is the permanent center of the system.

An Athlete may change teams, organizations, coaches, facilities, and technology platforms while retaining one continuous FMTRX development history.

---

## Organization

A baseball program, school, team operator, training business, facility, instructor group, or other entity using FMTRX.

Organizations provide access, governance, and context.

An Organization does not replace the Athlete as the permanent owner of developmental history.

---

## Team

A collection of Athletes associated with an Organization for a defined context or period.

A Team is an organizational relationship.

It is not the permanent owner of an Athlete’s history.

---

## Platform

An external technology, software product, device ecosystem, or defined spreadsheet source that produces baseball or athlete-development information.

Examples include:

* TrackMan
* HitTrax
* Rapsodo
* Blast Motion
* FMTRX Templates
* Generic Spreadsheet

Platforms describe baseball using their own language.

Platforms do not define canonical FMTRX meaning.

---

## Source File

The original file supplied for translation.

Supported Translation Engine formats currently include:

* CSV
* XLSX
* TSV

The Source File is preserved immutably.

A PDF may be stored as an attachment in other FMTRX contexts, but it is not considered a supported structured translation source unless a separately certified extraction system exists.

---

## Source Record

A distinguishable source row, event, observation, cell group, worksheet record, or other normalized unit identified during inspection.

A Source Record has not yet become a Canonical Event.

---

## File Inspection

The temporary, non-persistent process of reading a Source File to identify:

* File type
* Worksheets
* Headers
* Candidate player fields
* Candidate event rows
* Candidate session context
* Platform
* Structure
* Units
* Sample values
* Warnings

File Inspection does not create permanent athlete records.

---

## File Structure

The configuration explaining how a spreadsheet is organized.

Examples include:

* Players in rows
* Players in columns
* Events in rows
* Worksheet per player
* Single-player session
* Metadata before headers
* Unknown structure requiring coach input

File Structure may include:

* Header row
* Player column
* Player ID column
* Worksheet selection
* Ignored rows
* Ignored columns

---

## Destination

The FMTRX session or activity type that approved translated data is intended to become during persistence.

Examples include:

* Live AB
* Cage
* Batting Practice
* Bullpen
* Pitching Practice
* Long Toss
* Weighted Balls
* Exit Velocity
* Assessment
* Strength
* Mobility
* Recovery
* Speed & Agility
* Body Composition

Destination supplies activity context.

It does not independently determine semantic meaning.

---

## Platform Dictionary

The versioned translation specification for one Platform.

A Platform Dictionary defines:

* Recognized source fields
* Source aliases
* Source units
* Controlled values
* Field definitions
* Context rules
* Candidate Baseball Concepts
* Confidence
* Compatibility
* Known ambiguities
* Protected non-equivalences
* Validation rules

A Platform Dictionary explains how a platform speaks.

It does not redefine the canonical FMTRX Baseball Dictionary.

---

## FMTRX Baseball Dictionary

The versioned canonical language of baseball and athlete development inside FMTRX.

It contains the Baseball Concepts used by all supported platforms and all downstream systems.

The Baseball Dictionary is platform-independent.

---

## Baseball Concept

One canonical, versioned meaning recognized by FMTRX.

A Baseball Concept includes, at minimum:

* Stable identifier
* Canonical key
* Canonical name
* Definition
* Domain
* Data type
* Unit family
* Canonical unit
* Compatibility
* Validation constraints
* Search aliases
* Examples
* Lifecycle status
* Version history

One Baseball Concept may not represent multiple meanings.

---

## Semantic Equivalence

A certified determination that two or more source fields represent the same Baseball Concept.

Semantic Equivalence requires evidence beyond similar labels.

Evidence may include:

* Official platform definitions
* Units
* Measurement method
* Event context
* Source examples
* Controlled values
* Certification fixtures
* Human review

---

## Translation

The deterministic process of converting source-specific structure, labels, values, and units into proposed canonical FMTRX meanings.

Translation changes representation.

It must not change the underlying baseball meaning.

---

## Player Mapping

The process of associating each unique imported source player with an existing FMTRX Athlete or marking that source player as Not Importing.

Every unique source player appears once in Player Mapping.

Once approved, that mapping applies consistently to all eligible Source Records for that source player.

Players left as Not Importing are excluded from persistence.

---

## Column Mapping

The process of associating a source field with a Baseball Concept or explicitly marking it as Not Importing.

Column Mapping establishes the proposed semantic meaning of source values.

Coaches map baseball meaning, not physical database columns.

---

## Controlled-Value Mapping

The process of translating a finite source enumeration into an approved canonical value.

Examples include:

* Pitch type
* Handedness
* Contact type
* Play result
* Session classification
* Test side
* Movement direction

Controlled-value translation must be versioned and reproducible.

---

## Translation Review

The complete coach-facing inspection of the proposed translation before persistence.

Translation Review includes:

* Source Summary
* Destination
* Player Translation
* Concept Translation
* Controlled-Value Translation
* Warnings
* Not Importing Summary
* Normalized Sample Records
* Version information

Translation Review is temporary until approved.

---

## Translation Approval

The explicit user action confirming that the Translation Review correctly represents the intended players, concepts, controlled values, destination, and exclusions.

Approval is required before durable persistence.

---

## Translation Snapshot

The immutable record of an approved Translation Review.

A Translation Snapshot captures exactly what was approved, including:

* Source identity
* Source checksum
* Platform
* File Structure
* Destination
* Player Mapping
* Column Mapping
* Controlled-value mappings
* Exclusions
* Warnings
* Dictionary versions
* Translation Engine version
* Approval identity
* Approval timestamp

The Snapshot is the handoff contract between Translation and Persistence.

---

## Import Batch

The permanent record of one persistence operation executed from one approved Translation Snapshot.

An Import Batch records the transaction.

It does not replace the Snapshot and does not redefine the translation.

---

## Import History

The auditable history of Import Batches, statuses, exclusions, failures, reversals, replays, and related persistence actions.

Import History explains what happened after approval.

---

## External Session

A permanent FMTRX session reconstructed from approved external information.

An External Session preserves activity context such as:

* Athlete
* Date and time
* Destination
* Source platform
* Venue
* Team
* Session labels
* Import Batch

It is called external because its originating activity was recorded outside FMTRX, even though its permanent representation is canonical.

---

## Canonical Event

One permanent, indivisible athlete-development occurrence inside FMTRX.

Examples include:

* One pitch
* One batted-ball event
* One swing sensor event
* One throw
* One sprint
* One jump
* One lift set or test event
* One mobility measurement
* One recovery observation
* One assessment event

A Canonical Event provides the occurrence to which Canonical Metrics are attached.

---

## Canonical Metric

One measured or observed canonical value attached to a Canonical Event and referencing exactly one Baseball Concept.

Examples include:

* Exit Velocity = 98.4 mph
* Launch Angle = 24 degrees
* Pitch Release Velocity = 91.2 mph
* Spin Rate = 2,380 rpm
* Bat Speed = 72.5 mph

A Canonical Metric preserves provenance to its original source value.

---

## Provenance

The complete chain explaining the origin, interpretation, approval, persistence, and later use of a baseball fact.

Provenance answers:

* Where did the value come from?
* What did the source call it?
* What was the original value and unit?
* Which Platform Dictionary interpreted it?
* Which Baseball Concept received it?
* Which versions were used?
* Who approved the translation?
* Which Snapshot captured it?
* Which Import Batch persisted it?
* Which Session, Event, and Metric contain it?

---

## Duplicate

A candidate record that may represent the same real-world source, session, event, or measurement as another record.

Duplicate status must be determined through explicit, versioned rules.

Similarity alone does not permit silent deletion.

---

## Rollback

An auditable action that removes or excludes the active effect of an Import Batch without destroying its historical record.

Rollback does not erase provenance.

---

## Replay

A controlled re-execution of persistence or projection from an approved and versioned source state.

Replay must identify:

* Source Snapshot
* Rule versions
* Reason
* Initiator
* Prior result
* New result

---

## Statistics Projection

The deterministic process of deriving aggregate statistics from Canonical Events and Canonical Metrics.

Statistics Projection does not modify canonical history.

Projected statistics may be recalculated from immutable facts.

---

## Benchmark

A comparative standard derived from a validated athlete population, research source, team population, or other approved benchmark source.

Benchmarks evaluate canonical metrics and projected statistics.

They do not alter historical facts.

---

## Player Record

The athlete-facing presentation of canonical history and approved derived information.

The Player Record is a view of the Athlete’s history.

It is not an alternative source of truth separate from canonical records.

---

## Player DNA

FMTRX’s long-term developmental representation of an Athlete.

Player DNA may consume:

* Canonical Events
* Canonical Metrics
* Statistics Projections
* Benchmarks
* Trends
* Assessments
* Training responses
* Development history

Player DNA is derived intelligence.

It may not consume raw platform fields directly.

---

## AI

The FMTRX intelligence layer that uses canonical and approved derived information to produce insights, recommendations, summaries, projections, or coaching support.

AI is a consumer.

It is not a source of canonical historical facts unless a separate, explicitly governed observation system is created.

AI may not read raw platform files as a substitute for the Translation Engine.

---

## Naming Standards

The following naming standards apply:

* Every architectural term has one official definition.
* Every Baseball Concept has one canonical name.
* Synonyms may support search and discovery but not canonical storage.
* Database names, API contracts, service names, documentation, and user interfaces should align with official terminology.
* Platform-specific terms must remain clearly identified as source terminology.
* Later chapters may expand responsibilities but may not redefine these terms.

---

## Explicit Prohibitions

The following are prohibited:

* Using `import` and `translation` as interchangeable architectural terms
* Calling a Source Record a Canonical Event before persistence
* Calling a source field a Baseball Concept
* Treating Translation Review as permanent storage
* Treating an Import Batch as a Source File
* Treating statistics as canonical historical events
* Treating Player DNA as raw data
* Allowing platform vocabulary to replace canonical FMTRX terminology
* Creating undocumented competing definitions in code
* Redefining these terms in later chapters

---

## Chapter Certification

This chapter is complete when:

* Every core architectural term has one official definition.
* Later chapters use these terms consistently.
* Database schemas and APIs can be reviewed against this vocabulary.
* Translation and Persistence terminology remain clearly separated.
* Source terminology and canonical terminology cannot be confused.
* Future developers and AI systems can understand the architecture without relying on tribal knowledge.

With the official language established, the Backbone must now define the permanent structure that receives translated baseball information.
