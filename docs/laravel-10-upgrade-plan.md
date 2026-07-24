# Controlled Laravel 10 upgrade plan

## Objective and boundary

Upgrade FMTRX from Laravel 9.52.21 to the latest supported Laravel 10 release
without changing production in place. The upgrade is prepared and rehearsed on
a branch and an isolated production-shaped MariaDB copy. Production remains on
the current application release until the complete gate passes.

## Confirmed dependency blockers

`composer prohibits laravel/framework ^10.0 --tree` identifies these required
changes:

- Change the root `laravel/framework` constraint from `^9.19` to `^10.0`.
- Upgrade `nunomaduro/larastan` 1.x to the Laravel 10-compatible major.
- Upgrade `spatie/laravel-ignition` 1.x to the Laravel 10-compatible major.
- Upgrade Monolog 2 to Monolog 3 through the framework dependency update.

Laravel Sanctum 3, Laravel UI 4, Telescope 4, Inertia Laravel 0.6, Livewire
2.12, Tinker 2, request-docs 2.26, Pest Laravel 1, and the current log viewer
declare Laravel 10 compatibility. They still require regression testing and
should be updated only where Composer requires it.

## Controlled sequence

1. Create a dedicated upgrade branch from a clean, tagged release candidate.
2. Capture the current `composer.lock`, application configuration, route list,
   queue schedule, and database schema dump as rollback evidence.
3. Build an isolated database using the exact production MariaDB version and a
   sanitized production-shaped dataset.
4. Update only the framework constraint, Larastan, Ignition, and directly
   required transitive packages using an explicit Composer command with
   `--with-all-dependencies`. Review every lockfile delta.
5. Apply Laravel 10 application changes for exception handling, validation,
   middleware, model casting, mail, filesystem, queues, and test helpers.
6. Run static analysis, route and configuration cache builds, all targeted
   authorization/billing/account-deletion tests, and the complete PHPUnit suite
   twice.
7. Rehearse migrations forward, rollback, and forward again on the isolated
   MariaDB instance.
8. Run web and mobile API contract tests, then authenticated coach/player
   browser journeys including refresh, team switching, and entitlement denial.
9. Produce a release candidate and observe it in a non-production environment
   before scheduling production.

## Rollback boundary

The rollback unit is the complete Laravel 10 release: application artifact,
`composer.lock`, configuration cache, queue workers, and any migrations shipped
with it. Do not roll back PHP code independently of schema changes. Before
deployment, classify every migration as reversible or forward-only and take a
verified database backup. If health, authentication, billing reconciliation,
or queue checks fail, stop workers, restore the prior application artifact and
lockfile, reverse only migrations proven reversible, and otherwise restore the
pre-release database backup.

## Current status

Planning only. No Laravel packages, production databases, credentials, or
provider configuration were changed by this work.
