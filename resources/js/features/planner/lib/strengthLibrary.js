// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Strength Library — baseball-focused, classified by ROLE.
//
// Strength is split into three planner buckets so a coach builds a session the
// way it's actually programmed: ~1 Primary lift, 2–4 Secondary lifts, 3–6
// Accessory movements.
//   role      → primary | secondary | accessory  (→ strength_primary/…)
//   pattern   → Squat / Hinge / Push / Pull / Carry / Lateral / Sled / Olympic
//               (accessory uses body-area groups: Glutes, Hamstrings, …). This
//               becomes the drill-picker's category dropdown inside each bucket.
//   direction → Bilateral / Unilateral / Horizontal / Vertical / Lateral / Rotational
//   quality   → Max Strength / Power / Hypertrophy / Stability / Arm Care …
//
// An exercise can appear in more than one role (e.g. RFE Split Squat is a
// primary lift some days, a secondary lift others) — it's simply listed under
// each role it fits. Default sets/reps/prescription follow the role.
// ─────────────────────────────────────────────────────────────────────────────

const slug = (v) => String(v || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

const ROLE_DEFAULTS = {
  primary: { bucket: 'strength_primary', sets: 4, reps: 4, intensity: 'High', prescription: 'percent_1rm' },
  secondary: { bucket: 'strength_secondary', sets: 3, reps: 8, intensity: 'Moderate', prescription: 'fixed_weight' },
  accessory: { bucket: 'strength_accessory', sets: 3, reps: 12, intensity: 'Low', prescription: 'athlete_weight' },
};

// g(role, pattern, direction, equipment, quality, tags, [names]) → drill[]
const g = (role, pattern, direction, equipment, quality, tags, names) => {
  const rd = ROLE_DEFAULTS[role];
  return names.map((name) => ({
    id: `sl_${role}_${slug(pattern)}_${slug(name)}`,
    name,
    bucket: rd.bucket,
    roles: [role],
    categoryGroup: pattern,
    subcategory: pattern,
    movementPattern: pattern,
    direction,
    equipment,
    physicalQuality: quality,
    physicalQualities: [quality],
    tags,
    defaultSets: rd.sets,
    defaultReps: rd.reps,
    defaultIntensity: rd.intensity,
    defaultPrescriptionType: rd.prescription,
    workloadType: 'strength',
    source: 'strength_lib',
  }));
};

const PB = ['position-player friendly', 'pitcher-friendly']; // broadly usable

export const STRENGTH_LIBRARY = [
  // ── PRIMARY ────────────────────────────────────────────────────────────────
  ...g('primary', 'Squat', 'Bilateral', 'Barbell', 'Max Strength', PB,
    ['Back Squat', 'High-Bar Back Squat', 'Low-Bar Back Squat', 'Front Squat', 'Safety-Bar Squat', 'Box Squat', 'Zercher Squat', 'Pause Squat', 'Tempo Squat', 'Pin Squat', 'Anderson Squat']),
  ...g('primary', 'Squat', 'Bilateral', 'Specialty', 'Max Strength', PB, ['Belt Squat', 'Hatfield Squat', 'Landmine Squat']),
  ...g('primary', 'Squat', 'Unilateral', 'Barbell', 'Max Strength', PB,
    ['Barbell Split Squat', 'Rear-Foot-Elevated Split Squat', 'Bulgarian Split Squat', 'Front-Foot-Elevated Split Squat', 'Walking Lunge', 'Reverse Lunge', 'High Step-Up', 'Pistol Squat']),
  ...g('primary', 'Hinge', 'Bilateral', 'Barbell', 'Max Strength', PB,
    ['Conventional Deadlift', 'Sumo Deadlift', 'Trap-Bar Deadlift', 'High-Handle Trap-Bar Deadlift', 'Block Pull', 'Rack Pull', 'Deficit Deadlift', 'Snatch-Grip Deadlift', 'Barbell Romanian Deadlift', 'Stiff-Leg Deadlift', 'Good Morning', 'Barbell Hip Thrust']),
  ...g('primary', 'Hinge', 'Unilateral', 'Barbell', 'Max Strength', PB, ['Single-Leg Romanian Deadlift', 'B-Stance Romanian Deadlift', 'Single-Leg Hip Thrust']),
  ...g('primary', 'Pull', 'Vertical', 'Bodyweight', 'Max Strength', PB, ['Weighted Pull-Up', 'Weighted Chin-Up', 'Neutral-Grip Pull-Up', 'Sternum Pull-Up']),
  ...g('primary', 'Pull', 'Horizontal', 'Barbell', 'Max Strength', PB, ['Barbell Row', 'Pendlay Row', 'T-Bar Row', 'Seal Row', 'Chest-Supported Row', 'Meadows Row', 'Heavy One-Arm Dumbbell Row']),
  ...g('primary', 'Push', 'Horizontal', 'Barbell', 'Max Strength', PB, ['Barbell Bench Press', 'Incline Barbell Bench Press', 'Close-Grip Bench Press', 'Floor Press', 'Swiss-Bar Bench Press', 'Football-Bar Bench Press']),
  ...g('primary', 'Push', 'Vertical', 'Mixed', 'Max Strength', ['pitcher-friendly'], ['Push Press', 'Standing Overhead Press', 'Half-Kneeling Landmine Press', 'One-Arm Dumbbell Overhead Press', 'Neutral-Grip Overhead Press']),
  ...g('primary', 'Olympic', 'Bilateral', 'Barbell', 'Power', PB, ['Hang Power Clean', 'Power Clean', 'High-Hang Clean', 'Clean Pull', 'Mid-Thigh Pull', 'Jump Shrug', 'Snatch-Grip High Pull', 'Hang Power Snatch']),
  ...g('primary', 'Olympic', 'Bilateral', 'Mixed', 'Power', PB, ['Trap-Bar Jump', 'Dumbbell Snatch', 'Kettlebell Swing', 'Landmine Clean']),

  // ── SECONDARY ──────────────────────────────────────────────────────────────
  ...g('secondary', 'Squat', 'Bilateral', 'Dumbbell/KB', 'Hypertrophy', PB, ['Goblet Squat', 'Heel-Elevated Goblet Squat', 'Tempo Goblet Squat', 'Pause Goblet Squat', 'Double-Kettlebell Front Squat', 'Cyclist Squat']),
  ...g('secondary', 'Squat', 'Unilateral', 'Dumbbell/KB', 'Hypertrophy', PB,
    ['Rear-Foot-Elevated Split Squat', 'Front-Foot-Elevated Split Squat', 'Goblet Split Squat', 'Dumbbell Split Squat', 'Reverse Lunge', 'Walking Lunge', 'Deficit Reverse Lunge', 'Step-Up', 'Skater Squat', 'Assisted Pistol Squat', 'Single-Leg Box Squat']),
  ...g('secondary', 'Hinge', 'Bilateral', 'Dumbbell/KB', 'Hypertrophy', PB, ['Dumbbell Romanian Deadlift', 'Kettlebell Romanian Deadlift', 'Cable Pull-Through', 'Good Morning', 'Hip Thrust', 'Glute Bridge', 'Back Extension', 'Reverse Hyperextension', 'Kettlebell Swing']),
  ...g('secondary', 'Hinge', 'Unilateral', 'Dumbbell/KB', 'Hypertrophy', PB, ['Single-Leg Romanian Deadlift', 'Kickstand Romanian Deadlift', 'B-Stance Romanian Deadlift', 'Landmine Romanian Deadlift', 'Single-Leg Hip Thrust', 'Nordic Hamstring Curl', 'Stability-Ball Leg Curl']),
  ...g('secondary', 'Pull', 'Horizontal', 'Dumbbell/Cable', 'Hypertrophy', PB, ['One-Arm Dumbbell Row', 'Chest-Supported Dumbbell Row', 'Seated Cable Row', 'Half-Kneeling Cable Row', 'Split-Stance Cable Row', 'One-Arm Landmine Row', 'Inverted Row', 'Ring Row', 'Renegade Row', 'Machine Row', 'Batwing Row']),
  ...g('secondary', 'Pull', 'Vertical', 'Cable/Bodyweight', 'Hypertrophy', PB, ['Pull-Up', 'Chin-Up', 'Neutral-Grip Pull-Up', 'Eccentric Pull-Up', 'Lat Pulldown', 'Neutral-Grip Pulldown', 'Half-Kneeling One-Arm Pulldown', 'Straight-Arm Pulldown']),
  ...g('secondary', 'Push', 'Horizontal', 'Dumbbell/Cable', 'Hypertrophy', PB, ['Dumbbell Bench Press', 'Neutral-Grip Dumbbell Bench Press', 'Incline Dumbbell Press', 'Dumbbell Floor Press', 'One-Arm Floor Press', 'Weighted Push-Up', 'Ring Push-Up', 'Standing One-Arm Cable Press', 'Swiss-Bar Bench Press']),
  ...g('secondary', 'Push', 'Vertical', 'Mixed', 'Hypertrophy', ['pitcher-friendly'], ['Half-Kneeling Landmine Press', 'Standing Landmine Press', 'Rotational Landmine Press', 'One-Arm Dumbbell Press', 'Bottoms-Up Kettlebell Press', 'Half-Kneeling Cable Press', 'One-Arm Incline Dumbbell Press']),
  ...g('secondary', 'Lateral', 'Lateral', 'Mixed', 'Strength', PB, ['Lateral Lunge', 'Cossack Squat', 'Slide-Board Lateral Lunge', 'Lateral Step-Up', 'Crossover Step-Up', 'Copenhagen Squat', 'Curtsy Lunge', 'Heiden Squat', 'Landmine Lateral Lunge']),
  ...g('secondary', 'Sled', 'Linear', 'Sled', 'Strength Endurance', PB, ['Heavy Sled Push', 'Heavy Sled Drag', 'Reverse Sled Drag', 'Lateral Sled Drag', 'Crossover Sled Drag', 'Sled March', 'Sled Sprint']),
  ...g('secondary', 'Carry', 'Unilateral', 'Mixed', 'Stability', PB, ['Farmer Carry', 'Suitcase Carry', 'Front-Rack Carry', 'Overhead Carry', 'Bottoms-Up Kettlebell Carry', 'Waiter Carry', 'Cross-Body Carry', 'Offset Carry', 'Zercher Carry', 'Trap-Bar Carry', 'Sandbag Carry']),

  // ── ACCESSORY ──────────────────────────────────────────────────────────────
  ...g('accessory', 'Glutes', 'Bilateral', 'Band/Bodyweight', 'Hypertrophy', PB, ['Glute Bridge', 'Single-Leg Glute Bridge', 'Hip Thrust', 'Single-Leg Hip Thrust', 'Frog Pump', 'Banded Hip Thrust', 'Cable Kickback', 'Lateral Band Walk', 'Monster Walk', 'Clamshell', 'Hip Airplane', 'Fire Hydrant']),
  ...g('accessory', 'Hamstrings', 'Bilateral', 'Bodyweight/Machine', 'Tissue Capacity', PB, ['Nordic Hamstring Curl', 'Glute-Ham Raise', 'Lying Hamstring Curl', 'Seated Hamstring Curl', 'Stability-Ball Leg Curl', 'Slider Hamstring Curl', 'Razor Curl', 'Reverse Hyperextension']),
  ...g('accessory', 'Quads', 'Unilateral', 'Machine/Bodyweight', 'Hypertrophy', PB, ['Leg Extension', 'Spanish Squat', 'Reverse Nordic Curl', 'Cyclist Squat', 'Peterson Step-Up', 'Step-Down', 'Wall Sit', 'Sissy Squat', 'Terminal Knee Extension']),
  ...g('accessory', 'Adductors', 'Lateral', 'Cable/Bodyweight', 'Tissue Capacity', PB, ['Copenhagen Plank', 'Short-Lever Copenhagen Plank', 'Copenhagen Hip Lift', 'Cable Hip Adduction', 'Adductor Machine', 'Isometric Ball Squeeze']),
  ...g('accessory', 'Abductors', 'Lateral', 'Band/Cable', 'Stability', PB, ['Mini-Band Lateral Walk', 'Side-Lying Hip Abduction', 'Cable Hip Abduction', 'Lateral Step-Down', 'Side Plank with Hip Abduction']),
  ...g('accessory', 'Calves & Ankle', 'Bilateral', 'Bodyweight/Machine', 'Tissue Capacity', PB, ['Standing Calf Raise', 'Seated Calf Raise', 'Single-Leg Calf Raise', 'Tibialis Raise', 'Eccentric Calf Raise', 'Soleus Wall Sit', 'Pogo Hold', 'Short-Foot Exercise']),
  ...g('accessory', 'Upper Back', 'Horizontal', 'Cable/Dumbbell', 'Arm Care', PB, ['Face Pull', 'Band Pull-Apart', 'Cable Reverse Fly', 'Dumbbell Reverse Fly', 'Prone T Raise', 'Prone Y Raise', 'Prone W Raise', 'Prone Trap Raise', 'Batwing Row', 'Scapular Pull-Up', 'High Cable Row']),
  ...g('accessory', 'Scapular Control', 'Horizontal', 'Bodyweight/Cable', 'Stability', ['pitcher-friendly'], ['Scapular Push-Up', 'Push-Up Plus', 'Wall Slide', 'Serratus Wall Slide', 'Serratus Punch', 'Bear Crawl', 'Landmine Press with Reach']),
  ...g('accessory', 'Rotator Cuff', 'Rotational', 'Band/Cable', 'Arm Care', ['pitcher-friendly', 'shoulder history modification'], ['Band External Rotation', 'Cable External Rotation', 'Side-Lying External Rotation', 'Prone External Rotation', 'External Rotation at 90°', 'Full-Can Raise', 'Scaption Raise', 'Cuban Rotation', 'Bottoms-Up Kettlebell Hold', 'Rhythmic Stabilization']),
  ...g('accessory', 'Chest', 'Horizontal', 'Dumbbell/Cable', 'Hypertrophy', PB, ['Push-Up', 'Incline Push-Up', 'Deficit Push-Up', 'Dumbbell Squeeze Press', 'Cable Fly', 'Low-to-High Cable Fly', 'Dumbbell Fly', 'Pec-Deck Fly']),
  ...g('accessory', 'Shoulders', 'Vertical', 'Dumbbell/Cable', 'Hypertrophy', PB, ['Dumbbell Lateral Raise', 'Cable Lateral Raise', 'Front Raise', 'Scaption Raise', 'Rear-Delt Raise', 'Arnold Press', 'Trap-3 Raise', 'Turkish Get-Up']),
  ...g('accessory', 'Biceps', 'Vertical', 'Dumbbell/Cable', 'Hypertrophy', PB, ['Dumbbell Curl', 'Hammer Curl', 'Cross-Body Hammer Curl', 'Incline Dumbbell Curl', 'Cable Curl', 'EZ-Bar Curl', 'Reverse Curl', 'Zottman Curl', 'Preacher Curl']),
  ...g('accessory', 'Triceps', 'Vertical', 'Cable/Dumbbell', 'Hypertrophy', PB, ['Rope Pressdown', 'Cable Pressdown', 'Overhead Cable Extension', 'Dumbbell Overhead Extension', 'Lying Triceps Extension', 'Close-Grip Push-Up', 'Tate Press', 'JM Press']),
  ...g('accessory', 'Forearms & Wrist', 'Rotational', 'Mixed', 'Tissue Capacity', ['pitcher-friendly', 'elbow history modification'], ['Wrist Curl', 'Reverse Wrist Curl', 'Wrist Roller', 'Plate Pinch', 'Rice-Bucket Work', 'Hand Gripper', 'Fat-Grip Hold', 'Bar Hang', 'Wrist Pronation', 'Wrist Supination', 'Radial Deviation', 'Ulnar Deviation']),
  ...g('accessory', 'Core — Anti-Extension', 'Bilateral', 'Bodyweight', 'Stability', PB, ['Dead Bug', 'Weighted Dead Bug', 'Hollow-Body Hold', 'Plank', 'Long-Lever Plank', 'RKC Plank', 'Body Saw', 'Ab-Wheel Rollout', 'Stability-Ball Rollout', 'Reverse Crunch', 'Hanging Knee Raise']),
  ...g('accessory', 'Core — Anti-Rotation', 'Rotational', 'Cable/Band', 'Stability', PB, ['Pallof Press', 'Half-Kneeling Pallof Press', 'Tall-Kneeling Pallof Press', 'Split-Stance Pallof Press', 'Bird Dog', 'Renegade Row', 'Plank Row']),
  ...g('accessory', 'Core — Anti-Lateral Flexion', 'Lateral', 'Mixed', 'Stability', PB, ['Side Plank', 'Copenhagen Plank', 'Suitcase Carry', 'Offset Farmer Carry', 'Side-Plank Row', 'Single-Arm Deadlift']),
  ...g('accessory', 'Core — Rotation', 'Rotational', 'Cable/Med Ball', 'Power', PB, ['Cable Chop', 'Cable Lift', 'Half-Kneeling Cable Chop', 'Landmine Rotation', 'Landmine Rainbow', 'Russian Twist']),
  ...g('accessory', 'Neck', 'Bilateral', 'Band/Bodyweight', 'Tissue Capacity', PB, ['Neck Flexion Isometric', 'Neck Extension Isometric', 'Lateral Neck Isometric', 'Chin Tuck', 'Prone Cobra', 'Shrug']),
  ...g('accessory', 'Grip & Hand', 'Unilateral', 'Mixed', 'Tissue Capacity', PB, ['Farmer Carry', 'Plate Pinch', 'Towel Hang', 'Bar Hang', 'Fat-Grip Hold', 'Rice-Bucket Exercises', 'Hand Gripper', 'Towel Pull-Up']),
];

export const STRENGTH_ROLE_BUCKETS = ['strength_primary', 'strength_secondary', 'strength_accessory'];
