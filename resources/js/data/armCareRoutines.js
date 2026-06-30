/**
 * FMTRX Elite Throwing Preparation — Arm Care routine content.
 *
 * Pure data so the routine can be edited without touching UI/score logic.
 * Shape:
 *   ROUTINE = { key, label, subtitle, estMinutes, phases: [PHASE] }
 *   PHASE   = { id, name, goal, estMinutes, exercises: [EXERCISE] }
 *   EXERCISE= { id, name, prescription, cue, required }
 *
 * `required` exercises count toward the Arm Care Compliance Score denominator
 * even when skipped; optional ones only count once completed. (See armCareStore.)
 */

// ── Phase builders (shared between routine variants) ────────────────────────

const phaseBloodFlow = {
  id: 'blood_flow',
  name: 'Increase Blood Flow',
  goal: 'Raise body temperature and circulation. Choose ONE option to get warm.',
  estMinutes: 10,
  // Pick one option — completing any one satisfies the phase (counts as 1 required).
  selectOne: true,
  exercises: [
    { id: 'bf_stairmaster', name: 'StairMaster', prescription: '10 minutes · Level 5–7', cue: 'Steady, conversational effort. You should feel warm, not gassed.', required: true },
    { id: 'bf_bicycle', name: 'Bicycle Sprints', prescription: '20 × 5-second bursts', cue: 'Moderate resistance. Explosive on the burst, easy spin between.', required: true },
    { id: 'bf_sled', name: 'Sled Marches', prescription: '8 × 10 yards · 1 plate (45 lb)', cue: 'Slow and controlled. Down = 1 rep, back = 2 reps.', required: true },
    { id: 'bf_stepups', name: 'Step-Ups', prescription: '50 total (25 each leg)', cue: 'Drive through the heel; control the way down.', required: true },
  ],
};

const phaseSoftTissue = {
  id: 'soft_tissue',
  name: 'Soft Tissue Preparation',
  goal: 'Release tight muscles and improve tissue quality before activation.',
  estMinutes: 8,
  exercises: [
    {
      id: 'st_lacrosse',
      name: 'Lacrosse Ball',
      prescription: 'Roll each spot ~20–30s',
      cue: 'Release tight muscles and improve tissue quality.',
      required: true,
      subItems: [
        { id: 'st_lacrosse_upper_traps', name: 'Upper Traps' },
        { id: 'st_lacrosse_scap', name: 'Scapular Muscles' },
        { id: 'st_lacrosse_rear_delt', name: 'Rear Deltoid' },
        { id: 'st_lacrosse_lat_delt', name: 'Lateral Deltoid' },
        { id: 'st_lacrosse_front_delt', name: 'Front Deltoid (gentle)' },
        { id: 'st_lacrosse_pec_minor', name: 'Pec Minor' },
        { id: 'st_lacrosse_feet', name: 'Bottom of Feet' },
      ],
    },
    {
      id: 'st_handroller',
      name: 'Hand Roller',
      prescription: 'Roll each group slowly',
      cue: 'Smooth, deliberate passes — no rushing.',
      required: true,
      subItems: [
        { id: 'st_hand_triceps', name: 'Triceps' },
        { id: 'st_hand_biceps', name: 'Biceps' },
        { id: 'st_hand_flexors', name: 'Forearm Flexors' },
        { id: 'st_hand_extensors', name: 'Forearm Extensors' },
        { id: 'st_hand_palm', name: 'Palm' },
      ],
    },
    {
      id: 'st_foamroller',
      name: 'Foam Roller',
      prescription: 'Roll each group slowly',
      cue: 'Sink into each group and breathe.',
      required: true,
      subItems: [
        { id: 'st_foam_glutes', name: 'Glutes' },
        { id: 'st_foam_hamstrings', name: 'Hamstrings' },
        { id: 'st_foam_itband', name: 'IT Band' },
        { id: 'st_foam_quads', name: 'Quadriceps' },
      ],
    },
  ],
};

const phaseMobility = {
  id: 'mobility',
  name: 'Mobility & Stretching',
  goal: 'Open up range of motion through neck, shoulders, hips, and t-spine.',
  estMinutes: 12,
  exercises: [
    // Neck
    { id: 'mb_neck_cars', name: 'Neck CARs', prescription: '3 each direction', cue: 'Slow controlled circles — find the edges of your range.', required: true },
    // Shoulder / upper body
    { id: 'mb_scap_cars', name: 'Scapular CARs', prescription: '3 each', cue: 'Move the shoulder blade, not the arm.', required: true },
    { id: 'mb_trap_cars', name: 'Trap CARs', prescription: '3 each', cue: 'Elevate, retract, depress — full square.', required: true },
    { id: 'mb_impossible_pulls', name: 'Impossible Pulls', prescription: '10 seconds', cue: 'Pull as if tearing the band — nothing moves but everything fires.', required: true },
    { id: 'mb_wall_shoulder_cars', name: 'Wall Shoulder CARs', prescription: '5 each', cue: 'Keep the back of the hand on the wall as long as possible.', required: true },
    { id: 'mb_wall_tricep', name: 'Wall Triceps Stretch', prescription: '10 seconds', cue: 'Feel the long head of the triceps lengthen.', required: false },
    { id: 'mb_wall_forearm', name: 'Wall Forearm Stretch', prescription: '10 seconds', cue: 'Gentle pull through the forearm flexors.', required: false },
    { id: 'mb_pec_stretch', name: 'Pec Stretch', prescription: '10 seconds', cue: 'Open the chest; keep the shoulder down.', required: true },
    { id: 'mb_high_hand_pec', name: 'High-Hand Pec Stretch', prescription: '10 seconds', cue: 'Hand high on the wall to bias the upper pec.', required: false },
    { id: 'mb_wall_good_mornings', name: 'Wall Good Mornings', prescription: '10s each: left / middle / right', cue: 'Hinge into the wall; feel the lats and posterior chain.', required: false },
    { id: 'mb_serratus_lat', name: 'Serratus Anterior Lat Stretch', prescription: '10 seconds', cue: 'Reach long and sink the ribcage.', required: false },
    { id: 'mb_doorway', name: 'Doorway Stretch', prescription: '1 × 5 seconds', cue: 'Big chest opener — relax into it.', required: false },
    // Hips & lower body
    { id: 'mb_9090', name: '90/90 Hip Lean In / Out', prescription: '10 seconds', cue: 'Tall spine; rotate from the hip, not the low back.', required: true },
    { id: 'mb_groin_rockers', name: 'Groin Rockers', prescription: '3 float reps', cue: 'Rock back until you feel the inner thigh, float forward.', required: false },
    { id: 'mb_three_way_lunge', name: 'Three-Way Lunges', prescription: '2 each direction', cue: 'Forward, lateral, rotational — control the descent.', required: true },
    { id: 'mb_hip_cars', name: 'Hip CARs', prescription: '5 each', cue: 'Biggest circle you own; keep the rest of the body quiet.', required: true },
    { id: 'mb_knee_cars', name: 'Knee CARs', prescription: '5 each', cue: 'Slow, controlled rotation through the knee.', required: false },
    { id: 'mb_ankle_cars', name: 'Ankle CARs', prescription: '5 each', cue: 'Trace the full circle with the toes.', required: false },
    { id: 'mb_hip_rocker_flexor', name: 'Hip Rocker to Hip Flexor', prescription: '3 float reps', cue: 'Drive the hips through; feel the front of the hip open.', required: false },
    // Thoracic spine
    { id: 'mb_pole_twists', name: 'Walking Pole Twists', prescription: '5 each', cue: 'Rotate around a tall spine; eyes follow the turn.', required: true },
    { id: 'mb_wall_tspine', name: 'Wall T-Spine Rotations', prescription: '5 each', cue: 'Open the top arm; keep the hips square.', required: true },
    { id: 'mb_pelvic_strap', name: 'Pelvic Rotations with Strap', prescription: '5 each', cue: 'Separate hips from ribcage.', required: false },
  ],
};

const phaseRotatorCuff = {
  id: 'rotator_cuff',
  name: 'Rotator Cuff Activation',
  goal: 'Activate the small stabilizing muscles before throwing.',
  estMinutes: 10,
  exercises: [
    // External rotation series
    { id: 'rc_er_circles_down', name: 'Palm Down ER Circles', prescription: '3 each', cue: 'Small, deliberate circles — feel the back of the shoulder.', required: true },
    { id: 'rc_er_circles_up', name: 'Palm Up ER Circles', prescription: '3 each', cue: 'Stay tall; rotate from the shoulder.', required: true },
    { id: 'rc_bw_er_raises', name: 'Bodyweight ER Raises', prescription: '3', cue: 'Lead with the back of the hand.', required: true },
    { id: 'rc_alt_er_raises', name: 'Alternating ER Raises', prescription: '3 each', cue: 'Control both up and down.', required: true },
    // Green ball series
    { id: 'rc_gb_er_raises', name: 'Green Ball ER Raises', prescription: '3', cue: 'Light ball — quality over load.', required: true },
    { id: 'rc_gb_alt_er', name: 'Green Ball Alternating ER Raises', prescription: '3 each', cue: 'Smooth tempo, no shrugging.', required: false },
    { id: 'rc_gb_swimmers', name: 'Swimmers', prescription: '5', cue: 'Big circle around the body; keep the core braced.', required: false },
    // Physio ball series — pause 1s at top
    { id: 'rc_pb_t_raises', name: 'Serratus T Raises', prescription: '3 each · pause 1s at top', cue: 'Thumbs up; squeeze the mid-back.', required: true },
    { id: 'rc_pb_y_raises', name: 'Serratus Y Raises', prescription: '3 each · pause 1s at top', cue: 'Arms to the Y; lift from the shoulder blade.', required: true },
    { id: 'rc_pb_er_raises', name: 'Serratus ER Raises', prescription: '3 each · pause 1s at top', cue: 'Externally rotate, then hold.', required: true },
    // Rotational stability
    { id: 'rc_straight_arm_rot', name: 'Straight Arm Green Ball Rotations', prescription: '5 each', cue: 'Lock the elbow; rotate the whole arm.', required: false },
    { id: 'rc_er_up_rot', name: 'ER Up Green Ball Rotations', prescription: '5 each', cue: 'Hold ER position; rotate without losing it.', required: false },
    // Wall taps — all four positions, R & L
    { id: 'rc_wall_taps', name: 'Wall Taps (I · Y · T · ER)', prescription: '5 each position · right & left', cue: 'Light taps; keep the shoulder packed.', required: true },
    // Band pull-aparts
    { id: 'rc_pullaparts_low_er', name: 'Palms-Up Low ER Pull-Aparts', prescription: '5 slow reps · large red band', cue: 'Slow stretch; pull from the mid-back.', required: true },
    { id: 'rc_pulses_low_er', name: 'Low ER Pulses', prescription: '10 seconds · red band', cue: 'Small fast pulses; constant tension.', required: false },
    { id: 'rc_pulses_extended', name: 'Arms Extended Pulses', prescription: '10 seconds · red band', cue: 'Arms long; pulse without shrugging.', required: false },
  ],
};

const phaseJBands = {
  id: 'j_bands',
  name: 'J-Band Series',
  goal: 'Activate the shoulder through full ranges of motion.',
  estMinutes: 8,
  exercises: [
    { id: 'jb_small_circles', name: 'Small Arm Circles', prescription: '3 each direction', cue: 'Constant band tension; small and controlled.', required: true },
    { id: 'jb_large_circles', name: 'Large Arm Circles', prescription: '3 each direction', cue: 'Biggest circle you control without the band going slack.', required: true },
    { id: 'jb_right_er', name: 'Right ER Pulls (opposite hand on head)', prescription: '3', cue: 'Lead with the back of the hand; stay tall.', required: true },
    { id: 'jb_right_ir', name: 'Right IR Pulls', prescription: '3', cue: 'Drive across the body; control the return.', required: true },
    { id: 'jb_left_er', name: 'Left ER Pulls', prescription: '3', cue: 'Match the right side; no shrugging.', required: true },
    { id: 'jb_left_ir', name: 'Left IR Pulls', prescription: '3', cue: 'Smooth across the body.', required: true },
    { id: 'jb_rear_flys', name: 'Rear Flys', prescription: '3', cue: 'Squeeze the rear delts; thumbs up.', required: true },
  ],
};

const phaseThrowingPrep = {
  id: 'throwing_prep',
  name: 'Scapular Stability & Throwing Prep',
  goal: 'Lock in stability, then transition into smooth, controlled throws.',
  estMinutes: 5,
  exercises: [
    // Isometric holds — 10s each
    { id: 'tp_iso_low_er', name: 'Iso: Low External Rotation', prescription: '10-second hold', cue: 'Pack the shoulder; brace the core.', required: true },
    { id: 'tp_iso_straight_thumb', name: 'Iso: Extended Straight Arm (thumb up)', prescription: '10-second hold', cue: 'Long arm, thumb up — hold rock-solid.', required: true },
    { id: 'tp_iso_lat_thumb', name: 'Iso: Extended Lat (thumb up)', prescription: '10-second hold', cue: 'Feel the lat engage; stay tall.', required: true },
    { id: 'tp_iso_straight_palm', name: 'Iso: Extended Straight Arm (palm down)', prescription: '10-second hold', cue: 'Palm down; resist any drop.', required: true },
    { id: 'tp_iso_high_er', name: 'Iso: High External Rotation', prescription: '10-second hold', cue: 'Throwing position; hold it stable.', required: true },
    // Throwing transition
    { id: 'tp_split_stance_throws', name: 'Split Stance Full Motion Throws', prescription: '2 controlled reps', cue: 'Take your time. Reset between throws. Smooth movement, proper mechanics.', required: true },
  ],
};

// ── Routine variants ────────────────────────────────────────────────────────

export const ARM_CARE_ROUTINES = [
  {
    key: 'pre_throw',
    label: 'Pre-Throw Routine',
    subtitle: 'Full elite throwing preparation',
    estMinutes: 53,
    phases: [
      phaseBloodFlow,
      phaseSoftTissue,
      phaseMobility,
      phaseRotatorCuff,
      phaseJBands,
      phaseThrowingPrep,
    ],
  },
  {
    key: 'post_throw',
    label: 'Post-Throw Recovery',
    subtitle: 'Flush, lengthen, and down-regulate after throwing',
    estMinutes: 20,
    phases: [
      phaseBloodFlow,
      phaseSoftTissue,
      phaseMobility,
    ],
  },
  {
    key: 'recovery_day',
    label: 'Recovery Day',
    subtitle: 'Tissue quality + mobility, no throwing',
    estMinutes: 20,
    phases: [
      phaseSoftTissue,
      phaseMobility,
    ],
  },
  {
    key: 'custom',
    label: 'Custom Arm Care',
    subtitle: 'Cuff activation + J-bands + throwing prep',
    estMinutes: 23,
    phases: [
      phaseRotatorCuff,
      phaseJBands,
      phaseThrowingPrep,
    ],
  },
];

export function getRoutineByKey(key) {
  return ARM_CARE_ROUTINES.find((r) => r.key === key) || null;
}

/** Total number of drills in a routine (sub-drills expand their parent). */
export function countExercises(routine) {
  if (!routine) return 0;
  return routine.phases.reduce(
    (sum, p) => sum + p.exercises.reduce((s, e) => s + (e.subItems?.length || 1), 0),
    0,
  );
}

export function countRequired(routine) {
  if (!routine) return 0;
  return routine.phases.reduce((sum, p) => {
    // A "choose one" phase contributes a single required item to the score.
    if (p.selectOne) return sum + 1;
    // Exercises with sub-drills are scored per sub-drill; otherwise per exercise.
    return sum + p.exercises.reduce((s, e) => {
      if (e.subItems?.length) return s + e.subItems.length;
      return s + (e.required ? 1 : 0);
    }, 0);
  }, 0);
}
