# AI Coding Agent Instructions for Fungoweb (Laravel Backend)

## Overview
Laravel 9 backend for the **Fungo Metrics** baseball training platform. Serves:
1. A REST API consumed by the React Native mobile app (`/api/*` — Sanctum token auth)
2. A web admin UI via **Inertia.js + Vue 3 + Tailwind + Vite**

## Architecture

### Single-Action Invokable Controllers — Always
Every controller has exactly one `__invoke()` method. No resource controllers. Name each class after its action:
```
app/Http/Controllers/Api/Training/AddNewSession.php        → POST /api/training
app/Http/Controllers/Api/Training/Result/SaveBattingResultPractice.php → POST /api/result/batting
app/Http/Controllers/Api/Sessions/GetPracticeSessionByMode.php         → GET  /api/sessions/mode
```
Folders: `Admin/`, `Auth/`, `Coach/`, `DashBoard/`, `Player/`, `Sessions/`, `Training/`, `Training/Result/`.

### Consistent JSON Response Envelope
```php
return response()->json([
    'code'    => '008',       // short numeric code; append '-E' on errors
    'message' => 'save batting training result',
    'status'  => 'success',   // or 'error'
    'data'    => $result,
], Response::HTTP_CREATED);
```
Use `Symfony\Component\HttpFoundation\Response` constants for HTTP codes. Wrap all logic in `try/catch`; on failure return `HTTP_INTERNAL_SERVER_ERROR`. Wrap DB writes in `DB::beginTransaction()` / `commit()` / `rollBack()`.

### Service Layer
Generic CRUD services in `app/Services/` — instantiate directly in controllers with a model instance:
```php
(new CreateServiceData(new BattingPracticeResult()))->handle($data);
(new UpdateServiceData(new Practice()))->handle($practice, ['status' => 'finished']);
```
Domain statistics services (`Statistics/BattingStatisticsService`, `CageStatisticsService`, etc.) contain complex multi-model aggregation — query them for dashboard/reporting, don't reproduce logic in controllers.

### Models
- All PKs are **UUID strings** — every model has `HasUuid` trait, `public $incrementing = false`, `protected $keyType = 'string'`.
- `SoftDeletes` on all models; never hard-delete.
- Explicit `$fillable` — no `$guarded = []`.
- **PHP 8.1 backed enums** in `app/Models/Concerns/` for all domain constants (`PracticeTypes`, `PracticeModes`, `UserTypes`, `BattingTrajectory`, `CaptureZone`, etc.). Always reference these enums — never raw strings.
- `Practice` is the central session model with `hasMany` to all result types.
- `User` has `subscription_plan`; `User::PLAN_FEATURES` maps plan tiers to feature string arrays for capability gating.

### Form Requests — Required for All Inputs
Form Requests mirror controller folder structure in `app/Http/Requests/Api/`. All override `failedValidation()` to return the JSON envelope (code `001V`, HTTP 422) instead of redirecting. Always call `$request->validated()` — never `$request->all()`.

## Developer Workflows

| Command | Purpose |
|---------|---------|
| `make test` | Run test suite (`php artisan test`) |
| `make coverage` | Run with coverage report |
| `make analyse` | PHPStan / Larastan static analysis |
| `make pint` | Format code (PSR-12 via Laravel Pint) |
| `make migrate` / `make migrater` | Migrate / fresh migrate |
| `make up` / `make down` | Docker compose (MariaDB on port 4406) |
| `make updev` | `php artisan serve` + Vite dev server |

API docs auto-generated from Form Requests at `/request-docs` (no separate OpenAPI spec). Telescope + Clockwork available in dev for query inspection.

## Tests
- **Pest PHP** (v1) with `pest-plugin-laravel`. Structure mirrors `app/` under `tests/Feature/Api/` and `tests/Unit/Api/`.
- Tests run against a real **MariaDB** database (`fungo_test`) — not SQLite.
- Auth pattern: `Sanctum::actingAs($user)` before requests.
- HTTP assertions: `$this->json('POST', 'api/training', $data)->assertCreated()`.

## Conventions
- **`declare(strict_types=1)`** required on every PHP file.
- **Enums over strings** — use `PracticeTypes::BATTING->value`, not `'batting'`.
- **No repositories** — the generic `CreateServiceData`/`UpdateServiceData` services are the persistence layer.
- All new API routes go in `routes/api.php`; group under the correct prefix and `auth:sanctum` + `ability:coach|player` middleware as appropriate.
- Domain sport constants (velocity zones, launch angle buckets, etc.) live in `config/constants.php` — import from there, don't hardcode.

## Key Files
- `routes/api.php` — all API routes; the authoritative map of features
- `app/Models/Concerns/` — all domain enums
- `app/Services/` — generic CRUD + statistics services
- `app/Models/Practice.php` — central session model
- `app/Models/User.php` — auth, plan features
- `config/constants.php` — sport domain constants
