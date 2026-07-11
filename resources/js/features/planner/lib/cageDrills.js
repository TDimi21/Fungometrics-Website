// ─────────────────────────────────────────────────────────────────────────────
// Cage drill library — single source of truth
//
// These are the objective-based cage hitting drills surfaced in the Practice
// Planner (PracticeCreatorScreen) AND selectable inside Cage Mode → Practice
// Mode (CagePracticeSetup). Keeping them here means the planner and the cage
// session always show the same drills.
//
// Each drill: { id, name, category: 'Cage', skill, difficulty, equipmentTags,
//               suggestedMinutes, objective, equipment, coachingCues }
// ─────────────────────────────────────────────────────────────────────────────

export const CAGE_DRILLS = [
  // Barrel Control
  { id: 'cage1', name: 'Short Bat Progression', category: 'Cage', skill: 'Barrel Control', difficulty: 1, equipmentTags: ['Short Bat'], suggestedMinutes: 8, objective: 'Shorten the bat to feel a tight, controlled barrel path.', equipment: 'Short bat, balls, net', coachingCues: 'Stay short to the ball, palm-up/palm-down at contact' },
  { id: 'cage2', name: 'One-Hand Top Hand Swings', category: 'Cage', skill: 'Barrel Control', difficulty: 2, equipmentTags: ['Short Bat'], suggestedMinutes: 6, objective: 'Isolate the top hand for barrel control and strength through the ball.', equipment: 'One-hand/short bat, tee, net', coachingCues: 'Lead with the knob, finish through the ball' },
  { id: 'cage3', name: 'One-Hand Bottom Hand Swings', category: 'Cage', skill: 'Barrel Control', difficulty: 2, equipmentTags: ['Short Bat'], suggestedMinutes: 6, objective: 'Isolate the bottom hand for direction and an on-plane path.', equipment: 'One-hand/short bat, tee, net', coachingCues: 'Pull the knob to the ball, stay connected' },
  { id: 'cage4', name: 'Inside Tee Drill', category: 'Cage', skill: 'Barrel Control', difficulty: 2, equipmentTags: ['Tee'], suggestedMinutes: 8, objective: 'Tee set inside to train staying inside the ball and turning on it.', equipment: 'Tee, balls, net', coachingCues: 'Hands inside, clear the hips, pull-side gap' },
  { id: 'cage5', name: 'Opposite Field Tee', category: 'Cage', skill: 'Barrel Control', difficulty: 2, equipmentTags: ['Tee'], suggestedMinutes: 8, objective: 'Tee set deep to drive the ball the other way.', equipment: 'Tee, balls, net', coachingCues: 'Let it travel, stay through the middle/oppo' },
  { id: 'cage6', name: 'Low Tee Through the Ball', category: 'Cage', skill: 'Barrel Control', difficulty: 2, equipmentTags: ['Tee'], suggestedMinutes: 8, objective: 'Low tee to train posture and staying through the low pitch.', equipment: 'Tee, balls, net', coachingCues: 'Get the back hip down, stay through it' },
  // Timing
  { id: 'cage7', name: 'High Tee Challenge', category: 'Cage', skill: 'Timing', difficulty: 2, equipmentTags: ['Tee'], suggestedMinutes: 8, objective: 'Hit high fastballs while keeping the barrel above the ball — 3 rounds x 8 swings, middle/oppo focus.', equipment: 'Bat, tee, bucket of balls', coachingCues: 'Match plane early, do not drop the hands, stay through it, finish tall' },
  { id: 'cage8', name: 'Walk-Up Timing Drill', category: 'Cage', skill: 'Timing', difficulty: 3, equipmentTags: ['Front Toss'], suggestedMinutes: 10, objective: 'Use a walk-up/load rhythm to be on time with the pitch.', equipment: 'Front toss or machine, balls', coachingCues: 'Move early, be on time — not quick' },
  { id: 'cage9', name: 'Fastball Timing', category: 'Cage', skill: 'Timing', difficulty: 3, equipmentTags: ['Machine'], suggestedMinutes: 10, objective: 'Time up fastballs at game velocity.', equipment: 'Machine/front toss, balls', coachingCues: 'Get the foot down on time, let it travel' },
  { id: 'cage10', name: 'Velocity Machine Round', category: 'Cage', skill: 'Timing', difficulty: 4, equipmentTags: ['Machine'], suggestedMinutes: 10, objective: 'See elevated velocity to sharpen timing and quick decisions.', equipment: 'Pitching machine, balls', coachingCues: 'Simplify the load, see it deep, short to it' },
  // Pitch Recognition
  { id: 'cage11', name: 'Breaking Ball Recognition', category: 'Cage', skill: 'Pitch Recognition', difficulty: 4, equipmentTags: ['Machine'], suggestedMinutes: 10, objective: 'Read spin and recognize breaking balls out of the hand.', equipment: 'Machine, balls', coachingCues: 'Track release, recognize spin, stay back' },
  { id: 'cage12', name: 'Mixed Pitch Sequence', category: 'Cage', skill: 'Pitch Recognition', difficulty: 4, equipmentTags: ['Machine'], suggestedMinutes: 12, objective: 'See mixed pitch types/speeds to train recognition and decisions.', equipment: 'Machine/coach, balls', coachingCues: 'Commit on time, adjust to speed' },
  { id: 'cage13', name: 'Chase Recognition', category: 'Cage', skill: 'Pitch Recognition', difficulty: 4, equipmentTags: ['Machine'], suggestedMinutes: 10, objective: 'Machine throws 70% strikes / 30% balls. Score: correct swing +2, correct take +2, chase -1, take strike -1.', equipment: 'Machine, balls', coachingCues: 'Swing at strikes, take balls, trust your zone' },
  { id: 'cage14', name: 'Random Location Challenge', category: 'Cage', skill: 'Pitch Recognition', difficulty: 4, equipmentTags: ['Tee'], suggestedMinutes: 10, objective: 'Coach moves the tee (up/down/in/away) after every swing; hitter cannot know the next spot. Score: solid contact %, hard-hit %, miss %.', equipment: 'Tee, balls, net', coachingCues: 'Stay athletic, adjust to the ball, balanced finish' },
  // Power
  { id: 'cage15', name: 'Damage Round', category: 'Cage', skill: 'Power', difficulty: 4, equipmentTags: ['Front Toss'], suggestedMinutes: 10, objective: 'Count only line drives, gaps and HR trajectory. Score: line drive 2, gap 3, HR 5, ground ball 0.', equipment: 'Machine/front toss, balls', coachingCues: 'Swing to do damage, drive the gaps' },
  { id: 'cage16', name: 'Launch Angle Round', category: 'Cage', skill: 'Power', difficulty: 4, equipmentTags: ['Tee'], suggestedMinutes: 10, objective: 'Train an optimal launch window for line drives and backspin.', equipment: 'Tee/front toss, net', coachingCues: 'Catch it out front, slight up-slope, backspin' },
  { id: 'cage17', name: 'Pull Side Power', category: 'Cage', skill: 'Power', difficulty: 3, equipmentTags: ['Tee'], suggestedMinutes: 8, objective: 'Turn and drive pull-side with intent.', equipment: 'Tee/front toss, net', coachingCues: 'Clear the hips, pull-side gap' },
  { id: 'cage18', name: 'Opposite Gap Power', category: 'Cage', skill: 'Power', difficulty: 3, equipmentTags: ['Tee'], suggestedMinutes: 8, objective: 'Drive the ball with authority to the opposite gap.', equipment: 'Tee/front toss, net', coachingCues: 'Let it travel, stay through the oppo gap' },
  { id: 'cage19', name: 'Fastball Away Round', category: 'Cage', skill: 'Power', difficulty: 3, equipmentTags: ['Machine'], suggestedMinutes: 8, objective: 'Fastballs on the outer third — drive the away pitch to the big part of the field.', equipment: 'Machine/front toss, balls', coachingCues: 'Let it get deep, stay through middle/oppo' },
  // Situational
  { id: 'cage20', name: 'Hit & Run Round', category: 'Cage', skill: 'Situational', difficulty: 3, equipmentTags: ['Front Toss'], suggestedMinutes: 8, objective: 'Put the ball in play on the ground / through the right side on command.', equipment: 'Front toss/machine, balls', coachingCues: 'Get the bat to the ball, stay short' },
  { id: 'cage21', name: 'Two-Strike Approach', category: 'Cage', skill: 'Situational', difficulty: 3, equipmentTags: ['Front Toss'], suggestedMinutes: 8, objective: 'Choke up, widen the zone, battle and put it in play.', equipment: 'Front toss/machine, balls', coachingCues: 'Shorten up, fight, hit it where it is pitched' },
  { id: 'cage22', name: 'Situational Hitting', category: 'Cage', skill: 'Situational', difficulty: 3, equipmentTags: ['Front Toss'], suggestedMinutes: 10, objective: 'Execute the situation: move the runner, score from third, etc.', equipment: 'Front toss/machine, balls', coachingCues: 'Know the job, execute the plan' },
  { id: 'cage23', name: 'Sac Fly', category: 'Cage', skill: 'Situational', difficulty: 2, equipmentTags: ['Front Toss'], suggestedMinutes: 6, objective: 'Elevate to the outfield to score the runner from third.', equipment: 'Front toss/machine, balls', coachingCues: 'Get under it just enough, drive it to the OF' },
  { id: 'cage24', name: 'Runner on Third', category: 'Cage', skill: 'Situational', difficulty: 3, equipmentTags: ['Front Toss'], suggestedMinutes: 8, objective: 'Score the runner from third with the infield in or back.', equipment: 'Front toss/machine, balls', coachingCues: 'Find a pitch to elevate or drive' },
  { id: 'cage25', name: 'Bunt Progression', category: 'Cage', skill: 'Situational', difficulty: 1, equipmentTags: [], suggestedMinutes: 6, objective: 'Sacrifice and bunt-for-hit fundamentals.', equipment: 'Bat, balls', coachingCues: 'Top hand controls the barrel, deaden it' },
  // Competition
  { id: 'cage26', name: 'Around the World', category: 'Cage', skill: 'Competition', difficulty: 3, equipmentTags: ['Front Toss'], suggestedMinutes: 10, objective: 'Hit to each field/zone in sequence to win the round.', equipment: 'Front toss/machine, balls', coachingCues: 'Execute each zone, compete' },
  { id: 'cage27', name: 'Team Barrel Challenge', category: 'Cage', skill: 'Competition', difficulty: 3, equipmentTags: ['Front Toss'], suggestedMinutes: 12, objective: 'Teams compete for the most barreled balls.', equipment: 'Front toss/machine, balls', coachingCues: 'Quality contact wins, compete each swing' },
  { id: 'cage28', name: 'Exit Velo Contest', category: 'Cage', skill: 'Competition', difficulty: 4, equipmentTags: ['Machine', 'Radar'], suggestedMinutes: 10, objective: 'Compete for the highest / most consistent exit velocity.', equipment: 'Machine/front toss, radar', coachingCues: 'Be on time, swing to do damage' },
  { id: 'cage29', name: '21 Outs Challenge', category: 'Cage', skill: 'Competition', difficulty: 3, equipmentTags: ['Machine'], suggestedMinutes: 14, objective: 'Simulate at-bats; bad swings/outs count against you — first to clean reps wins.', equipment: 'Machine/front toss, balls', coachingCues: 'Compete every pitch, quality over max effort' },
  { id: 'cage30', name: 'Live AB Simulation', category: 'Cage', skill: 'Competition', difficulty: 5, equipmentTags: ['Machine'], suggestedMinutes: 20, objective: 'Highest-level cage drill — fastballs, breaking balls, changeups, scored exactly like Live AB so cage to Live AB to game use identical scoring.', equipment: 'Machine/live arm, full setup', coachingCues: 'Compete every pitch with a game plan' },
];

// Skill buckets, in the order they should be presented.
export const CAGE_SKILL_ORDER = [
  'Barrel Control',
  'Timing',
  'Pitch Recognition',
  'Power',
  'Situational',
  'Competition',
];

/** Look up a cage drill by its id. */
export function getCageDrillById(id) {
  return CAGE_DRILLS.find((d) => d.id === id) || null;
}

/** Group the cage drills by their skill bucket (in CAGE_SKILL_ORDER). */
export function cageDrillsBySkill() {
  return CAGE_SKILL_ORDER.map((skill) => ({
    skill,
    drills: CAGE_DRILLS.filter((d) => d.skill === skill),
  })).filter((g) => g.drills.length > 0);
}
