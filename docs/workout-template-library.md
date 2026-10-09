# Workout library implementation

## Existing infrastructure reused

- `DailyPlan`, `DailyPlanAssignment`, `DailyPlanProgress`: scheduled workouts, athlete assignment, actuals, completion and coach feedback. Published copies are visible through existing player daily-plan APIs and web/native workout screens.
- `PlayerGroup`, `CoachTeam`, `PlayerTeam`: team-scoped group and athlete selection.
- `PlannerCustomDrill`, planner bucket and arm-care libraries: reusable exercises and compatible daily-plan sections.
- `Practice`, `PracticeLineUp`, `WeightBallPractice`, bullpen results: canonical observed performance. Session links reference these records rather than copying bullpen pitches.
- Daily Planner: date, editing, assignments, drafts/publish, calendar and dashboard shortcuts.
- App `features/programs` is an in-memory test workflow, not persistent shared storage. The server program schedule below publishes into existing daily plans, which the app already loads.

## Storage decisions

Teams are the existing organization boundary. Nullable `organization_id` stores a team ID; global premades have no organization. New templates, ordered sections and exercises use UUIDs. Program days contain versioned template snapshots in MariaDB-compatible longText, not native JSON. Individual schedule entries have their own athlete selection. Published entries create stable DailyPlan IDs inside one transaction. Repeated publication returns the same assignments. No automatic throwing progression or workload escalation.

Template copy data remains in daily-plan buckets, including original prescriptions and template ID/version. Completion remains separate from optional actual measurements. Existing benchmark task behavior is preserved; ordinary exercise completion never becomes a benchmark.

No production migrations, deployment, remote push, subscription changes or native release submission are authorized.

## Review workflow and screens

1. Coach: Daily Planner → **+ Add Workout**, or `/workout-library`.
2. Library: search/filter, preview each full prescription, **Duplicate & Customize** to save a private reusable copy. Global premades cannot be edited by a coach.
3. **Use Template / Assign to Players**: choose date and athletes/groups/team; creates a draft snapshot and opens the existing Daily Planner editor. Adjust that day's prescription, save and publish there. Multiple plans/components can share a date.
4. Library → **Program Builder**: start an empty FlameBangers program with the five selectable blocks, choose 4/8/10/12 or another length up to 52 weeks, add days, adjust athlete lists, move/swap workouts, copy days/weeks, or repeat week 1. Calendar columns are Monday–Sunday, including partial boundary weeks. Phase labels are editable. Coach review is required for publication. Published programs can be copied for changes.
5. Player web: existing Player Workouts dashboard. Native: existing Player Workouts screen. Open assigned plans, read prescriptions, view available media, complete items/sections, add optional actual sets/reps/distance/RPE/notes, radar measurements, or link an existing bullpen session. Submit to mark the workout complete.
6. Coach: Daily Planner → View Players → player review shows prescribed/actual results, notes and existing session report links. Player completed-workout history includes observed actuals.

## Database setup

Migration: `database/migrations/2026_10_09_160000_create_workout_template_library.php`.

Five new tables: `workout_templates`, `workout_template_sections`, `workout_template_exercises`, `workout_programs`, `workout_session_links`. Uses longText for array payloads and bounded composite indexes for MariaDB 10.1 compatibility. Existing daily plans/assignments/progress and canonical performance tables remain authoritative.

For a confirmed local/staging database after reviewing its connection:

```sh
php artisan migrate
php artisan db:seed --class=FlameBangersWorkoutTemplateSeeder
```

The seeder uses stable slugs and reusable exercise IDs. Re-running skips existing masters and does not overwrite them or duplicate the 94 exercise entries. It preserves all written prescriptions, including repeated ball weights. The migration and seeder were executed only against the isolated `fungo_test` database during automated tests in this implementation session. Production database setup remains a deployment step.

## Routes and API

All API paths below use `/api/` and existing Sanctum/coach/player permission middleware.

| Method | Path | Purpose |
| --- | --- | --- |
| GET, POST | `coach/workout-templates` | List visible templates / save private custom template |
| GET, PUT | `coach/workout-templates/{id}` | Preview / update owned custom copy with version check |
| POST | `coach/workout-templates/{id}/duplicate` | Save editable private copy |
| POST | `coach/workout-templates/{id}/use` | Idempotent client-UUID draft daily-plan assignment |
| GET, POST | `coach/workout-programs` | List team programs / save versioned draft |
| POST | `coach/workout-programs/{id}/publish` | Require workload approval; create published snapshots once |
| GET | `player/daily-plans/{id}/sessions` | List same-team sessions belonging to or including the assigned player |
| POST (extended) | `player/daily-plans/{id}/progress` | Save optional actuals and completion in a transaction |
| Existing | `coach/daily-plans`, player daily-plan APIs and coach progress/review APIs | Planner editing, assignments, player dashboard, feedback |

Web route: `/workout-library`, name `workout.library`, coach-only with existing `planner_create` entitlement. No subscription configuration changes.

## Integrity and compatibility

- Master edits cannot rewrite assigned daily-plan snapshots. After any player progress exists, edits to the assigned template workout are rejected; duplicate it to change future work.
- Template/day versions, program IDs, schedule date, coach/team/player IDs, prescribed values, actual values and completion timestamps remain separate.
- Radar results use client UUIDs and canonical `weight_ball_practices`; retries do not create another throw. Canonical weight/velocity columns are integers, so input uses whole ounces/mph. There is no training velocity ceiling. Corrections to accepted throws go through the existing session editor.
- A linked bullpen uses its existing practice and pitches. Linking or completing a workout does not copy pitches or finish someone else's session. No completion-only action creates a performance session or assessment measurement.
- J-Band media reuses the existing supplied guide. Other exercises show a placeholder until an accurate demo URL is configured. Custom templates support image/video URLs for future uploaded media; no new media-upload service is introduced.
- App pending edits survive an online list refresh. Failed completion submission stays unsent and can be retried. Workout cache/progress is scoped to the active login; legacy unscoped progress is not imported because its owner cannot be established. Previously submitted progress reloads from the server.
- The app's older local test Program Builder remains separate; production server programs reach the app through published daily plans.

## Verification

- Backend: `php artisan test --filter='WorkoutLibraryTest|GetWeightBallPracticeResultsTest|GetBullpenStatisticsByPracticeTest'` — 20 passed, including seeding/reseeding, copied-template edits and historical snapshots, groups/team boundaries, per-athlete schedules, version checks, retries, canonical radar results, bullpen linkage and completion-only behavior.
- Web: `npm test -- tests/frontend/workoutProgramSchedule.spec.js tests/frontend/plannerCalendar.spec.js tests/frontend/plannerLinks.spec.js tests/frontend/practicePlanner.spec.js` — 17 passed.
- Native: `npx jest src/utils/planner/__tests__/workoutTemplateCache.test.js src/services/__tests__/workoutTemplateSync.test.js --runInBand` — 5 passed.
- Web production bundle builds successfully (existing large-chunk advisory).
- Native changed-file ESLint check passes. iOS Metro JavaScript bundle succeeds into `/tmp`; this is not an Xcode/device build or App Store submission.

## Remaining release verification

Apply migration/seeder to the intended development/staging environment before reviewing populated screens. Authenticated browser and physical-device visual QA, and MariaDB 10.1 execution itself, have not been performed here (tests use the isolated local test database). No production deployment, migration, release build, subscription change, credential change or remote push was performed.

## File manifest

### Web/backend created

- `app/Http/Controllers/Api/Workouts/WorkoutProgramController.php`
- `app/Http/Controllers/Api/Workouts/WorkoutSessionController.php`
- `app/Http/Controllers/Api/Workouts/WorkoutTemplateController.php`
- `app/Models/WorkoutProgram.php`
- `app/Models/WorkoutTemplate.php`
- `app/Models/WorkoutTemplateExercise.php`
- `app/Models/WorkoutTemplateSection.php`
- `app/Services/Workouts/WorkoutPerformanceService.php`
- `app/Services/Workouts/WorkoutTemplateService.php`
- `database/data/flamebangers-workouts.json`
- `database/migrations/2026_10_09_160000_create_workout_template_library.php`
- `database/seeders/FlameBangersWorkoutTemplateSeeder.php`
- `docs/workout-template-library.md`
- `public/images/training/j-band-exercise-sheet.jpg`
- `resources/js/components/workouts/ProgramBuilder.vue`
- `resources/js/components/workouts/TemplateEditor.vue`
- `resources/js/components/workouts/TemplateExerciseActuals.vue`
- `resources/js/features/workouts/programSchedule.js`
- `resources/js/pages/workouts/WorkoutLibrary.vue`
- `tests/Feature/Workouts/WorkoutLibraryTest.php`
- `tests/frontend/workoutProgramSchedule.spec.js`

### Web/backend modified

- `app/Http/Controllers/Api/Planner/GetCustomDrills.php`
- `app/Http/Controllers/Api/Planner/SaveDailyPlan.php`
- `app/Http/Controllers/Api/Planner/SaveWorkoutProgress.php`
- `resources/js/components/planner/CoachWorkoutPlayers.vue`
- `resources/js/components/planner/PlayerWorkoutsPanel.vue`
- `resources/js/features/planner/lib/plannerBuckets.js`
- `resources/js/pages/practice/DailyPlanner.vue`
- `resources/router/index.js`
- `routes/api.php`

### Native created

- `src/screens/Planner/TemplateExerciseActuals.js`
- `src/services/__tests__/workoutTemplateSync.test.js`
- `src/utils/planner/__tests__/workoutTemplateCache.test.js`

### Native modified

- `src/data/plannerBuckets.js`
- `src/screens/Planner/PlayerCompletedWorkoutScreen.js`
- `src/screens/Planner/PlayerPlannerScreen.js`
- `src/screens/Planner/PlayerWorkoutScreen.js`
- `src/services/plannerApi.js`
- `src/utils/planner/plannerStore.js`

