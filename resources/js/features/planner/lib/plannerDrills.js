// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner — drill / exercise library (Phase 1 seed)
//
// Single normalized shape so a planner item, the drill picker, and (later) the
// backend all speak the same language. The library is seeded from:
//   • a curated FMTRX starter set (throwing, arm care, strength, recovery…)
//   • the existing Cage drills (src/data/cageDrills.js) → Hitting bucket
//   • the existing Arm Care routines (src/data/armCareRoutines.js) → Arm Care
// Coaches can also add custom drills (persisted via plannerStore); those merge in.
//
// Drill shape:
//   { id, name, bucket, subcategory, description, equipment, videoUrl,
//     defaultSets, defaultReps, defaultDurationSec, defaultDistance,
//     defaultThrows, defaultIntensity, defaultIntent, workloadType,
//     bodyRegion, movementPattern, tags:[], coachCue, source }
// ─────────────────────────────────────────────────────────────────────────────

import { CAGE_DRILLS } from './cageDrills';
import { ARM_CARE_ROUTINES } from './armCareRoutines';
import { EXERCISE_LIBRARY } from './exerciseLibrary';
import { STRENGTH_LIBRARY } from './strengthLibrary';
import { initialSets, oneRMFieldForExercise } from './strengthLoad';

const d = (o) => ({
  subcategory: '',
  description: '',
  equipment: '',
  videoUrl: '',
  defaultSets: null,
  defaultReps: null,
  defaultDurationSec: null,
  defaultDistance: null,
  defaultThrows: null,
  defaultIntensity: 'Moderate',
  defaultIntent: null,
  workloadType: 'none',
  bodyRegion: '',
  movementPattern: '',
  categoryGroup: '',
  physicalQuality: '',
  physicalQualities: [],
  baseballCorrelation: '',
  baseballCorrelations: [],
  relatedMetrics: [],
  tags: [],
  coachCue: '',
  source: 'seed',
  ...o,
});

// ── Curated FMTRX starter set ────────────────────────────────────────────────
const STARTER = [
  // Movement Preparation
  d({ id: 'mp_jog', name: 'Light Jog + Dynamic Warm-up', bucket: 'movement_prep', defaultDurationSec: 300, defaultIntensity: 'Low', workloadType: 'time', tags: ['warmup'], coachCue: 'Raise the heart rate, loosen up.' }),
  d({ id: 'mp_leg_swings', name: 'Leg Swings (Front/Side)', bucket: 'movement_prep', defaultSets: 1, defaultReps: 10, defaultIntensity: 'Low', workloadType: 'reps', bodyRegion: 'Lower Body' }),
  d({ id: 'mp_hip_open', name: 'World\'s Greatest Stretch', bucket: 'movement_prep', defaultSets: 1, defaultReps: 5, defaultIntensity: 'Low', workloadType: 'reps', movementPattern: 'Mobility' }),
  d({ id: 'mp_band_walk', name: 'Lateral Band Walks', bucket: 'movement_prep', defaultSets: 2, defaultReps: 10, defaultIntensity: 'Low', workloadType: 'reps', bodyRegion: 'Glute', equipment: 'Mini band' }),

  // Throwing
  d({ id: 'th_wrist', name: 'Wrist Throws', bucket: 'throwing', defaultThrows: 10, defaultIntent: 40, defaultIntensity: 'Low', workloadType: 'throwing', tags: ['arm-care throw'], coachCue: 'Loose wrist, spin the ball.' }),
  d({ id: 'th_pivot', name: 'Pivot Picks', bucket: 'throwing', defaultThrows: 8, defaultIntent: 60, defaultIntensity: 'Moderate', workloadType: 'throwing' }),
  d({ id: 'th_rocker', name: 'Rocker Throws', bucket: 'throwing', defaultThrows: 8, defaultIntent: 70, defaultIntensity: 'Moderate', workloadType: 'throwing' }),
  d({ id: 'th_catch', name: 'Catch Play', bucket: 'throwing', defaultThrows: 25, defaultIntent: 60, defaultIntensity: 'Moderate', workloadType: 'throwing', tags: ['catch play'] }),
  d({ id: 'th_longtoss', name: 'Long Toss', bucket: 'throwing', defaultThrows: 25, defaultIntent: 80, defaultDistance: 180, defaultIntensity: 'High', workloadType: 'throwing', tags: ['long toss'], coachCue: 'Stretch it out, stay behind the ball.' }),
  d({ id: 'th_pulldown', name: 'Pulldowns', bucket: 'throwing', defaultThrows: 5, defaultIntent: 95, defaultIntensity: 'Maximum', workloadType: 'throwing', tags: ['pulldown', 'velocity'], coachCue: 'Full effort, full recovery between.' }),

  // Pitching development
  d({ id: 'pd_bullpen_cmd', name: 'Command Bullpen', bucket: 'pitching', defaultThrows: 25, defaultIntent: 80, defaultIntensity: 'High', workloadType: 'throwing', tags: ['bullpen', 'command'], coachCue: 'Compete to a target, hold your line.' }),
  d({ id: 'pd_bullpen_dev', name: 'Development Bullpen', bucket: 'pitching', defaultThrows: 20, defaultIntent: 70, defaultIntensity: 'Moderate', workloadType: 'throwing', tags: ['bullpen'] }),
  d({ id: 'pd_flat_ground', name: 'Flat Ground Pitch Design', bucket: 'pitching', defaultThrows: 15, defaultIntent: 70, defaultIntensity: 'Moderate', workloadType: 'throwing', tags: ['flat ground'] }),

  // Speed & agility
  d({ id: 'sa_10yd', name: '10-Yard Accelerations', bucket: 'speed_agility', defaultSets: 4, defaultReps: 1, defaultIntensity: 'High', workloadType: 'reps', tags: ['sprint'], coachCue: 'Explosive first 3 steps.' }),
  d({ id: 'sa_pro_agility', name: 'Pro Agility (5-10-5)', bucket: 'speed_agility', defaultSets: 4, defaultReps: 1, defaultIntensity: 'High', workloadType: 'reps', movementPattern: 'Lateral' }),

  // Strength — lower body / power / upper
  d({ id: 'st_hex_dl', name: 'Hex Bar Deadlift', bucket: 'strength', subcategory: 'Hinge', bodyRegion: 'Lower Body', defaultSets: 4, defaultReps: 4, defaultIntensity: 'High', workloadType: 'strength', tags: ['lower body'], coachCue: 'Drive the floor away, finish through the hips.' }),
  d({ id: 'st_back_squat', name: 'Back Squat', bucket: 'strength', subcategory: 'Squat', bodyRegion: 'Lower Body', defaultSets: 4, defaultReps: 5, defaultIntensity: 'High', workloadType: 'strength', tags: ['lower body'] }),
  d({ id: 'st_rdl', name: 'Romanian Deadlift', bucket: 'strength', subcategory: 'Hinge', bodyRegion: 'Hamstring', defaultSets: 3, defaultReps: 8, defaultIntensity: 'Moderate', workloadType: 'strength', tags: ['lower body'] }),
  d({ id: 'st_split_squat', name: 'Rear-Foot-Elevated Split Squat', bucket: 'strength', subcategory: 'Single-leg', bodyRegion: 'Lower Body', defaultSets: 3, defaultReps: 8, defaultIntensity: 'Moderate', workloadType: 'strength' }),
  d({ id: 'st_mb_rot', name: 'Rotational Med-Ball Throw', bucket: 'strength', subcategory: 'Rotational power', bodyRegion: 'Power', defaultSets: 3, defaultReps: 5, defaultIntensity: 'High', workloadType: 'strength', tags: ['rotational power', 'power'], equipment: 'Med ball', coachCue: 'Sequence hips → torso → arms.' }),
  d({ id: 'st_trap3', name: 'Trap-3 Raise', bucket: 'strength', subcategory: 'Scapular control', bodyRegion: 'Upper Body', defaultSets: 3, defaultReps: 12, defaultIntensity: 'Low', workloadType: 'strength', tags: ['shoulder health'] }),
  d({ id: 'st_row', name: '1-Arm DB Row', bucket: 'strength', subcategory: 'Horizontal pull', bodyRegion: 'Upper Body', defaultSets: 3, defaultReps: 10, defaultIntensity: 'Moderate', workloadType: 'strength' }),
  d({ id: 'st_chinup', name: 'Chin-Ups', bucket: 'strength', subcategory: 'Vertical pull', bodyRegion: 'Upper Body', defaultSets: 3, defaultReps: 8, defaultIntensity: 'Moderate', workloadType: 'strength' }),
  d({ id: 'st_carry', name: 'Farmer Carry', bucket: 'strength', subcategory: 'Carry', bodyRegion: 'Core', defaultSets: 3, defaultReps: 1, defaultDistance: 40, defaultIntensity: 'Moderate', workloadType: 'strength' }),
  d({ id: 'st_pallof', name: 'Pallof Press (Anti-Rotation)', bucket: 'strength', subcategory: 'Anti-rotation', bodyRegion: 'Core', defaultSets: 3, defaultReps: 10, defaultIntensity: 'Low', workloadType: 'strength' }),

  // Conditioning
  d({ id: 'cd_tempo', name: 'Tempo Runs', bucket: 'conditioning', defaultSets: 6, defaultReps: 1, defaultDistance: 100, defaultIntensity: 'Moderate', workloadType: 'reps' }),
  d({ id: 'cd_bike', name: 'Assault Bike Intervals', bucket: 'conditioning', defaultSets: 8, defaultDurationSec: 20, defaultIntensity: 'High', workloadType: 'time', equipment: 'Air bike' }),

  // Recovery
  d({ id: 'rc_foam', name: 'Foam Roll — Full Body', bucket: 'recovery', defaultDurationSec: 300, defaultIntensity: 'Recovery', workloadType: 'time', coachCue: 'Slow, breathe, spend time on tight spots.' }),
  d({ id: 'rc_breath', name: 'Box Breathing', bucket: 'recovery', defaultSets: 1, defaultReps: 10, defaultIntensity: 'Recovery', workloadType: 'reps' }),
  d({ id: 'rc_mobility', name: 'Post-Throw Mobility Flow', bucket: 'recovery', defaultDurationSec: 300, defaultIntensity: 'Recovery', workloadType: 'time', tags: ['shoulder health'] }),

  // Education
  d({ id: 'ed_film', name: 'Film Review', bucket: 'education', defaultDurationSec: 600, defaultIntensity: 'Recovery', workloadType: 'time' }),
  d({ id: 'ed_mental', name: 'Mental Skills / Breathwork Reading', bucket: 'education', defaultDurationSec: 600, defaultIntensity: 'Recovery', workloadType: 'time' }),
];

// ── Fold in existing Cage drills → Hitting bucket ─────────────────────────────
const CAGE = (CAGE_DRILLS || []).map((c) =>
  d({
    id: `cage_${c.id}`,
    name: c.name,
    bucket: 'hitting',
    subcategory: c.skill || 'Cage',
    description: c.objective || '',
    equipment: c.equipment || '',
    defaultDurationSec: c.suggestedMinutes ? c.suggestedMinutes * 60 : null,
    defaultIntensity: 'Moderate',
    workloadType: 'time',
    coachCue: c.coachingCues || '',
    tags: ['hitter'],
    source: 'cage',
  }),
);

// ── Fold in existing Arm Care exercises → Arm Care bucket ─────────────────────
const ARM = [];
const seenArm = new Set();
(ARM_CARE_ROUTINES || []).forEach((routine) => {
  (routine.phases || []).forEach((phase) => {
    (phase.exercises || []).forEach((ex) => {
      if (!ex?.id || seenArm.has(ex.id)) return;
      seenArm.add(ex.id);
      ARM.push(d({
        id: `arm_${ex.id}`,
        name: ex.name,
        bucket: 'arm_care',
        subcategory: phase.name || 'Arm Care',
        description: ex.prescription || '',
        defaultIntensity: 'Low',
        workloadType: 'reps',
        coachCue: ex.cue || '',
        tags: ['shoulder health'],
        source: 'armcare',
      }));
    });
  });
});

const normalizedKey = (drill) =>
  `${drill.bucket || ''}:${String(drill.name || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, ' ')}`;

const mergeList = (a = [], b = []) => [...new Set([...(a || []), ...(b || [])].filter(Boolean))];

const mergeDrill = (existing, incoming) => ({
  ...incoming,
  ...existing,
  equipment: existing.equipment || incoming.equipment || '',
  subcategory: existing.subcategory || incoming.subcategory || '',
  bodyRegion: existing.bodyRegion || incoming.bodyRegion || '',
  movementPattern: existing.movementPattern || incoming.movementPattern || '',
  categoryGroup: existing.categoryGroup || incoming.categoryGroup || '',
  physicalQuality: existing.physicalQuality || incoming.physicalQuality || '',
  baseballCorrelation: existing.baseballCorrelation || incoming.baseballCorrelation || '',
  physicalQualities: mergeList(existing.physicalQualities, incoming.physicalQualities),
  baseballCorrelations: mergeList(existing.baseballCorrelations, incoming.baseballCorrelations),
  relatedMetrics: mergeList(existing.relatedMetrics, incoming.relatedMetrics),
  tags: mergeList(existing.tags, incoming.tags),
});

const dedupeDrills = (items = []) => {
  const byKey = new Map();

  items.forEach((item) => {
    const key = normalizedKey(item);
    if (!key.trim()) return;
    byKey.set(key, byKey.has(key) ? mergeDrill(byKey.get(key), item) : item);
  });

  return [...byKey.values()];
};

// Strength now lives in three role buckets (strength_primary/secondary/accessory)
// sourced from the classified STRENGTH_LIBRARY, so the old single-bucket
// 'strength' entries (from STARTER + EXERCISE_LIBRARY) are dropped in favor of it.
const notLegacyStrength = (dr) => dr.bucket !== 'strength';
export const SEED_DRILLS = dedupeDrills([
  ...STRENGTH_LIBRARY,
  ...STARTER.filter(notLegacyStrength),
  ...EXERCISE_LIBRARY.filter(notLegacyStrength),
  ...CAGE,
  ...ARM,
]);

export function getDrillsForBucket(bucketType, extraDrills = []) {
  const all = [...SEED_DRILLS, ...(extraDrills || [])];
  return all.filter((x) => x.bucket === bucketType);
}

// The "type of lift" label used for the picker's quick-filter buttons.
export const drillCategory = (drill) =>
  String(drill?.categoryGroup || drill?.subcategory || '').trim();

// Distinct categories (with counts) for a bucket, so the picker can show
// tap-to-filter buttons instead of one long scroll.
export function getCategoriesForBucket(bucketType, extraDrills = []) {
  const counts = new Map();
  getDrillsForBucket(bucketType, extraDrills).forEach((x) => {
    const label = drillCategory(x);
    if (!label) return;
    counts.set(label, (counts.get(label) || 0) + 1);
  });
  return [...counts.entries()]
    .map(([label, count]) => ({ label, count }))
    .sort((a, b) => a.label.localeCompare(b.label));
}

export function searchDrills(query, bucketType, extraDrills = []) {
  const all = bucketType
    ? getDrillsForBucket(bucketType, extraDrills)
    : [...SEED_DRILLS, ...(extraDrills || [])];
  const q = String(query || '').trim().toLowerCase();
  if (!q) return all;
  return all.filter((x) =>
    `${x.name} ${x.subcategory} ${x.categoryGroup} ${x.physicalQuality} ${x.baseballCorrelation} ${(x.physicalQualities || []).join(' ')} ${(x.baseballCorrelations || []).join(' ')} ${(x.tags || []).join(' ')}`.toLowerCase().includes(q),
  );
}

// Build a fresh plan-item from a library drill (carrying its default prescription).
export function itemFromDrill(drill) {
  const isStrength = (drill.workloadType || 'none') === 'strength';
  const presc = drill.defaultPrescriptionType || 'percent_1rm'; // role-based default
  return {
    id: `item_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
    drillId: drill.id,
    name: drill.name,
    instructions: drill.description || '',
    coachCue: drill.coachCue || '',
    equipment: drill.equipment || '',
    videoUrl: drill.videoUrl || '',
    // Strength items build sets individually (each set can use its own
    // prescription method); other items keep the simple numeric sets/reps.
    defaultPrescriptionType: isStrength ? presc : null,
    oneRMField: isStrength ? oneRMFieldForExercise(drill) : null,
    setList: isStrength ? initialSets(drill.defaultSets, drill.defaultReps, presc) : null,
    sets: drill.defaultSets,
    reps: drill.defaultReps,
    durationSec: drill.defaultDurationSec,
    distance: drill.defaultDistance,
    throws: drill.defaultThrows,
    weight: null,
    intensity: drill.defaultIntensity || 'Moderate',
    intent: drill.defaultIntent,
    rest: null,
    required: true,
    workloadType: drill.workloadType || 'none',
    bucket: drill.bucket || '',
    subcategory: drill.subcategory || '',
    bodyRegion: drill.bodyRegion || '',
    movementPattern: drill.movementPattern || '',
    categoryGroup: drill.categoryGroup || '',
    physicalQuality: drill.physicalQuality || '',
    physicalQualities: drill.physicalQualities || [],
    baseballCorrelation: drill.baseballCorrelation || '',
    baseballCorrelations: drill.baseballCorrelations || [],
    relatedMetrics: drill.relatedMetrics || [],
    tags: drill.tags || [],
    source: drill.source || 'seed',
  };
}
