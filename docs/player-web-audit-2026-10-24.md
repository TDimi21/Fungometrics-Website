# Player web audit — 2026-10-24

Scope: Fungoweb player navigation, personal scoring, session statistics/reports, connected-team sharing, workout launch/linking, and regression coverage for account/profile, dashboard, assessments, development, and permissions. Changes are local until committed, CI passes, and deployed.

## Findings repaired

| Area | Confirmed issue | Repair |
| --- | --- | --- |
| Workout launch | Player Today submitted a team-owned session request that self-scoring authorization rejects. | Launch through personal setup with explicit team-sharing selection. Assigned workout links require sharing with the assigned team, keep the player as owner, and remain retry-safe. Existing linked sessions open directly. |
| Multiple teams | Secondary selected teams could open shared sessions but lose the results from aggregate statistics. Bullpen overview could also include personal/unshared results by pitcher alone. | Team result queries include canonical or shared-team sessions once, and bullpen overview now applies team scope. Tests cover all six modes and exclusion of private results. |
| Player statistics | Batting/bullpen trusted the optional player flag; training-mode session statistics ignored it. | Authenticated players receive their own result rows regardless of the query flag. Coach aggregate views remain available. |
| Session lists | Batting/bullpen/cage responses included teammates’ results; lists paginated without stable date ordering. | Filter nested results to the player and sort on the server before pagination. Player search filters session notes. |
| Session navigation | Next/Previous buttons ignored their links; unknown session slugs could throw. | Parse valid page numbers from API links, disable unavailable/current links, and handle unknown slugs without a crash. |
| Statistics picker | Only the first page loaded; Live AB incorrectly queried training-mode sessions. | Load every page from the correct endpoint, with mode filtering and no credential forwarding to response-provided hosts. |
| Session Reports | Reports stopped at eight entries, older sessions disappeared, Live AB participation was omitted, training counts were incomplete, and in-progress entries counted as completed. | Load full paginated history, retain unfinished sessions, use canonical training result arrays, and normalize completion state. Failures display an error instead of silently appearing as empty history. |
| Navigation permissions | New Statistics required a coach-only entitlement. Generic dashboard fallbacks opened the coach page for players. | Use personal statistics access for players and redirect dashboard fallbacks to player home. Player report scope follows the signed-in role. |
| Connected teams | An in-memory enum player role could fail the strict string comparison. | Normalize string/enum roles consistently in list/leave handlers. |
| CI reliability | The login-history test compared against a moving clock and could fail at a one-second boundary. | Freeze test time; no production timestamp behavior changed. |
| Error feedback | Every session-creation 403 claimed the subscription was insufficient, including membership/authorization failures. | Reserve that message for an explicit entitlement failure and retain server error details for other failures. |

## Verification

- Registered route inventory: 436 controller-backed routes; all controller classes and methods resolve. This establishes registration integrity, not production reachability.
- Frontend tests exercise all six personal setup destinations, planner launch/resume, optional multi-team selection, full report pagination, completed counts, statistics navigation and access guards.
- Backend regression tests cover create/read/save/edit rejection/finish for six personal modes; shared-session read-only coaches and unrelated-user denial; multi-team aggregate consistency; planner assignment ownership/idempotency; player-only session result reads.
- The full backend suite also covers existing authentication/claim/deletion, profile, dashboard, assessment/development, planner/workout, entitlement and API security behavior.
- Frontend: 482 tests across 77 files passed. Production Vite build passed.
- Backend: 1,221 tests and 10,807 assertions passed after repairing the admin test clock race.
- `git diff --check` passed.

## Limits and release checks

No production database writes, SSH deployment, real email/SMS delivery, purchases, or destructive account operations were performed. No interactive authenticated browser walkthrough was completed: the browser execution tool is unavailable in this session. Automated component logic tests and production compilation are not a substitute for visual/browser QA.

After successful GitHub CI on both MariaDB versions, deploy and use a test player to check: sign in; dashboard/profile/team connections; each of the six session modes; save, refresh/resume, undo, stats and report; personal versus two selected teams; both coaches’ read-only access; assigned workout creation/resume/completion; older session pagination; logout. Verify a second player cannot access or write the first player's sessions.

This patch adds no migration. The previously added player-sharing migrations still need to be present on production. The earlier production cron/queue problem remains unverified by this local audit. Mail/SMS delivery and background work require a separate production check. Existing Vite bundle-size warnings remain a performance follow-up; they do not prevent compilation.
