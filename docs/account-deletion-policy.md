# FMTRX account deletion policy

Policy version: 2026-07-23

Implementation inventory corrected 2026-10-22; retained-data policy still requires owner review.

Deletion requires current-password verification followed by a short-lived,
single-use destructive confirmation token and the exact phrase `DELETE`.

| Data | Action |
|---|---|
| API tokens and sessions | Immediately revoked |
| Coach/player team memberships and lineups | Deleted |
| Name, email, phone, password and profile photo | Anonymized or removed |
| Player demographic/contact fields and photos | Anonymized |
| Player date of birth (`players.born_date`) | Cleared |
| Invitations | Recipient email anonymized; token/code hashes cleared; invitations revoked. Pending invitations authored by a deleted coach are revoked. |
| Communications preferences | Deleted |
| Communications event/delivery snapshots tied to the account | Personal payload cleared; queued pending deliveries suppressed; events marked processed. Minimal UUID/deduplication and delivery audit history retained. Email already handed to a provider cannot be recalled. |
| Assessment notes and coach insights | Removed |
| Fitness/assessment numeric measurements | Retained only against the anonymized user UUID for aggregate/statistical integrity |
| Practice/session participation | Membership links removed; team/session aggregates may remain without personal identity |
| Free-form notes | Removed where associated with the deleting account |
| Subscription/provider records | Retained in minimized anonymized form for entitlement, refund, fraud, accounting and dispute obligations |
| Security/billing audits | Retained with hashed network metadata and anonymized/deleted user identity where schema permits |
| Uploaded profile/player media | Database references are removed and referenced objects are deleted from the configured public or S3 disk during the deletion transaction |

Deleting FMTRX does not cancel an Apple subscription. The app provides the
Apple subscription-management link before final confirmation.
