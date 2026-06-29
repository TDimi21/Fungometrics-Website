/**
 * FMTRX standardized practice locations, grouped. Coaches assign a location to
 * each drill/station so a practice plan reads like a station map (where to go,
 * what to do, what gear is needed, how long).
 */

export const LOCATION_GROUPS = {
  'Warm-Up Areas': ['Home Plate Circle', 'Foul Territory', 'Right Field Foul Line', 'Left Field Foul Line', 'Outfield Grass', 'Warning Track', 'Team Stretch Area'],
  Hitting: ['Batting Cage 1', 'Batting Cage 2', 'Batting Cage 3', 'Indoor Cage', 'Outdoor Cage', 'Tee Station', 'Front Toss Station', 'Side Toss Station', 'Pitching Machine Station', 'Live BP Cage', 'Soft Toss Station', 'Short Bat Station', 'Exit Velocity Station', 'HitTrax Station', 'Rapsodo Hitting Station'],
  Pitching: ['Bullpen 1', 'Bullpen 2', 'Bullpen 3', 'Indoor Bullpen', 'Outdoor Bullpen', 'Flat Ground Station', 'Long Toss Station', 'Weighted Ball Station', 'Command Station', 'Velocity Station', 'Recovery Throwing Station', 'Pitch Design Station', 'Rapsodo Pitching Station', 'TrackMan Pitching Station'],
  Catching: ['Receiving Station', 'Blocking Station', 'Pop Time Station', 'Throw Down Station', 'Framing Station'],
  Infield: ['First Base', 'Second Base', 'Third Base', 'Shortstop', 'Double Play Station', 'Infield Fundamentals', 'Ground Ball Station', 'Slow Roller Station', 'Short Hop Station', 'Corner Infield', 'Middle Infield'],
  Outfield: ['Left Field', 'Center Field', 'Right Field', 'Fly Ball Station', 'Drop Step Station', 'Wall Ball Station', 'Crow Hop Station', 'Relay Throw Station'],
  'Base Running': ['Home to First', 'First Base (BR)', 'Second Base (BR)', 'Third Base (BR)', 'Lead-Off Station', 'Steal Station', 'Sliding Station', 'First-to-Third Station', 'Rundown Station'],
  'Team Defense': ['Team Infield', 'Team Outfield', 'Cutoffs & Relays', 'First & Third Defense', 'Bunt Defense', 'Pickoff Defense', 'PFP', 'Defensive Situations', 'Live Defense'],
  Conditioning: ['Speed Lane', 'Sprint Station', 'Agility Station', 'Ladder Station', 'Cone Station', 'Sled Station', 'Plyometric Station', 'Jump Training', 'Conditioning Track'],
  'Strength & Performance': ['Weight Room', 'Power Rack', 'Dumbbell Area', 'Medicine Ball Area', 'Turf', 'Recovery Area', 'Mobility Area', 'Arm Care Station', 'Grip Strength Station'],
  'Assessment Stations': ['Check-In', 'Height & Weight', 'Athletic Testing', 'Strength Testing', 'Mobility Screening', 'Exit Velocity Testing', 'Throwing Velocity Testing', 'Bullpen Assessment', 'Hitting Assessment', 'Vision Testing', 'Performance Testing'],
  'Technology Stations': ['Pocket Radar', 'Rapsodo', 'TrackMan', 'Blast Motion', 'Diamond Kinetics', 'Video Analysis', 'Slow Motion Capture'],
  Classroom: ['Film Room', 'Whiteboard Session', 'Chalk Talk', 'Mental Performance', 'Team Meeting', 'Recruiting Review'],
  Recovery: ['Foam Rolling', 'Stretching', 'Recovery Zone', 'Ice Bath', 'Hydration Station'],
};

export const LOCATIONS_ALL = Object.values(LOCATION_GROUPS).flat();
