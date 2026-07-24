# FMTRX Laravel Major Upgrade Plan

Date: 2026-07-23

## Boundary and objective

Upgrade from Laravel 9.52.21 to a supported release containing the signed-URL,
email-validation, and wildcard-file-validation fixes (minimum currently
Laravel 12.61.1). Do this on a dedicated branch after the iOS release review.
The rollback boundary is the pre-upgrade Git commit plus a database backup
taken before the first upgrade-only migration. Do not mix product features,
schema cleanup, or provider changes into the upgrade.

## Sequence

1. Freeze the reviewed Laravel 9 lockfile and capture both full PHPUnit runs,
   route list, queue inventory, scheduler inventory, and isolated MariaDB
   rehearsal as the behavioral baseline.
2. Upgrade one supported major at a time (9→10→11→12), following each official
   upgrade guide. Keep PHP, Sanctum, Telescope, Inertia, Livewire, Pest/PHPUnit,
   logging, and request-doc packages on compatible stable constraints.
3. At every major: run authentication/authorization, billing webhook replay,
   `/api/me/access`, account deletion, uploads, mail/reset, signed-URL guard,
   queues, scheduler, web tests, and the complete PHPUnit suite twice.
4. Rehearse all migrations forward/rollback/forward against the exact
   production MariaDB version. Deploy code separately from optional schema
   work, with backward-compatible migrations only.
5. On Laravel 12.61.1+, remove compensating controls only after equivalent
   regression tests prove the framework fixes and application behavior.

## Rollback

If any major fails its gates, restore `composer.json`/`composer.lock` and code to
the last passing major commit and reinstall from that reviewed lockfile. If an
upgrade-only migration ran in staging, execute its tested `down()` before code
rollback. Production rollout must use a maintenance window, verified backup,
health checks, and an explicit go/no-go owner; none of those actions were
performed in this review.
