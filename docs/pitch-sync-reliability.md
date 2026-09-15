# Bullpen pitch synchronization fix

The mobile bullpen recorder now stores each pitch in SQLite before sending it, keeps its request identity through retries, and requires the backend to acknowledge both that identity and the session before marking it synchronized. SQLite writes are acknowledged after transaction commit. Network failures, timeouts, throttling, and authentication/access interruptions retain pending pitches with backoff. Validation failures remain stored as failed and are never mistaken for duplicates.

Replay is serialized, scoped to the recording account, and runs on reconnect, foregrounding, and a foreground timer. It uses the direct HTTP client so a second generic queue cannot independently replay the same pitch. Mobile save buttons reject overlapping taps. Both mobile save branches use identical pitch payload construction. Pending pitches are merged into the modern bullpen display by request identity so server refreshes do not erase them or count an accepted pitch twice.

The web bullpen form retains failed submissions rather than clearing their inputs. Retries on that form use the same request identity, and counters advance only after confirmation. Its pending submission identity is held for the mounted form; it is not a general browser offline queue.

## API and database

- `GET /api/result/bullpen/sync-capabilities` requires authentication and advertises `idempotency_version: 1` after the new column exists. Mobile retains pitches locally until this capability is confirmed.
- `POST /api/result/bullpen` accepts an optional `client_request_id` (up to 64 letters, numbers, underscores, or hyphens).
- The first successful save returns 201; an identical retry returns 200 with the same result ID and a top-level `client_request_id` acknowledgement.
- Reusing an identity for changed validated data or a different session returns 409. Replaying a deleted result returns 410 and cannot resurrect it.
- A unique `(recorded_by, client_request_id)` database constraint protects identity reuse. A transaction and session row lock serialize pitch ordering and same-session requests. Request fingerprints remain unchanged when a result is edited.
- Requests without identities remain accepted for older clients, but do not gain deduplication. Two distinct pitches with identical measurements must use different identities.

## Rollout and historical records

Deploy migration `2026_09_14_000000_add_bullpen_request_identity.php` and the backend before releasing the new mobile build. The capability endpoint prevents new mobile uploads to an older backend that cannot deduplicate them. The migration was executed only on the local test database during verification; production was not changed.

Existing rows in the mobile event table do not establish a reliable recording-account identity or server acknowledgement. They are retained, not automatically assigned to the next account or replayed. Pitches previously marked synchronized incorrectly, already duplicated in server data, or discarded by older queues require reconciliation against session records. This change does not automatically delete duplicate-looking historical pitches: identical measurements can represent different real pitches.

## Verification

- 153 backend regression tests passed (673 assertions), including 10 new capability/idempotency tests and prior authorization checks.
- 86 mobile service tests passed, including 17 outbox tests using real SQLite statements and 2 transaction-commit tests. One pre-existing failure remains: `homePlayer.js` is 3,510 lines against its 3,500-line budget.
- 3 web submission tests passed. The changed Vue script/template and React Native screens compile/parse.
- No production traffic, native-device end-to-end run, or historical data repair was performed.
