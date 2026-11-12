# Public landing redesign

The public `/` page remains `resources/js/pages/Welcome.vue` in the existing Laravel / Vue 3 / Vite stack. No authentication, registration, dashboard, API or backend routes were changed. No dependencies were added. No production deployment was performed.

## Changes

- Scoped navy/red/cream responsive landing page, official existing logo, role-specific login and registration links.
- Hero, session strip, keyboard-operated report tabs, coach/player sections, three-step process, FAQ and closing CTA.
- Reusable platform capture and native dialog with Escape, focus restoration and scroll locking.
- Lazy loading below the fold, explicit image dimensions, reduced-motion styles and visible focus.

## Product evidence

- Login/register destinations: `resources/router/index.js` (`/login/coach`, `/login/player`, `/register/coach`, `/register/player`).
- Session reports and weighted-ball curves: `resources/js/components/statistics/training/TrainingSessionStatsTabs.vue`.
- Personal/team history: `resources/js/features/player-home/pages/PlayerHomeDashboard.vue` and its statistics components.
- Planning: `resources/js/pages/practice/PracticePlanner.vue` and `resources/js/pages/workouts/WorkoutPlanner.vue`.
- Copy distinguishes mobile session capture from web review and management, without implying exclusive platform capabilities or automatic radar measurement.

## Missing approved assets

The approved reference at https://fmtrx-home-redesign.electbison4.chatgpt.site returned HTTP 401. It could not be inspected or used to export its screenshots. No suitable anonymized platform captures were available in the repository. Layout follows the supplied written design brief; exact reference comparison remains pending.

See `resources/js/assets/img/landing/README.md` for the three required WebP files. The page intentionally shows clearly labeled placeholders until supplied. No dashboard statistics or platform images were fabricated. Final screenshot optimization, privacy review and image-composition QA must be repeated with the supplied captures.

## Verification

- Node 24 production build passed. Existing >500 KB chunk warning remains (ApexCharts).
- Existing frontend suite: 88 files / 561 tests passed.
- Compiled frontend preview checked in isolated headless Chrome at 1440, 768, 390 and 320 px widths: no horizontal overflow; tabs, keyboard arrows/Home, FAQ and both login routes passed; no page JavaScript errors.
- Full-page desktop/mobile screenshots visually inspected.
- Native modal tested separately at desktop and mobile widths using the official logo as a temporary test fixture, not a production screenshot: open, focus containment, Escape, restored trigger focus, restored scrolling and close button passed.
- Frontend checks do not constitute authenticated backend/end-to-end verification. Existing auth behavior was preserved.

## Weighted-ball asset received

The user supplied `weighted balls new screen.mov`. A frame at 39 seconds supplies the weighted-ball hero inset and tab report (990 × 574 WebP). The crop excludes the player selector and account navigation, preserving the original chart and metrics. Hitting and bullpen remain pending as importable files.
