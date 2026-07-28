# FMTRX Data Hub Import Certification Results

This suite is inspection-only. It creates no FMTRX practices, sessions, canonical events, assessments, statistics, profiles, mappings, or import batches.

| Fixture | Format | Layout | Destination | Players | Metrics | Expected Outcome |
|---|---|---|---|---:|---:|---|
| `generic-assessment-rows` | CSV | players_in_rows | assessment | 4 | 11 | Expected review / manual confirmation |
| `generic-strength-xlsx` | XLSX | players_in_rows | strength | 4 | 13 | Expected review / manual confirmation |
| `generic-mobility-warning` | CSV | players_in_rows | mobility | 3 | 7 | Expected review / manual confirmation |
| `generic-recovery-tsv` | TSV | players_in_rows | recovery | 3 | 7 | Expected review / manual confirmation |
| `generic-exit-velocity-events` | CSV | events_in_rows | exit_velocity | 3 | 8 | Expected review / manual confirmation |
| `generic-long-toss-events` | CSV | events_in_rows | long_toss | 3 | 6 | Expected review / manual confirmation |
| `generic-weighted-ball-events` | XLSX | events_in_rows | weighted_balls | 3 | 6 | Expected review / manual confirmation |
| `layout-players-columns` | XLSX | players_in_columns | assessment | 3 | 4 | Expected review / manual confirmation |
| `layout-worksheet-player` | XLSX | worksheet_per_player | assessment | 3 | 5 | Expected review / manual confirmation |
| `layout-single-player` | CSV | single_player_session | bullpen | 1 | 6 | Expected review / manual confirmation |
| `layout-multiple-player-events` | CSV | events_in_rows | weighted_balls | 3 | 4 | Expected review / manual confirmation |
| `layout-metadata-header` | CSV | players_in_rows | strength | 2 | 3 | Expected review / manual confirmation |
| `layout-blank-rows` | CSV | players_in_rows | strength | 2 | 3 | Expected review / manual confirmation |
| `column-unknowns` | CSV | players_in_rows | assessment | 2 | 4 | Expected blocked/rejected: At least one valid performance concept must be mapped. |
| `player-duplicate-names` | CSV | players_in_rows | assessment | 3 | 2 | Expected review / manual confirmation |
| `player-no-roster` | CSV | players_in_rows | assessment | 3 | 2 | Expected blocked/rejected: At least one source player must be explicitly connected. |
| `player-mixed-roster` | CSV | players_in_rows | assessment | 4 | 2 | Expected review / manual confirmation |
| `column-manual-baseball` | CSV | players_in_rows | cage | 2 | 5 | Expected review / manual confirmation |
| `column-duplicate-concepts` | CSV | players_in_rows | cage | 2 | 3 | Expected blocked/rejected: Duplicate concept mappings require explicit confirmation or Not Importing decisions. |
| `player-twenty-events` | CSV | events_in_rows | exit_velocity | 1 | 3 | Expected review / manual confirmation |
| `fmtrx-generated-template` | CSV | players_in_rows | strength | 4 | 17 | Expected success |
| `fmtrx-altered-template` | CSV | players_in_rows | strength | 4 | 17 | Expected blocked/rejected: FMTRX canonical-key row was altered. |
| `invalid-bad-xlsx` | XLSX | worksheet_per_player | assessment | 2 | 3 | Expected blocked/rejected: Ambiguous structure and columns must be corrected or ignored. |
| `invalid-extension` | TXT | unknown | assessment | 0 | 0 | Expected blocked/rejected: Unsupported extension. |
| `invalid-malformed-csv` | CSV | unknown | assessment | 0 | 0 | Expected blocked/rejected: Malformed CSV must not crash inspection. |
| `invalid-malformed-xlsx` | XLSX | unknown | assessment | 0 | 0 | Expected blocked/rejected: The Excel workbook could not be opened. |
| `platform-trackman` | CSV | events_in_rows | live_ab | 3 | 7 | Expected success |
| `platform-hittrax` | CSV | events_in_rows | batting_practice | 2 | 9 | Expected success |
| `platform-rapsodo` | XLSX | single_player_session | bullpen | 1 | 16 | Expected success |
| `platform-blast` | CSV | single_player_session | batting_practice | 1 | 20 | Expected success |

## Generic layouts

Players-in-rows, players-in-columns, event rows, worksheet-per-player, single-player sessions, metadata-before-header, blank-row handling, and logical transposition are covered. Source workbooks are never modified.

## Player Mapping

The suite covers repeated events, duplicate source names, no roster matches, mixed rosters, session-level assignment, Connected versus Not Importing counts, and duplicate-target confirmation.

## Column Mapping

Known concepts, unknown columns, context-sensitive baseball headers, duplicate concepts, controlled-value review, Store as Unknown, Submit New Concept, and approval blocking are represented in the manifest.

## FMTRX templates

The clean strength template is derived from the live `FmtrxTemplateCatalog` and its schema is compared to output from `FmtrxCsvTemplateService` in the certification test. An altered canonical-key row is expected to be rejected.

## Known platforms

Sanitized TrackMan, HitTrax, Rapsodo, and Blast Motion fixtures use the same headers and shapes exercised by their production inspection services. No private customer exports are copied.

## Invalid and unsupported files

A safe problematic workbook contains merged cells, duplicate/blank headers, mixed values, a non-executed formula, and a hidden worksheet. Harmless unsupported, malformed CSV, and malformed XLSX placeholders verify clear rejection without executable or malicious content.

## Certification boundary

Passing this suite certifies inspection and mapping workflow inputs. It does not certify Import Batch persistence or writes to canonical/legacy performance records.
