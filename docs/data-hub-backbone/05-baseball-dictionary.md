# Chapter 5 — The FMTRX Baseball Dictionary

## One Baseball Language

Every baseball technology describes the game differently.

TrackMan uses one vocabulary.

HitTrax uses another.

Rapsodo measures similar ideas using different names.

Blast Motion focuses on different observations entirely.

Coach spreadsheets often use abbreviations that exist nowhere else.

Without a shared language, two systems may describe the same baseball event differently, while two identical labels may represent entirely different measurements.

The purpose of the FMTRX Baseball Dictionary is to eliminate that ambiguity.

The Baseball Dictionary defines the official language of baseball inside FMTRX.

Every canonical measurement, observation, classification, and controlled value ultimately references a Baseball Concept defined here.

The Baseball Dictionary is the single source of truth for baseball meaning.

It is independent of every platform.

---

# Constitutional Relationship

The Baseball Dictionary implements:

* Rule of One Meaning
* Platform Neutrality
* Context Matters
* Source Independence
* Reproducibility

No Platform Dictionary may redefine a Baseball Concept.

Platforms translate into the Baseball Dictionary.

The Baseball Dictionary never translates toward a platform.

---

# Purpose

The Baseball Dictionary exists to define one canonical meaning for every measurable baseball concept recognized by FMTRX.

It provides:

* Stable identity
* Canonical naming
* Definitions
* Units
* Data types
* Validation
* Compatibility
* Relationships
* Search aliases
* Lifecycle
* Version history

Every Canonical Metric references exactly one Baseball Concept.

---

# Scope

The Baseball Dictionary defines canonical concepts for every athlete-development domain supported by FMTRX.

Examples include:

### Hitting

* Exit Velocity
* Launch Angle
* Spray Angle
* Bat Speed
* Attack Angle
* Time to Contact

### Pitching

* Release Velocity
* Spin Rate
* Spin Axis
* Vertical Break
* Horizontal Break
* Release Height
* Release Side
* Extension

### Throwing

* Throw Velocity
* Long Toss Distance
* Weighted Ball Velocity

### Strength

* Back Squat
* Trap Bar Deadlift
* Bench Press
* Chin Ups
* Grip Strength

### Mobility

* Hip Internal Rotation
* Shoulder External Rotation
* Thoracic Rotation

### Speed

* Sprint Time
* Sprint Velocity
* 10-Yard Split
* 30-Yard Split

### Assessment

* Height
* Weight
* Wingspan
* Vertical Jump

### Recovery

* Sleep Hours
* Fatigue Score
* Readiness Score

The Baseball Dictionary defines meaning.

It does not define how a platform measures that meaning.

---

# Baseball Concept

A Baseball Concept represents one canonical baseball meaning.

Each Baseball Concept contains:

## Identity

* Stable UUID
* Canonical Key
* Canonical Name

Example

```text
Key

hitting.exit_velocity
```

Name

```text
Exit Velocity
```

The key never changes.

The display name may evolve through versioning if necessary.

---

## Definition

Every concept contains one official definition.

Example

Exit Velocity

"The speed of the baseball immediately after contact with the bat."

Definitions describe baseball meaning.

They never reference platform-specific terminology.

---

## Domain

Every concept belongs to one logical domain.

Examples:

* Hitting
* Pitching
* Throwing
* Strength
* Mobility
* Speed
* Recovery
* Assessment
* Defense
* Vision
* Mental Performance

Domains improve organization.

They do not affect meaning.

---

## Data Type

Every concept has one approved data type.

Examples:

* Decimal
* Integer
* Boolean
* Text
* Enumeration
* Date
* Time
* Duration

Data type validation is part of the concept.

---

## Units

Each concept defines:

Canonical Unit

Example

```text
mph
```

Supported Units

Example

```text
mph

km/h
```

Conversion Rules

Every supported conversion must be deterministic.

---

## Validation

Each concept defines acceptable values.

Examples:

Exit Velocity

```text
0–130 mph
```

Launch Angle

```text
-90° to +90°
```

Spin Rate

```text
0–6000 rpm
```

Validation protects canonical history.

It does not modify source information.

---

## Compatibility

Every concept explicitly identifies which destinations support it.

Example

Exit Velocity

Compatible:

* Cage
* Live AB
* Batting Practice
* Game

Not Compatible:

* Bullpen
* Long Toss

Compatibility prevents invalid mappings during Translation Review.

---

## Aliases

Platforms rarely use canonical names.

Each concept therefore defines searchable aliases.

Example

Exit Velocity

Aliases

* ExitSpeed
* EV
* Velo
* Exit Velocity
* Ball Speed

Aliases support discovery.

They do not replace the canonical name.

---

## Semantic Relationships

Concepts may define relationships.

Example

```text
Release Velocity

Parent Domain

Pitching
```

Related Concepts

* Spin Rate
* Extension
* Release Height

Relationships improve navigation and AI understanding.

They never replace canonical identity.

---

# Controlled Concepts

Some concepts represent finite approved values.

Examples

Pitch Type

Values

* Four-Seam Fastball
* Two-Seam Fastball
* Cutter
* Slider
* Curveball
* Splitter
* Changeup

Each controlled value is versioned.

Platforms translate into these values.

---

# Versioning

Concepts evolve through versioning.

Versioning may add:

* Better definitions
* New aliases
* New compatible platforms
* Additional units
* Improved validation

Versioning may not silently change meaning.

If meaning changes,

a new Baseball Concept must be created.

---

# Semantic Equivalence

Multiple source fields may map to one Baseball Concept only after certification.

Example

Certified

TrackMan

```text
ExitSpeed
```

HitTrax

```text
Velo
```

↓

Baseball Concept

```text
Exit Velocity
```

---

Protected

TrackMan

```text
RelSpeed
```

↓

Pitch Release Velocity

≠

HitTrax

```text
Pitch
```

↓

Inbound Pitch Velocity

These remain separate Baseball Concepts.

---

# Lifecycle

Every Baseball Concept follows a governed lifecycle.

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

Certified concepts may be used by Platform Dictionaries.

Deprecated concepts remain supported for historical records.

Retired concepts remain explainable forever.

---

# Responsibilities

The Baseball Dictionary is responsible for:

* Canonical meaning
* Naming
* Units
* Validation
* Compatibility
* Versioning
* Aliases
* Relationships
* Controlled values
* Semantic identity

It is not responsible for:

* Platform translation
* File inspection
* Player Mapping
* Persistence
* Statistics
* AI reasoning

---

# Explicit Prohibitions

The following are prohibited:

* Two Baseball Concepts sharing the same meaning
* One Baseball Concept representing multiple meanings
* Platform terminology replacing canonical terminology
* Silent changes to canonical meaning
* Removing concepts that exist in historical records
* Platform-specific validation inside Baseball Concepts
* Treating aliases as canonical names
* Creating new concepts without documentation and certification

---

# Chapter Certification

This chapter is complete when:

* Every canonical baseball meaning has one Baseball Concept.
* Every Baseball Concept has a stable identifier and canonical key.
* Every concept defines its domain, units, validation, compatibility, and lifecycle.
* Aliases improve discovery without changing canonical identity.
* Platform Dictionaries reference Baseball Concepts rather than redefining them.
* Semantic equivalence and protected non-equivalence are documented and testable.
* New platforms can be integrated by extending Platform Dictionaries instead of modifying the Baseball Dictionary.
* Historical concepts remain explainable even after deprecation.

---

## Why this chapter matters

This is the chapter that transforms FMTRX from an application into a **baseball language**.

The first four chapters established the philosophy, laws, vocabulary, and structure. Chapter 5 establishes the canonical concepts that populate that structure. Once this chapter is in place, every current and future platform—TrackMan, HitTrax, Rapsodo, Blast Motion, FMTRX Templates, or technologies that don't yet exist—can speak through the same dictionary.

The natural next step is **Chapter 6: Platform Dictionaries**, where we'll define how each supported platform translates into these canonical Baseball Concepts without ever changing their meaning. I believe Chapters 5 and 6 together will complete the conceptual foundation of the Translation Engine, leaving only the engine workflow and certification to document before Era 1 is fully specified.
