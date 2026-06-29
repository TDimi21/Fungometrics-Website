/**
 * FMTRX Equipment Library
 *
 * Coaches set the gear their program actually has; the Practice Planner can then
 * show only drills that can be run with that equipment. Drills declare what they
 * need via `equipmentTags` (a drill with no tags needs nothing special and is
 * always runnable).
 */

// The full, set-up-once equipment list, grouped by FMTRX category. Toggled by
// the coach in "Available Equipment". Strings here must match drill equipmentTags.
export const EQUIPMENT_LIBRARY = {
  Hitting: ['Tee', 'Front Toss', 'Machine', 'Live Arm', 'Short Bat', 'Weighted Bat', 'Net / Cage', 'L-Screen'],
  Pitching: ['Bullpen Mound', 'Flat Ground', 'Long Toss', 'Weighted Balls', 'Command Target', 'Radar'],
  Defense: ['Fungo Bat', 'Infield', 'Outfield', 'Catching Gear'],
  'Athletic Performance': ['Cones', 'Agility Ladder', 'Bands', 'Med Ball', 'Sled'],
  Technology: ['Pocket Radar', 'Rapsodo', 'TrackMan', 'Blast Motion', 'Diamond Kinetics', 'HitTrax'],
  'Coach Resources': ['Clipboard', 'Stopwatch', 'Tablet', 'Video Camera'],
};

// Flat list of every equipment string.
export const EQUIPMENT_ALL = Object.values(EQUIPMENT_LIBRARY).flat();

// A drill is runnable if every piece of equipment it needs is available.
// Drills with no equipmentTags need nothing special → always runnable.
export function drillRunnable(drill, available) {
  const tags = (drill && drill.equipmentTags) || [];
  if (!tags.length) return true;
  const have = available instanceof Set ? available : new Set(available || []);
  return tags.every((t) => have.has(t));
}
