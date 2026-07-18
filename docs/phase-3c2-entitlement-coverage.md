# FMTRX Phase 3C.2 entitlement enforcement audit

Status: **NO-GO for deployment pending matrix review and completion of unsafe shared-route mappings.**

This audit did not access production, change production data, deploy, commit, or push. Laravel's `GET /api/me/access` remains the only runtime access authority. RevenueCat, Apple product configuration, subscription identities, webhook identities, and permanent Laravel UUID behavior are unchanged.

## Authoritative artifacts

- `config/entitlements.php`: administrator-visible entitlement catalog.
- `config/entitlement_coverage.php`: machine-readable 52-entitlement layer mapping, access behavior, dependencies, limits, status, and gaps.
- `EntitlementCoverageRegistry`: merges catalog metadata, plan defaults, coverage, numeric limits, always-available capabilities, and the live Laravel route-gate inventory.
- Admin plan-feature response: returns `entitlements`, `coverage`, `coverage_summary`, and `coverage_summary.route_gates`.
- `GET /api/me/access`: runtime plan, audience, source, provider, entitlements, limits, team context, and access version.

The route-gate inventory is generated from Laravel's registered routes, not copied by hand. It currently contains 87 protected route/method mappings across 20 entitlement keys. Each record contains the entitlement, HTTP method, URI, controller action, and middleware. This prevents the report from drifting when routes change.

## Final entitlement coverage matrix

Layer counts are mapping counts in the registry. A nonzero count does not mean the layer is complete; the status and gaps are authoritative. No entitlement is currently marked `fully_wired`, so incomplete administrator toggles are displayed read-only in both admin clients.

| Entitlement | Audience | Default plans | Status | Backend | Web | Mobile | Blocking gaps |
|---|---|---|---|---:|---:|---:|---|
| create_session | coach | free, coach_basic, coach_pro | missing_route | 1 | 1 | 2 | backend_create_not_gated |
| record_pitches | coach | free, coach_basic, coach_pro | missing_route | 3 | 2 | 1 | backend_writes_not_gated |
| view_session_history | coach | free, coach_basic, coach_pro | missing_route | 1 | 1 | 1 | shared_history_routes_not_gated |
| scripted_bp | coach | coach_pro | missing_web_gate | 3 | 1 | 2 | web_entry_and_deep_link_not_gated |
| scripted_bullpen | coach | coach_pro | missing_web_gate | 4 | 2 | 3 | web_scripted_mode_entry_and_deep_link_not_gated |
| liveab_sessions | shared | coach_pro, player_pro | missing_route | 3 | 2 | 2 | shared_session_create_not_gated; web_start_practice_rollout_disabled |
| exit_velocity_sessions | shared | coach_pro, player_pro | missing_route | 2 | 1 | 2 | shared_session_create_not_gated; web_start_practice_rollout_disabled |
| long_toss_sessions | shared | coach_pro, player_pro | missing_route | 2 | 1 | 2 | shared_session_create_not_gated; web_start_practice_rollout_disabled |
| weighted_ball_sessions | shared | coach_pro, player_pro | missing_route | 2 | 1 | 2 | shared_session_create_not_gated; web_start_practice_rollout_disabled |
| practice_sessions | coach | coach_pro | client_only | 0 | 1 | 2 | no_distinct_backend_operation |
| view_team_stats | coach | coach_pro | missing_web_gate | 1 | 1 | 1 | web_stats_routes_not_gated |
| view_advanced_stats | shared | coach_pro, player_pro | missing_web_gate | 1 | 2 | 2 | web_deep_links_not_gated; shared_stats_payload_mixes_free_and_paid_data |
| view_own_stats | player | player_pro | missing_route | 1 | 1 | 1 | own_stats_endpoint_not_entitlement_gated; ownership_must_remain_enforced |
| personal_stats | player | player_basic, player_pro | missing_route | 1 | 1 | 2 | backend_personal_stats_reads_not_gated |
| performance_overview | coach | coach_pro | missing_web_gate | 1 | 1 | 1 | web_panel_not_gated |
| heat_maps | shared | coach_pro, player_pro | client_only | 0 | 1 | 2 | shared advanced-stat payload; no isolated operation |
| export_stats | shared | coach_pro, player_pro | client_only | 0 | 1 | 1 | export operations not mapped to a protected endpoint |
| ai_analytics | coach | coach_pro | missing_route | 1 | 1 | 1 | intelligence group uses view_advanced_stats instead |
| ai_recommendations | player | player_pro | client_only | 0 | 1 | 1 | no isolated player recommendation route |
| view_session_report | shared | coach_pro, player_pro | missing_route | 1 | 1 | 1 | report APIs use session-type gates or remain open |
| liveab_analytics | coach | coach_pro | client_only | 0 | 1 | 1 | no isolated backend operation |
| box_score | shared | coach_pro, player_pro | client_only | 0 | 1 | 1 | no isolated backend operation |
| team_recaps | coach | coach_pro | client_only | 0 | 0 | 1 | no isolated backend operation |
| player_recaps | shared | coach_pro, player_pro | client_only | 0 | 0 | 1 | no isolated backend operation |
| planner_create | coach | coach_pro | missing_route | 2 | 1 | 1 | additional planner endpoints remain open |
| plan_builder | coach | coach_pro | missing_web_gate | 1 | 1 | 2 | web builder actions not gated |
| assign_workouts | coach | coach_pro | missing_web_gate | 1 | 1 | 1 | web publish and some assignment actions remain open |
| view_workout_progress | coach | coach_pro | missing_web_gate | 1 | 1 | 1 | web progress actions not gated |
| manage_player_groups | coach | coach_pro | missing_web_gate | 1 | 1 | 1 | web group actions not gated |
| record_assessments | coach | free, coach_basic, coach_pro | missing_route | 1 | 1 | 1 | write route open; ownership/audience contract needs direct tests |
| view_assessment_reports | shared | coach_pro, player_pro | missing_web_gate | 1 | 1 | 1 | mounted web page does not react to revocation |
| view_assessment_comparisons | shared | coach_pro, player_pro | client_only | 0 | 1 | 1 | shares report response; no isolated operation |
| view_assessment_recommendations | shared | coach_pro, player_pro | missing_web_gate | 1 | 1 | 1 | web action ungated; mobile read shares report response |
| arm_care | shared | coach_pro, player_basic, player_pro | missing_web_gate | 1 | 1 | 1 | mounted web page does not react to revocation |
| roster_view | coach | free, coach_basic, coach_pro | missing_route | 1 | 1 | 1 | roster reads not gated |
| invite_players | coach | free, coach_basic, coach_pro | missing_route | 1 | 1 | 1 | add-player route not entitlement-gated |
| add_coaches | coach | free, coach_basic, coach_pro | missing_route | 1 | 1 | 1 | add-coach route not entitlement-gated |
| team_switching | coach | coach_pro | client_only | 0 | 1 | 1 | team list/switch operation not isolated |
| edit_team | coach | coach_pro | missing_route | 1 | 1 | 1 | edit-team route not gated |
| edit_player | coach | coach_pro | missing_route | 1 | 1 | 1 | edit-player route not gated |
| add_team | coach | coach_pro | missing_web_gate | 1 | 1 | 1 | web route not gated |
| manage_multiple_teams | coach | coach_pro | client_only | 0 | 1 | 1 | overlaps add_team/team_switching; no isolated operation |
| view_player_cards | coach | coach_pro | missing_web_gate | 1 | 0 | 1 | web feature absent |
| unlimited_players | coach | coach_pro | not_implemented | 0 | 0 | 0 | legacy label only; numeric player limit is authoritative |
| view_own_profile | player | player_basic, player_pro | missing_route | 1 | 1 | 1 | own-profile route not gated |
| view_own_sessions | player | player_basic, player_pro | missing_route | 1 | 1 | 1 | own-session routes not gated; historical-read decision pending |
| development_graphs | player | player_pro | missing_web_gate | 1 | 1 | 1 | backend uses view_advanced_stats; web route open |
| shareable_profile | player | player_pro | not_implemented | 0 | 0 | 0 | no verified operation |
| recruiting_profile | player | player_pro | client_only | 0 | 0 | 1 | no verified backend operation |
| sms_results | coach | coach_pro | missing_mobile_gate | 1 | 1 | 0 | mobile entry not mapped |
| notifications | shared | all plans | not_implemented | 0 | 0 | 0 | baseline label without verified runtime operation |
| recent_sessions | shared | all plans | missing_route | 1 | 1 | 1 | recent-session route not gated |

Status totals: 12 `client_only`, 1 `missing_mobile_gate`, 21 `missing_route`, 15 `missing_web_gate`, and 3 `not_implemented`. Exact totals are generated by `EntitlementCoverageRegistry::summary()` and must be refreshed after further wiring.

## Registered Laravel entitlement routes

The exact 87-record route list is available in `coverage_summary.route_gates` from the administrator plan-feature endpoint. Counts by entitlement:

| Entitlement | Route/method mappings |
|---|---:|
| add_team | 1 |
| arm_care | 2 |
| assign_workouts | 2 |
| exit_velocity_sessions | 4 |
| liveab_sessions | 6 |
| long_toss_sessions | 4 |
| manage_player_groups | 3 |
| performance_overview | 1 |
| plan_builder | 4 |
| planner_create | 7 |
| scripted_bp | 3 |
| scripted_bullpen | 8 |
| sms_results | 1 |
| view_advanced_stats | 27 |
| view_assessment_recommendations | 1 |
| view_assessment_reports | 3 |
| view_player_cards | 1 |
| view_team_stats | 1 |
| view_workout_progress | 4 |
| weighted_ball_sessions | 4 |

Coverage tests fail if a registered gate references an unknown entitlement or is missing from this generated inventory. Incomplete/open operations appear in each matrix row's `coverage.backend` and `gaps` rather than being misrepresented as protected.

## Web gates

- `resources/js/store/access.js`: fail-closed Laravel access store; a failed refresh removes stale paid authority.
- `resources/js/components/access/EntitlementGate.vue`: loading, allowed, locked, refresh, and upgrade states.
- Router gate: assessment reports, arm care, Live AB create/track, advanced training modes, session report, and practice planner redirect to the dashboard with `access_denied` when denied.
- `AdminPlans.vue`: incomplete toggles are disabled and show status plus gaps; numeric limits remain editable.
- Remaining web mappings and gaps are recorded per entitlement. Mounted-screen revocation, several action-level gates, and mixed free/paid payloads prevent deployment.

## Mobile gates

- `accessStore.canAccess()` is authoritative only after a successful Laravel refresh; app resume, team change, purchase/restore, manual refresh, and Phase 3C.1 version refresh are preserved.
- Advanced-stat screens no longer grant from `subscription_plan` or `hasFeature()`.
- Stats navigation gates Scripted BP and Scripted Bullpen using entitlement keys.
- Scripted Bullpen setup and an already-mounted bullpen session react to revocation; the mode is reset and its cached script is not used when access is absent.
- `AdminPlanFeatures` disables incomplete toggles and displays registry gaps.
- Remaining navigation rows/screens/actions are listed per entitlement in the registry and remain incomplete where the matrix says so.

Legacy `subscription_plan` values may remain for display and backward compatibility, but the audited paid gates must not grant from them.

## Scripted Bullpen enforcement

New practices persist an indexed `is_scripted` server field. The client may request scripted mode, but Laravel independently requires:

- an authenticated coach,
- active team membership/ownership,
- `scripted_bullpen` in the Laravel-resolved access summary.

Conditional middleware protects new scripted creation plus practice view, finish, delete, bullpen result create/view/update, and report access. Regular Bullpen continues through the same endpoints without requiring the paid entitlement. There is no exposed update that can convert an existing regular practice into a scripted practice.

Direct API tests cover regular access, paid scripted create/read/write, revocation across read/write/report/finish/delete, record retention, and cross-team denial. Pre-migration historical practices default to regular because no trustworthy historical server field distinguishes scripted sessions; they are not retroactively guessed from mobile storage.

The feature remains `missing_web_gate`, so its administrator toggle stays disabled.

## Historical-read decisions

- New paid creation/write operations must be blocked when the entitlement is absent.
- Existing roster/team records are never deleted when a numeric limit is lowered.
- Scripted Bullpen records remain stored after revocation; access is denied while the entitlement is absent.
- Session history, own sessions, and mixed report/stat payloads need explicit product decisions before broad read gating. They are intentionally listed as incomplete rather than changed speculatively.
- Always-available completion flows remain outside paid gating but still require authentication, assignment, ownership, and audience checks.

## Numeric limits and concurrency

Player seats, coach seats, and real-team count come from the effective Laravel plan summary. Each add operation locks its authoritative team/coach row before counting and creating, serializing concurrent additions.

Verified behavior:

- below limit succeeds;
- at limit rejects the next creation with a specific 403 code;
- unlimited permits creation;
- lowering a limit below usage preserves records and blocks new additions;
- existing records remain accessible;
- administrator limit changes require no subscription mutation.

Still incomplete: standardized usage/limit display in both clients and a true multi-connection concurrency stress test. Transactional row locking is implemented and ordinary boundary tests pass.

## Audience, ownership, and authorization

- `/api/me/access` now serializes `audience` directly; clients need not infer it from plan names.
- Player free fallback remains audience-filtered.
- Scripted Bullpen entitlement cannot bypass coach audience or team membership.
- `RequiresPlan` continues resolving explicit team context and denies cross-team access.
- Admin plan mutation remains administrator-only, audited, versioned, and reason-required.
- No RevenueCat provider record or subscription identity is changed by a plan-matrix update.

## Always-accessible capabilities

The registry records registration, login, password recovery, profile/settings, claim profile, purchase, restore purchases, assigned-workout completion, and readiness-survey completion as system capabilities outside paid gating. This does not remove authentication, assignment, ownership, or audience authorization.

## Verification

Laravel feature suites were run sequentially because they share one isolated test database. Parallel `RefreshDatabase` runs raced each other during the first attempt and were discarded as invalid evidence. No production database was used.

Results:

- Focused Laravel entitlement, RevenueCat, administration, capacity, training, and Bullpen suites: **120 tests, 1,533 assertions, all passing**.
- Coverage-registry suite: **4 tests, 1,090 assertions, all passing**, including exact catalog parity, known middleware keys, generated route inventory, and prevention of false `fully_wired` claims.
- Complete Laravel suite with the default 128 MB PHP limit: stopped after 103 tests due to memory exhaustion while loading the expanded configuration.
- Complete Laravel suite retried with `php -d memory_limit=512M`: **604 tests executed; 7 errors and 70 failures**. The failures are concentrated in legacy route-gating/factory expectations and remain a release stop; this report does not describe the complete suite as passing.
- Web frontend: **8 files, 41 tests, all passing**.
- Mobile Jest: **12 suites, 81 tests, all passing** using the repository's Watchman-safe invocation.
- Targeted mobile ESLint: **zero errors**.
- PHP formatting and changed-file syntax checks: passing.
- Composer validation and web dependency integrity: passing.
- Web production build: passing; only the existing outdated Browserslist-data warning was emitted.
- Mobile dependency integrity: `npm ls --all` exited successfully. The local Node version is 25.3.0, outside the repository's declared `>=18 <23` range, so release automation should use a supported Node version.
- Release-mode iOS JavaScript bundle: passing, with existing Watchman recrawl and `react-native-sqlite-storage` configuration warnings.
- Native iOS/Android project builds were not rerun because Phase 3C.2 changes no native files.

The focused enforcement evidence passes, but the complete Laravel regression result and incomplete registry rows preserve the **NO-GO** deployment verdict.

## Deployment and rollback plan (not executed)

Do not deploy until the matrix is reviewed and stop conditions are resolved.

Proposed order after approval:

1. Back up the target database and verify environment/database identity.
2. Deploy Laravel/web first.
3. Run `2026_07_17_000004_add_scripted_mode_to_practices.php`.
4. Clear Laravel caches and build the web client.
5. Verify the admin coverage payload and incomplete-toggle behavior.
6. Run isolated additive/revert acceptance tests and confirm audits.
7. Build/distribute mobile only after backend/web acceptance.

Rollback:

1. Revert application commits first so no code reads `practices.is_scripted`.
2. Roll back exactly one migration batch only after confirming it contains only the scripted-mode migration; otherwise run that migration's `down()` in a controlled release.
3. Clear caches and rebuild the web client.
4. Do not alter provider products, receipts, webhooks, subscriptions, or plan data during rollback.

## Controlled production acceptance checklist (not executed)

- [ ] Review and approve every incomplete matrix row.
- [ ] Confirm target environment and database identity; create backup.
- [ ] Confirm coach/player receive 403 from admin endpoints.
- [ ] Confirm incomplete toggles cannot be changed from web or mobile.
- [ ] For a completed representative entitlement in each category, perform additive enable, refresh, web/mobile/API verification, audited revert, and access removal.
- [ ] Verify Scripted Bullpen regular mode remains free and scripted direct API requests return 403 when disabled.
- [ ] Verify player, coach, and team limits at boundary without removing existing records.
- [ ] Confirm wrong audience, owner, and team remain denied.
- [ ] Confirm purchase, restore, login, registration, profile, assigned workout, and readiness survey remain available under their non-plan authorization rules.
- [ ] Confirm RevenueCat and Apple subscription/provider identities are unchanged.

## Acceptance verdict

**Phase 3C.2 is not deployment-ready.** The audit successfully exposes incomplete wiring instead of allowing administrator toggles to appear authoritative. The safe isolated Scripted Bullpen server distinction, standardized web store/gate, mobile gate corrections, route inventory, and capacity locks are candidates for review. Unsafe shared session creation, mixed analytics/report payloads, missing action-level web gates, incomplete mobile/web mappings, and unresolved historical-read rules trigger the requested stop condition.
