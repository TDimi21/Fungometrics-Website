# FMTRX Data Hub Import Certification Manifest

Generated deterministically. All names and values are fictional test data.

| ID | File | Format | Platform | Destination | Layout | Records | Players | Outcome |
|---|---|---|---|---|---|---:|---:|---|
| `generic-assessment-rows` | `generic/assessment/assessment_players_in_rows.csv` | CSV | generic-csv | assessment | players_in_rows | 4 | 4 | Review / confirmation |
| `generic-strength-xlsx` | `generic/strength/strength_multi_player.xlsx` | XLSX | generic-csv | strength | players_in_rows | 4 | 4 | Review / confirmation |
| `generic-mobility-warning` | `generic/mobility/mobility_multi_player.csv` | CSV | generic-csv | mobility | players_in_rows | 3 | 3 | Review / confirmation |
| `generic-recovery-tsv` | `generic/recovery/recovery_multi_player.tsv` | TSV | generic-csv | recovery | players_in_rows | 3 | 3 | Review / confirmation |
| `generic-exit-velocity-events` | `generic/exit_velocity/exit_velocity_events.csv` | CSV | generic-csv | exit_velocity | events_in_rows | 12 | 3 | Review / confirmation |
| `generic-long-toss-events` | `generic/long_toss/long_toss_events.csv` | CSV | generic-csv | long_toss | events_in_rows | 12 | 3 | Review / confirmation |
| `generic-weighted-ball-events` | `generic/weighted_ball/weighted_ball_events.xlsx` | XLSX | generic-csv | weighted_balls | events_in_rows | 12 | 3 | Review / confirmation |
| `layout-players-columns` | `generic/layouts/players_across_columns.xlsx` | XLSX | generic-csv | assessment | players_in_columns | 4 | 3 | Review / confirmation |
| `layout-worksheet-player` | `generic/layouts/worksheet_per_player.xlsx` | XLSX | generic-csv | assessment | worksheet_per_player | 3 | 3 | Review / confirmation |
| `layout-single-player` | `generic/layouts/single_player_bullpen.csv` | CSV | generic-csv | bullpen | single_player_session | 10 | 1 | Review / confirmation |
| `layout-multiple-player-events` | `generic/layouts/multiple_player_events.csv` | CSV | generic-csv | weighted_balls | events_in_rows | 24 | 3 | Review / confirmation |
| `layout-metadata-header` | `generic/layouts/metadata_before_header.csv` | CSV | generic-csv | strength | players_in_rows | 2 | 2 | Review / confirmation |
| `layout-blank-rows` | `generic/layouts/blank_rows.csv` | CSV | generic-csv | strength | players_in_rows | 2 | 2 | Review / confirmation |
| `column-unknowns` | `generic/column_mapping/unknown_columns.csv` | CSV | generic-csv | assessment | players_in_rows | 2 | 2 | Blocked or rejected |
| `player-duplicate-names` | `generic/player_mapping/duplicate_source_names.csv` | CSV | generic-csv | assessment | players_in_rows | 3 | 3 | Review / confirmation |
| `player-no-roster` | `generic/player_mapping/no_roster_matches.csv` | CSV | generic-csv | assessment | players_in_rows | 3 | 3 | Blocked or rejected |
| `player-mixed-roster` | `generic/player_mapping/mixed_roster.csv` | CSV | generic-csv | assessment | players_in_rows | 4 | 4 | Review / confirmation |
| `column-manual-baseball` | `generic/column_mapping/manual_baseball_headers.csv` | CSV | generic-csv | cage | players_in_rows | 2 | 2 | Review / confirmation |
| `column-duplicate-concepts` | `generic/column_mapping/duplicate_concepts.csv` | CSV | generic-csv | cage | players_in_rows | 2 | 2 | Blocked or rejected |
| `player-twenty-events` | `generic/player_mapping/same_player_twenty_events.csv` | CSV | generic-csv | exit_velocity | events_in_rows | 20 | 1 | Review / confirmation |
| `fmtrx-generated-template` | `generic/assessment/fmtrx_assessment_template.csv` | CSV | fmtrx-template | strength | players_in_rows | 4 | 4 | Success |
| `fmtrx-altered-template` | `generic/assessment/fmtrx_assessment_template_altered.csv` | CSV | fmtrx-template | strength | players_in_rows | 4 | 4 | Blocked or rejected |
| `invalid-bad-xlsx` | `generic/invalid/bad_spreadsheet.xlsx` | XLSX | generic-csv | assessment | worksheet_per_player | 2 | 2 | Blocked or rejected |
| `invalid-extension` | `generic/invalid/unsupported.txt` | TXT | generic-csv | assessment | unknown | 0 | 0 | Blocked or rejected |
| `invalid-malformed-csv` | `generic/invalid/malformed.csv` | CSV | generic-csv | assessment | unknown | 0 | 0 | Blocked or rejected |
| `invalid-malformed-xlsx` | `generic/invalid/malformed.xlsx` | XLSX | generic-csv | assessment | unknown | 0 | 0 | Blocked or rejected |
| `platform-trackman` | `platforms/trackman/trackman_mixed_sanitized.csv` | CSV | trackman | live_ab | events_in_rows | 2 | 3 | Success |
| `platform-hittrax` | `platforms/hittrax/hittrax_hitting_sanitized.csv` | CSV | hittrax | batting_practice | events_in_rows | 2 | 2 | Success |
| `platform-rapsodo` | `platforms/rapsodo/rapsodo_pitching_sanitized.xlsx` | XLSX | rapsodo | bullpen | single_player_session | 1 | 1 | Success |
| `platform-blast` | `platforms/blast/blast_motion_sanitized.csv` | CSV | blast-motion | batting_practice | single_player_session | 1 | 1 | Success |
