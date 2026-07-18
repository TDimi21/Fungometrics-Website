# FMTRX Phase 3C.2 entitlement enforcement audit

Status: **REVISED MATRIX READY FOR VERSION-CONTROL REVIEW; NOT DEPLOYED, COMMITTED, OR PUSHED.**

Laravel `GET /api/me/access` remains the runtime authority. This revision does not change RevenueCat, Apple products, receipts, webhooks, provider identities, subscription identities, or permanent Laravel UUID behavior.

## Coverage model

Every row declares its applicable platforms (`backend`, `web`, `mobile`) and one of these statuses:

- `fully_wired`: every applicable backend operation and client action is protected.
- `platform_wired`: every intentionally applicable platform is protected; an absent platform is not counted as a gap.
- `composite_wired`: a protected parent endpoint shapes its response on the server and clients gate the corresponding presentation.
- `immutable_baseline`: authenticated audience baseline, still constrained by ownership, membership, assignment, and limits.
- `disabled_incomplete`: known operation exists but the administrator control stays locked.
- `not_implemented`: hidden and non-editable because there is no verified secure workflow.
- `deprecated`: non-editable compatibility label replaced by an authoritative control.

Status totals:

| Status | Count |
|---|---:|
| fully_wired | 19 |
| platform_wired | 1 |
| composite_wired | 4 |
| immutable_baseline | 11 |
| disabled_incomplete | 13 |
| not_implemented | 2 |
| deprecated | 2 |
| **Total** | **52** |

## Revised 52-row matrix

`B/W/M` means backend, web, and mobile. A dash means no verified layer mapping is claimed.

| Entitlement | Audience | Platforms | Status | Verified mapping or genuine gap |
|---|---|---|---|---|
| create_session | coach | B/W/M | immutable_baseline | Basic creation remains available; session-type middleware blocks premium variants. |
| record_pitches | coach | B/W/M | immutable_baseline | Basic ball-by-ball writes remain available with auth and session ownership rules. |
| view_session_history | coach | B/W/M | immutable_baseline | Historical basic summaries remain readable; records are never deleted on downgrade. |
| scripted_bp | coach | B/W/M | fully_wired | Scripted creation/result APIs and report presentation require `scripted_bp`. |
| scripted_bullpen | coach | B/W/M | fully_wired | Creation, practice/result read-write, finish, delete, report, web report, and mobile entry are protected. |
| liveab_sessions | shared | B/W/M | fully_wired | Shared creation and direct result/report routes require Live AB access. |
| exit_velocity_sessions | shared | B/W/M | fully_wired | Shared creation and direct result/report routes require Exit Velocity access. |
| long_toss_sessions | shared | B/W/M | fully_wired | Shared creation and direct result/report routes require Long Toss access. |
| weighted_ball_sessions | shared | B/W/M | fully_wired | Shared creation and direct result/report routes require Weighted Ball access. |
| practice_sessions | coach | B/W/M | disabled_incomplete | No distinct authoritative backend operation is mapped. |
| view_team_stats | coach | B/W/M | fully_wired | API, web route, and mobile navigation are gated. |
| view_advanced_stats | shared | B/W/M | disabled_incomplete | Mixed legacy statistics payloads and remaining web deep links need separation. |
| view_own_stats | player | B/W/M | disabled_incomplete | Own-stats endpoint still needs an explicit entitlement plus preserved ownership. |
| personal_stats | player | B/W/M | disabled_incomplete | Personal fitness/stat reads need authoritative endpoint gating. |
| performance_overview | coach | B/W/M | disabled_incomplete | Web mounted panel still needs direct revocation handling. |
| heat_maps | shared | B/W/M | disabled_incomplete | Raw shared pitch data can still be used to compute heat maps; do not enable until safely separated. |
| export_stats | shared | B/W/M | disabled_incomplete | Export operations are not all mapped to protected server actions. |
| ai_analytics | coach | B/W/M | disabled_incomplete | Intelligence routes currently share broader gates instead of a complete AI-specific contract. |
| ai_recommendations | player | B/W/M | disabled_incomplete | No isolated or securely shaped player-recommendation response is verified. |
| view_session_report | shared | B/W/M | fully_wired | Report endpoints require report access and applicable session-type access. |
| liveab_analytics | coach | B/W/M | composite_wired | Laravel omits `calculates` unless entitled; web/mobile presentation is gated. |
| box_score | shared | B/W/M | composite_wired | Laravel omits ball-by-ball/pitcher/batter box-score data unless entitled. |
| team_recaps | coach | B/M | disabled_incomplete | Mobile screen exists but no isolated authoritative backend contract is verified. |
| player_recaps | shared | B/M | disabled_incomplete | Mobile screen exists but no isolated authoritative backend contract is verified. |
| planner_create | coach | B/W/M | fully_wired | Create/read/delete and supporting planner actions use the paid planner gate. |
| plan_builder | coach | B/W/M | fully_wired | Drill, plan, and strength-builder operations and clients are gated. |
| assign_workouts | coach | B/W/M | fully_wired | Publish, assignment, reminder, and client actions are gated. |
| view_workout_progress | coach | B/W/M | fully_wired | Progress, completion, acknowledgement, review, and clients are gated. |
| manage_player_groups | coach | B/W/M | fully_wired | Group CRUD and both clients are gated. |
| record_assessments | coach | B/W/M | immutable_baseline | Assessment entry is baseline but remains coach/team/ownership constrained. |
| view_assessment_reports | shared | B/W/M | fully_wired | Report routes and clients require report access. |
| view_assessment_comparisons | shared | B/W/M | composite_wired | Laravel strips comparison fields before serialization when absent. |
| view_assessment_recommendations | shared | B/W/M | composite_wired | Laravel strips recommendation fields before serialization when absent. |
| arm_care | shared | B/W/M | fully_wired | Read/write routes and both clients are gated. |
| roster_view | coach | B/W/M | immutable_baseline | Authenticated coach baseline; membership and limits still apply. |
| invite_players | coach | B/W/M | immutable_baseline | The numeric player limit is authoritative and locked transactionally. |
| add_coaches | coach | B/W/M | immutable_baseline | The numeric coach-seat limit is authoritative and locked transactionally. |
| team_switching | coach | B/W/M | disabled_incomplete | Team listing/switch actions need a complete distinct operation map. |
| edit_team | coach | B/W/M | fully_wired | Route, shared-team ownership, web action, and mobile action are gated. |
| edit_player | coach | B/W/M | fully_wired | Route, shared-team ownership, web action, and mobile action are gated. |
| add_team | coach | B/W/M | fully_wired | Route, capacity lock, web action, and mobile action are gated. |
| manage_multiple_teams | coach | B/W/M | deprecated | Derived from team limit plus `add_team` and `team_switching`; not editable. |
| view_player_cards | coach | B/M | platform_wired | Protected Laravel endpoint and mobile screen; web is intentionally not applicable. |
| unlimited_players | coach | B/W/M | deprecated | Numeric `players` limit is authoritative; legacy label is non-editable. |
| view_own_profile | player | B/W/M | immutable_baseline | Audience-safe own-profile access survives downgrade. |
| view_own_sessions | player | B/W/M | immutable_baseline | Audience-safe own-session history survives downgrade. |
| development_graphs | player | B/W/M | disabled_incomplete | Backend currently shares `view_advanced_stats`; web route is not fully gated. |
| shareable_profile | player | B/W/M | not_implemented | Hidden; no verified backend or client workflow. |
| recruiting_profile | player | B/M | not_implemented | Hidden; mobile surface has no verified authoritative backend operation. |
| sms_results | coach | B/W/M | fully_wired | Laravel gate plus practice ownership, web action, and mobile wrapper are enforced. |
| notifications | shared | B/W/M | immutable_baseline | Authenticated baseline; not an administrator toggle. |
| recent_sessions | shared | B/W/M | immutable_baseline | Authenticated audience baseline with existing ownership/team scoping. |

## Authoritative downgrade and historical-access policy

- Downgrades never delete practices, results, assessments, profiles, plans, assignments, or subscriptions.
- Coaches retain basic scoring and basic historical summaries.
- Players retain their profile, own-session history, assigned-workout completion, and readiness-survey completion.
- New premium session creation is denied when its session-type entitlement is absent.
- Premium reports, advanced analytics, comparisons, recommendations, heat maps, AI, and exports are denied or removed from server responses when absent.
- Coach planner creation, publishing, assignment, groups, progress review, and premium analysis remain paid.
- Laravel remains authoritative; legacy `subscription_plan` is compatibility/display state only.

## High-risk enforcement completed

- The shared training endpoint validates enum values and derives the required entitlement for Live AB, Exit Velocity, Long Toss, Weighted Ball, Scripted BP, and Scripted Bullpen.
- Missing/invalid input remains a direct 422 validation response; entitlement middleware does not replace the API's validation/not-found contract.
- Statistics bundle responses omit unauthorized premium session types and scripted practices.
- Live AB responses are server-shaped for box score, advanced matrices, and Live AB analytics.
- Assessment responses are server-shaped for comparisons and recommendations before serialization.
- Session-report routes require `view_session_report` plus the relevant session-type entitlement.
- Planner create/publish/assign/group/progress endpoints are protected.
- Team/player edit routes require entitlement and shared-team authorization.
- Web mounted views refresh access and react to revocation; paid deep links are router-gated.
- Mobile SMS rendering/action is wrapped by `sms_results`; the server also verifies session ownership.
- `/api/me/access` returns limits, current usage, and remaining capacity; web and mobile display them.
- Player, coach, and team capacity paths use database row locks. A two-connection MySQL lock-contention test verifies serialization.
- Scripted Bullpen remains server-distinguished by `practices.is_scripted`; regular Bullpen remains baseline and historical records remain stored after revocation.

## Regression comparison

Both repositories used the same isolated database safety rules. The exact command was run at the Phase 3C base commit `f8d63c3` and again with the revised Phase 3C.2 worktree:

```text
APP_ENV=testing DB_DATABASE=fungo_test php -d memory_limit=512M vendor/bin/phpunit
```

The base and revised repositories contain different test totals because Phase 3C.2 adds coverage tests. Comparison is by fully qualified failing/error test name, not by the randomized aggregate count alone.

| Run | Tests | Assertions | Errors | Failures |
|---|---:|---:|---:|---:|
| Phase 3C base (`f8d63c3`) | 590 | 2,011 | 7 | 99 |
| Revised Phase 3C.2 | 607 | 3,515 | 7 | 76 |

The final fully qualified test-name comparison contains 106 failing/error names in the Phase 3C base and 83 in the revised worktree, with **zero revised-only names**. One prior full run produced an unrelated Faker duplicate-email error; that test passed in isolation and the repeated complete run did not reproduce it. The known base failures are dominated by legacy random factory expectations, response-status expectations, and the existing `count_s_b` test-schema mismatch; they are not represented as passing.

## Remaining genuine security gaps

Administrator toggles remain disabled for all 13 `disabled_incomplete` rows:

- `practice_sessions`
- `view_advanced_stats`
- `view_own_stats`
- `personal_stats`
- `performance_overview`
- `heat_maps`
- `export_stats`
- `ai_analytics`
- `ai_recommendations`
- `team_recaps`
- `player_recaps`
- `team_switching`
- `development_graphs`

`shareable_profile` and `recruiting_profile` remain hidden and non-editable. `unlimited_players` and `manage_multiple_teams` remain deprecated and non-editable. These rows cannot be changed through either administrator client or the protected update API.

## Verification summary

- Coverage registry: passes with exact 52-row parity, valid statuses/platforms, known route gates, and no false `fully_wired` rows.
- Premium session creation: direct denial/allowance tests cover all six derived premium variants.
- Scripted Bullpen: direct create/read/write/report/revocation/retention/cross-team tests pass.
- Numeric concurrency: real two-connection MySQL lock-contention test passes.
- Web unit tests and production build pass.
- Focused mobile access/admin/SMS/RevenueCat tests pass; targeted ESLint has no errors.
- PHP formatting, syntax checks, dependency integrity, and `git diff --check` pass.
- Exact full-suite comparison: 0 revised-only failing/error test names.
- No production or staging access occurred. No deploy, commit, or push occurred in this revision.

## Verdict

The normalized matrix now contains legitimate fully-wired, platform-wired, composite-wired, immutable, disabled, unimplemented, and deprecated rows. The final exact regression-name comparison contains zero revised-only failures or errors, so the matrix is ready for version-control review. Deployment remains out of scope.
