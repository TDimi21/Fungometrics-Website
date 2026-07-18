# FMTRX Phase 3C.3 entitlement-control completion report

Status: **IMPLEMENTED, COMMITTED, AND PUSHED; NOT DEPLOYED OR RUN AGAINST PRODUCTION.**

- Laravel/web implementation: `29f1b8e44cc1b605f8b8fde31f4021dbd6b86f26`
  on `origin/main`.
- Mobile implementation: `c9ea0b7f3b0deabd074b2a757058d11dc868f73f`
  on `origin/TomsScreeens`.
- This report correction remains uncommitted until the deployment-scope review
  is explicitly approved.
- The published Laravel/web commit also contains an unrelated Top-10 dashboard
  redesign. Its separation is prepared for review below and has not been
  committed or pushed.

Laravel `GET /api/me/access` remains the only runtime plan-feature authority. This phase does not change prices, RevenueCat, Apple products, receipts, webhooks, subscription/provider identities, permanent Laravel UUIDs, or production data.

## Reviewed Phase 3C.2 input

The exact 13 `disabled_incomplete` controls at the reviewed Phase 3C.2 base (`ca1e9183c2717ec1f4f9147d25a63a3f8c3c75f7`) were:

1. `practice_sessions`
2. `view_advanced_stats`
3. `view_own_stats`
4. `personal_stats`
5. `performance_overview`
6. `heat_maps`
7. `export_stats`
8. `ai_analytics`
9. `ai_recommendations`
10. `team_recaps`
11. `player_recaps`
12. `team_switching`
13. `development_graphs`

## Final classification totals

| Classification | Count |
|---|---:|
| `fully_wired` | 20 |
| `platform_wired` | 2 |
| `composite_wired` | 4 |
| `immutable_baseline` | 11 |
| `disabled_incomplete` | 7 |
| `not_implemented` | 5 |
| `deprecated` | 3 |
| **Total** | **52** |

An administrator control is editable only when its status is `fully_wired`, `platform_wired`, or `composite_wired`. Baselines and deprecated controls remain visible but non-editable; not-implemented controls remain hidden and non-editable.

## Decisions for all 13 reviewed controls

| Entitlement | Phase 3C.3 decision | Applicable enforcement or reason |
|---|---|---|
| `practice_sessions` | `deprecated` | It duplicated the authoritative `planner_create` operation. Laravel plan resolution now strips the legacy key; mobile practice entry and create/list/session routes use `planner_create`. |
| `view_advanced_stats` | `disabled_incomplete` | Shared statistics payloads and coach/player pages still mix free and premium data. Enabling this would expose data that is merely hidden by clients. |
| `view_own_stats` | `disabled_incomplete` | The own-statistics endpoint and mixed player dashboard do not yet separate basic history from advanced personal statistics. |
| `personal_stats` | `disabled_incomplete` | Fitness/metrics reads are mixed with baseline player data and lack a safe independently protected contract. |
| `performance_overview` | `fully_wired` | `GET /api/coach/performance-overview/{team}` requires the entitlement and existing team authorization. Web and mobile deny while access is unverified, avoid the request when denied, clear paid score/detail state on revocation, and render an upgrade state. |
| `heat_maps` | `disabled_incomplete` | Raw shared pitch data can still be used to reconstruct heat maps, so a UI-only gate would not protect the premium data. |
| `export_stats` | `disabled_incomplete` | Export operations do not yet have one complete server-authorized operation map. |
| `ai_analytics` | `disabled_incomplete` | Intelligence endpoints and planner/development presentation remain mixed with broader controls. |
| `ai_recommendations` | `not_implemented` | No isolated player-recommendation server contract exists. The control is hidden, non-editable, and stripped from runtime grants. |
| `team_recaps` | `not_implemented` | No distinct authoritative team-recap backend contract exists. Existing session-recap presentation uses `view_session_report`; the unused label is hidden and stripped. |
| `player_recaps` | `not_implemented` | No distinct authoritative player-recap backend contract exists. Existing session-recap presentation uses `view_session_report`; the unused label is hidden and stripped. |
| `team_switching` | `disabled_incomplete` | Team selection occurs through multiple client paths and does not have a single safely separable server operation. Ownership/membership remains authoritative. |
| `development_graphs` | `platform_wired` (`backend`, `web`) | Player routes now require `development_graphs` rather than the broader `view_advanced_stats`: `GET /api/player/development/players/{player}` and `GET /api/player/development/teams/{team}/players/{player}`. The web `development.player` route already uses the same entitlement. Owner, audience, and team-membership checks remain enforced. No distinct mobile graph surface is claimed. |

## Runtime and client behavior

### Performance Overview

- Backend route: `GET /api/coach/performance-overview/{team}`.
- Backend authorization: authenticated coach, `performance_overview`, and existing team relationship checks.
- Web mapping: `resources/js/pages/dashboard/Index.vue`.
- Mobile mapping: `src/components/TeamStatsPanel/index.js`.
- Revocation: both mounted clients stop requests and clear the previously loaded paid score/detail state; mobile closes paid detail modals.
- Loading: web does not render paid data while authoritative access is unresolved.
- Direct API access: absent entitlement returns `403`.
- Administration: an enable/revert round trip changes `/api/me/access` immediately, writes two audits, and does not change subscription rows.

### Development Graphs

- Backend player-self route: `GET /api/player/development/players/{player}`.
- Backend team-context route: `GET /api/player/development/teams/{team}/players/{player}`.
- Web mapping: router entry `development.player` with `development_graphs` metadata.
- Authorization: absent entitlement, wrong audience, wrong owner, and wrong team return `403`.
- Historical records are not deleted when access is removed.
- Mobile is intentionally not listed as applicable because there is no verified distinct development-graph screen.

### Practice and recap compatibility

- `planner_create` is the one authoritative control for practice-plan creation and administration.
- Players retain assigned-workout viewing/completion and readiness-survey completion under the existing system-capability policy.
- The legacy `practice_sessions` grant cannot contradict `planner_create` because it is non-editable and removed during entitlement resolution.
- `PlayerRecapScreen` and `TeamSessionRecapScreen` are session-report presentations and therefore use `view_session_report`; the unused standalone recap labels are not exposed as admin controls.

## Composite response and historical-read policy

- `liveab_analytics` and `box_score` remain server-shaped within the protected Live AB response.
- Assessment comparisons and recommendations remain server-shaped within protected assessment contracts.
- Existing session, assessment, planner, workout, profile, and subscription records are never deleted on downgrade.
- Basic coach scoring/history and player own-profile/own-session history remain readable where already approved.
- New premium creation and premium detail/report/analytics/heat-map/AI/export access remain blocked when the required entitlement is absent.
- Assigned players can continue valid workouts and readiness surveys after a coach-plan downgrade.

## Numeric limits

Phase 3C.3 does not change numeric limit enforcement.

- `players`, `coaches`, and `teams` remain Laravel-authoritative.
- Below-limit creation remains allowed; the next addition at the limit returns the existing specific `403` response.
- `null` remains unlimited.
- Lowering a limit never deletes or hides existing records.
- Database locking continues to serialize concurrent additions.
- Deprecated `unlimited_players` and `manage_multiple_teams` are removed from runtime grants and cannot contradict numeric limits.

## Remaining disabled controls

The following seven controls remain visible but non-editable because secure separation is incomplete:

- `view_advanced_stats`
- `view_own_stats`
- `personal_stats`
- `heat_maps`
- `export_stats`
- `ai_analytics`
- `team_switching`

These are deliberate security stops, not completed work. None is described as enforced merely because a client screen has a lock.

## Hidden not-implemented and deprecated controls

Hidden `not_implemented` controls:

- `ai_recommendations`
- `team_recaps`
- `player_recaps`
- `shareable_profile`
- `recruiting_profile`

Visible, non-editable deprecated controls:

- `practice_sessions` (replaced by `planner_create`)
- `unlimited_players` (replaced by numeric `players` limit)
- `manage_multiple_teams` (derived from numeric `teams` limit plus authoritative team operations)

All eight keys are filtered from runtime grants even if legacy database rows still reference them.

## Regression comparison

Both runs use the same isolated `fungo_test` configuration and command:

```text
APP_ENV=testing DB_DATABASE=fungo_test php -d memory_limit=512M vendor/bin/phpunit
```

| Run | Tests | Assertions | Errors | Failures |
|---|---:|---:|---:|---:|
| Phase 3C.2 base (`ca1e9183`) | 607 | 3,514 | 8 | 75 |
| Phase 3C.3 worktree | 625 | 3,600 | 7 | 76 |

The final comparison is made by fully qualified JUnit test-case name. Both runs contain the same 83 unique failing/error test names: **zero Phase 3C.3-only names and zero Phase 3C.2-only names**. The aggregate moved from 8 errors/75 failures to 7 errors/76 failures because one identical failing test changed failure category; it did not introduce a new failing test name. The complete Laravel suite is not described as passing.

## Verification results

- Phase 3C.3 matrix, coverage registry, and premium route-gating slice: 18 tests / 80 assertions passed.
- Entitlement resolver, coverage, Plan Features administration, RevenueCat, premium gating, scripted bullpen, capacity concurrency, SMS, and intelligence slice: 36 tests / 1,535 assertions passed.
- Full training/session slice: 130 tests with 4 preexisting failures (three premium write fixtures expect success without paid grants; one legacy Live AB edit fixture expects `200` but receives validation `422`).
- Complete Laravel suite: 625 tests / 3,600 assertions / 7 errors / 76 failures; exact-name comparison found zero new regression names.
- Web Vitest suite: 8 files / 45 tests passed.
- Mobile Jest suite: 12 suites / 84 tests passed using `--watchman=false`.
- Web production build: passed; existing outdated Browserslist and large-chunk warnings remain.
- iOS release JavaScript bundle: passed and copied 75 asset files; existing Watchman recrawl and `react-native-sqlite-storage` configuration warnings remain.
- Mobile targeted ESLint: 0 errors and 71 preexisting warnings in the touched legacy files.
- Web ESLint: unavailable because this repository has neither an ESLint dependency nor an ESLint configuration; `npx` resolved ESLint 10 and stopped before linting. The Vite production build and Vitest compilation both passed.
- PHP Pint and syntax checks: passed for every changed PHP file.
- Composer validation and platform requirements: passed.
- Web and mobile `npm ls --depth=0`: passed; web reports the already-installed `esbuild-wasm` package as extraneous.
- Mobile Prettier check reports the four touched legacy files as preexisting whole-file formatting debt; Phase 3C.3 did not perform a broad mechanical rewrite.
- `git diff --check`: passed in both repositories.
- No native files changed, so a native rebuild was not required by the Phase 3C.3 verification rule.

## Files changed

Laravel/web:

- `app/Services/Access/EntitlementResolver.php`
- `config/entitlement_coverage.php`
- `config/entitlements.php`
- `routes/api.php`
- `resources/js/pages/dashboard/Index.vue`
- `tests/Feature/Api/Access/Phase3C3EntitlementMatrixTest.php`
- `tests/frontend/entitlementGateCoverage.spec.js`
- `docs/phase-3c3-entitlement-coverage.md`

`resources/js/pages/dashboard/Index.vue` is the only mixed file in the published
Laravel/web commit. Its exact scoped inventory appears below. Newer uncommitted
Hall-of-Fame/Top-10 work in the shared main worktree is also unrelated and has
been left untouched.

Mobile:

- `src/components/TeamStatsPanel/index.js`
- `src/navigations/Stacks/StatsStack.js`
- `src/navigations/TopTabNavigator.js`
- `src/utils/__tests__/entitlementGateCoverage.test.js`

## Repository-scope separation review

The complete reviewed ranges are:

- Laravel/web: `ca1e9183c2717ec1f4f9147d25a63a3f8c3c75f7..29f1b8e44cc1b605f8b8fde31f4021dbd6b86f26`
- Mobile: `ef8c2417727ca4f10c277585f07bbdd393cc7140..c9ea0b7f3b0deabd074b2a757058d11dc868f73f`

Every Laravel/web file other than `resources/js/pages/dashboard/Index.vue` is
Phase 3C.3 entitlement implementation, tests, or this report. All four mobile
files are Phase 3C.3 entitlement changes. No other committed file or hunk is in
either range.

### `Index.vue` Phase 3C.3 hunks

Line references below are from committed file `29f1b8e`:

- line 8: import authoritative `useAccessStore`.
- line 39: create the access-store instance.
- lines 626-632: compute `performance_overview` access and define paid-state
  clearing.
- lines 705-708: prevent the Performance Overview request when access is absent.
- lines 781-791: react to grant/revocation and clear mounted paid state.
- lines 2578-2583: refuse cached premium Performance Overview data without
  current authoritative access.
- lines 2773-2783: render loading/denied states before paid data and make the
  performance skeleton part of the fail-closed chain.

### `Index.vue` unrelated committed Top-10 hunks

Line references below are from committed file `29f1b8e`:

- lines 408-414: numeric leaderboard formatter.
- lines 557-608: six-category Top-10 metadata, parallel loading, card rows,
  leader/subtitle helpers, and selected-card state.
- line 2620: preload all Top-10 categories during dashboard refresh.
- lines 2981-3115: full-width Top-10 layout, category cards, range controls,
  selected-category bar, and redesigned team leaderboard.

The current main worktree additionally contains an uncommitted
`HallOfFameWall.vue` component and corresponding `Index.vue` integration. Those
changes are newer than `29f1b8e`, are not part of either reviewed commit range,
and were not modified during this separation review.

### Prepared separation (not committed or pushed)

- Local preservation branch `preserve/top10-dashboard-mixed-29f1b8e` points to
  the exact published mixed commit.
- An isolated detached review worktree was prepared from `29f1b8e` with only
  the committed Top-10 hunks reversed. The proposed corrective diff changes
  only `resources/js/pages/dashboard/Index.vue` (74 insertions, 171 deletions)
  and leaves exactly the seven Phase 3C.3 Performance Overview groups listed
  above when compared with `ca1e9183`.
- The proposed entitlement-only dashboard passes the focused web entitlement
  tests (4/4) and a production Vite build.
- No corrective commit exists yet. Therefore a final Laravel/web deployment
  hash cannot be stated without violating the instruction not to commit before
  review. The mobile deployment candidate remains exactly `c9ea0b7`.

## Migration requirements

No database migration is required. Phase 3C.3 changes runtime entitlement
classification, route middleware, client enforcement, tests, and documentation
only. No production data, plan definition, entitlement audit, subscription,
provider identity, RevenueCat configuration, or App Store configuration was
changed.

## Deployment and rollback plan (not executed)

1. Review the prepared one-file corrective diff and this report correction.
2. After explicit approval, preserve the newer Hall-of-Fame work on its own
   feature branch/commit without pushing it unless separately authorized.
3. Apply the reviewed Top-10 reversal to `main` as a new forward commit; do not
   rewrite or force-push `29f1b8e`.
4. Commit the approved report correction, rerun the web test/build checks, and
   record the resulting Laravel/web deployment hash. Mobile remains `c9ea0b7`.
5. Back up the deployed database and verify production database identity before
   deployment, even though no migration is required.
6. Deploy Laravel/web first, clear Laravel caches, build the web client, and
   verify the admin catalog classifications.
7. Perform an additive-then-revert test on `performance_overview` using a
   dedicated coach account; verify `/api/me/access`, direct-route behavior,
   both audit rows, and unchanged RevenueCat subscription identity/count.
8. Verify a dedicated player with `development_graphs` can access only their
   own graph and loses it immediately after a controlled revert.
9. Distribute mobile commit `c9ea0b7` only after backend/web acceptance.
10. Roll back with new forward revert commits that restore the deployed
    Laravel/web tree to `ca1e9183` and mobile tree to `ef8c241`; do not reset or
    rewrite published history. Clear Laravel caches and rebuild the web client.
    No schema rollback is needed, and plan definitions, audits, subscriptions,
    provider identities, and customer data must remain untouched.

## Controlled production acceptance checklist (not executed)

- [ ] Production database identity and backup confirmed.
- [ ] Laravel/web deployment commit matches the approved hash.
- [ ] Mobile distribution commit matches the approved hash.
- [ ] Admin catalog reports 52 rows with the counts above.
- [ ] Hidden and deprecated controls cannot be edited by either admin client or direct API.
- [ ] Add `performance_overview` temporarily to a dedicated coach plan with a reason.
- [ ] `/api/me/access`, web, mobile, and direct route unlock without restarting.
- [ ] Revert it with a second reason; mounted web/mobile state clears and direct route returns `403`.
- [ ] Both audits exist and RevenueCat subscriptions/provider identities are unchanged.
- [ ] A dedicated player receives `development_graphs` only when granted and cannot read another player/team.
- [ ] No production purchase, provider change, pricing change, or migration occurs as part of acceptance.
