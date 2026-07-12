// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner — bucket catalog (Phase 1)
//
// A "bucket" is a section of a player's day. A coach builds the day by adding
// drills / exercises / tasks into the buckets they choose; only selected buckets
// appear on the player's plan.
//
// `kind` drives how the bucket behaves in the builder:
//   'content'  → holds a list of drill/exercise items (coach adds them)
//   'note'     → a single free-text field (e.g. Coach Notes)
//   'survey'   → completed by the PLAYER (Readiness / Reflection) — no coach content
//
// `throwing` marks buckets whose items use throw counts + intent % (for the
// Phase-3 throwing-load engine); `strength` marks lifting buckets.
// ─────────────────────────────────────────────────────────────────────────────

export const BUCKETS = [
  { type: 'daily_readiness', title: 'Daily Readiness', kind: 'survey', icon: 'heart-pulse', color: '#22c55e', hint: 'Player completes before starting' },
  { type: 'movement_prep', title: 'Movement Preparation', kind: 'content', icon: 'run', color: '#38bdf8', hint: 'Warm-up & mobility' },
  { type: 'arm_care', title: 'Arm Care', kind: 'content', icon: 'arm-flex', color: '#f59e0b', hint: 'Cuff, scap, forearm' },
  { type: 'throwing', title: 'Throwing', kind: 'content', icon: 'baseball', color: '#ef4444', throwing: true, hint: 'Catch play → long toss → pulldowns' },
  { type: 'pitching', title: 'Pitching Development', kind: 'content', icon: 'baseball-bat', color: '#e11d48', throwing: true, hint: 'Bullpen / command work' },
  { type: 'hitting', title: 'Hitting', kind: 'content', icon: 'bat', color: '#2160C4', hint: 'Cage / tee / live' },
  { type: 'speed_agility', title: 'Speed and Agility', kind: 'content', icon: 'lightning-bolt', color: '#a78bfa', hint: 'Sprints, change of direction' },
  { type: 'strength_primary', title: 'Primary Strength', kind: 'content', icon: 'weight-lifter', color: '#f97316', strength: true, role: 'primary', hint: 'Main lift — 1 movement, heavy (3–6 reps)' },
  { type: 'strength_secondary', title: 'Secondary Strength', kind: 'content', icon: 'dumbbell', color: '#fb923c', strength: true, role: 'secondary', hint: '2–4 movements, moderate (5–10 reps)' },
  { type: 'strength_accessory', title: 'Accessory Strength', kind: 'content', icon: 'arm-flex', color: '#fbbf24', strength: true, role: 'accessory', hint: '3–6 movements, isolation (8–20 reps)' },
  { type: 'conditioning', title: 'Conditioning', kind: 'content', icon: 'heart', color: '#14b8a6', hint: 'Energy-system work' },
  { type: 'recovery', title: 'Recovery', kind: 'content', icon: 'sleep', color: '#38bdf8', hint: 'Soft tissue, mobility, breathing' },
  { type: 'education', title: 'Education', kind: 'content', icon: 'book-open-variant', color: '#94a3b8', hint: 'Video / reading / mental skills' },
  { type: 'coach_notes', title: 'Coach Notes', kind: 'note', icon: 'clipboard-text', color: '#64748b', hint: 'Message to the player' },
  { type: 'player_reflection', title: 'Player Reflection', kind: 'survey', icon: 'comment-quote', color: '#8b5cf6', hint: 'Player completes after the session' },
];

export const BUCKET_BY_TYPE = BUCKETS.reduce((acc, b) => { acc[b.type] = b; return acc; }, {});

// Intensity selector — label → 1..5 value (kept simple for coaches).
export const INTENSITY_LEVELS = [
  { label: 'Recovery', value: 1, color: '#38bdf8' },
  { label: 'Low', value: 2, color: '#22c55e' },
  { label: 'Moderate', value: 3, color: '#f59e0b' },
  { label: 'High', value: 4, color: '#f97316' },
  { label: 'Maximum', value: 5, color: '#ef4444' },
];

// Throwing intent percentages (throwing buckets only).
export const THROW_INTENTS = [40, 50, 60, 70, 80, 90, 95, 100];

// Development phases a plan can be assigned to.
export const PHASES = [
  'Assessment', 'Foundation', 'Strength Development', 'Power Development',
  'Velocity Development', 'Preseason', 'In-Season', 'Recovery', 'Return to Throw', 'Custom',
];

// Basic (Phase-1) workload labels — coach picks or the app estimates from items.
export const WORKLOAD_LEVELS = [
  { label: 'Recovery', color: '#38bdf8' },
  { label: 'Low', color: '#22c55e' },
  { label: 'Moderate', color: '#f59e0b' },
  { label: 'High', color: '#f97316' },
  { label: 'Very High', color: '#ef4444' },
];

export const PRIMARY_GOALS = [
  'Velocity development', 'Command development', 'Strength', 'Power', 'Recovery',
  'Bat speed', 'Contact quality', 'Return to throw', 'General development',
];

// Survey buckets are completed by the PLAYER, not built by the coach. These are
// the exact prompts the player answers — shown in the builder so the coach knows
// what's in each survey.
export const SURVEY_FIELDS = {
  daily_readiness: [
    { key: 'sleep_hours', label: 'Sleep hours', scale: 'hrs' },
    { key: 'sleep_quality', label: 'Sleep quality', scale: '1–5' },
    { key: 'energy', label: 'Energy', scale: '1–5' },
    { key: 'overall_soreness', label: 'Overall soreness', scale: '1–5' },
    { key: 'arm_soreness', label: 'Arm soreness', scale: '0–10' },
    { key: 'shoulder_soreness', label: 'Shoulder soreness', scale: '0–10' },
    { key: 'elbow_soreness', label: 'Elbow soreness', scale: '0–10' },
    { key: 'lower_body_soreness', label: 'Lower-body soreness', scale: '0–10' },
    { key: 'stress', label: 'Stress level', scale: '1–5' },
    { key: 'motivation', label: 'Motivation', scale: '1–5' },
    { key: 'pain_flag', label: 'Any pain today?', scale: 'Yes / No' },
    { key: 'notes', label: 'Optional comments', scale: 'Text' },
  ],
  player_reflection: [
    { key: 'workout_rating', label: 'Rate today’s workout', scale: '1–5' },
    { key: 'session_rpe', label: 'Session RPE', scale: '1–10' },
    { key: 'arm_feel', label: 'Arm feel', scale: '1–5' },
    { key: 'body_feel', label: 'Body feel', scale: '1–5' },
    { key: 'energy_after', label: 'Energy after session', scale: '1–5' },
    { key: 'completed_all', label: 'Did you complete everything?', scale: 'Yes / No' },
    { key: 'felt_best', label: 'What felt best?', scale: 'Text' },
    { key: 'felt_difficult', label: 'What felt difficult?', scale: 'Text' },
    { key: 'pain_after', label: 'Pain after training', scale: '0–10' },
    { key: 'comments', label: 'Player comments', scale: 'Text' },
  ],
};

// The 0–100 readiness score weighting (shown to the coach for context).
export const READINESS_WEIGHTS = [
  { part: 'Sleep', weight: 25 },
  { part: 'Energy', weight: 20 },
  { part: 'Soreness', weight: 20 },
  { part: 'Arm health', weight: 15 },
  { part: 'Stress', weight: 10 },
  { part: 'Motivation', weight: 10 },
];
