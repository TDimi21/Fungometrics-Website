# FMTRX account deletion policy

Policy version: 2026-07-23

Deletion requires current-password verification followed by a short-lived,
single-use destructive confirmation token and the exact phrase `DELETE`.

| Data | Action |
|---|---|
| API tokens and sessions | Immediately revoked |
| Coach/player team memberships and lineups | Deleted |
| Name, email, phone, password and profile photo | Anonymized or removed |
| Player demographic/contact fields and photos | Anonymized |
| Assessment notes and coach insights | Removed |
| Fitness/assessment numeric measurements | Retained only against the anonymized user UUID for aggregate/statistical integrity |
| Practice/session participation | Membership links removed; team/session aggregates may remain without personal identity |
| Free-form notes | Removed where associated with the deleting account |
| Subscription/provider records | Retained in minimized anonymized form for entitlement, refund, fraud, accounting and dispute obligations |
| Security/billing audits | Retained with hashed network metadata and anonymized/deleted user identity where schema permits |
| Uploaded profile/player media | Database references are removed and referenced objects are deleted from the configured public or S3 disk during the deletion transaction |

Deleting FMTRX does not cancel an Apple subscription. The app provides the
Apple subscription-management link before final confirmation.
