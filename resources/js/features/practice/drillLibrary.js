/**
 * Practice Planner drill library + option lists — ported from the mobile app's
 * PracticeCreatorScreen so the web planner uses the same defaults.
 */

export const DRILL_CATEGORIES = ['Warmup', 'Hitting', 'Defense', 'Pitching', 'Conditioning', 'Situational', 'Game Sim'];
export const FOCUS_OPTIONS = ['Mixed', 'Pitching', 'Defense', 'Hitting', 'Conditioning'];
export const GROUP_OPTIONS = ['Full Team', 'Pitchers', 'Hitters', 'Infield', 'Outfield', 'Varsity', 'JV', 'Custom'];

export const DEFAULT_DRILL_LIBRARY = [
  { id: 'dl1', name: 'Dynamic Warmup', category: 'Warmup', suggestedMinutes: 10, objective: 'Activate muscles, prevent injury', equipment: 'None', coachingCues: 'Full range of motion on all movements' },
  { id: 'dl2', name: 'Infield Defense', category: 'Defense', suggestedMinutes: 20, objective: 'Ground ball reps & footwork', equipment: 'Fungo bat, balls', coachingCues: 'Soft hands, stay low through the ball' },
  { id: 'dl3', name: 'Bullpen Session', category: 'Pitching', suggestedMinutes: 20, objective: 'Pitch command and mechanics', equipment: 'Bullpen mound, catcher', coachingCues: 'Drive off the rubber, stay on top' },
  { id: 'dl4', name: 'BP Round 1', category: 'Hitting', suggestedMinutes: 15, objective: 'Contact work off live BP', equipment: 'L-screen, balls, bats', coachingCues: 'Load early, stay short to the ball' },
  { id: 'dl5', name: 'Sprint Intervals', category: 'Conditioning', suggestedMinutes: 10, objective: 'Speed + burst development', equipment: 'Cones', coachingCues: 'Full effort, walk back recovery' },
  { id: 'dl6', name: 'Exit Velocity Work', category: 'Hitting', suggestedMinutes: 15, objective: 'Max power contact drills', equipment: 'Tee, radar gun', coachingCues: 'Drive through contact, full extension' },
  { id: 'dl7', name: 'Outfield Routes', category: 'Defense', suggestedMinutes: 15, objective: 'Drop steps and read reps', equipment: 'Fly balls, fungo', coachingCues: 'First step back on everything' },
  { id: 'dl8', name: 'Situational Defense', category: 'Situational', suggestedMinutes: 20, objective: 'Cuts, relays, rundowns', equipment: 'Full field setup', coachingCues: 'Communicate loudly, know the play before it happens' },
  { id: 'dl9', name: 'Live AB Scrimmage', category: 'Game Sim', suggestedMinutes: 30, objective: 'Game-speed ABs for pitchers and hitters', equipment: 'Full field', coachingCues: 'Compete every pitch' },

  // Throwing Warmups — Basic Progression
  { id: 'dl10', name: 'Wrist Flicks', category: 'Warmup', suggestedMinutes: 4, objective: 'Short throws to feel spin and clean release', equipment: 'Baseballs, partner', coachingCues: 'Stay loose, snap fingers through the ball' },
  { id: 'dl11', name: 'Knee Throws', category: 'Warmup', suggestedMinutes: 5, objective: 'Isolate upper body and arm path', equipment: 'Baseballs, partner', coachingCues: 'Stay tall, rotate through core, finish out front' },
  { id: 'dl12', name: 'Rock & Throw', category: 'Warmup', suggestedMinutes: 5, objective: 'Blend lower half into controlled throwing rhythm', equipment: 'Baseballs, partner', coachingCues: 'Rock smoothly, small stride, stay online to target' },
  { id: 'dl13', name: 'Step Behind Throws', category: 'Warmup', suggestedMinutes: 6, objective: 'Build momentum and timing into throws', equipment: 'Baseballs, partner', coachingCues: 'Stay athletic, transfer energy forward, finish balanced' },
  { id: 'dl14', name: 'Catch Play (Short Distance)', category: 'Warmup', suggestedMinutes: 8, objective: 'Build arm up at 30–60 feet with accuracy', equipment: 'Baseballs, partner', coachingCues: 'Aim chest-high, smooth transfer, consistent tempo' },

  // Throwing Warmups — Arm Strength / Mechanics
  { id: 'dl15', name: 'Long Toss Progression', category: 'Pitching', suggestedMinutes: 12, objective: 'Increase arm speed and throwing capacity safely', equipment: 'Baseballs, open space', coachingCues: 'Gradually back up, arc out, line-drive back in' },
  { id: 'dl16', name: 'Pull-Down Throws', category: 'Pitching', suggestedMinutes: 8, objective: 'Convert long-toss carry into on-line intent', equipment: 'Baseballs, partner', coachingCues: 'Stay tall, aggressive arm speed, finish through target' },
  { id: 'dl17', name: 'Quick Release Throws', category: 'Defense', suggestedMinutes: 7, objective: 'Speed up catch-to-throw transfer', equipment: 'Baseballs, partner', coachingCues: 'Soft hands, quick feet, short arm path' },
  { id: 'dl18', name: 'One-Hand Picks + Throw', category: 'Defense', suggestedMinutes: 8, objective: 'Glove-only field and fast exchange to throw', equipment: 'Baseballs, fungo/coach', coachingCues: 'Attack the ball, secure cleanly, throw in rhythm' },
  { id: 'dl19', name: 'Shuffle Throws', category: 'Defense', suggestedMinutes: 7, objective: 'Infield footwork and alignment on throws', equipment: 'Baseballs, cones', coachingCues: 'Catch, shuffle under control, throw from strong base' },

  // Throwing Warmups — Accuracy / Target Work
  { id: 'dl20', name: 'Partner Target Game', category: 'Warmup', suggestedMinutes: 8, objective: 'Compete for chest-high strike accuracy', equipment: 'Baseballs, partner target', coachingCues: 'Track target early, firm front side, finish on line' },
  { id: 'dl21', name: 'Bucket Throw Drill', category: 'Warmup', suggestedMinutes: 8, objective: 'Improve precision to a small target zone', equipment: 'Bucket/cone target, baseballs', coachingCues: 'Throw through the target, consistent release point' },
  { id: 'dl22', name: 'Hit the Net Drill', category: 'Warmup', suggestedMinutes: 8, objective: 'Repeatable accuracy to a marked square', equipment: 'Net/fence target, baseballs', coachingCues: 'Keep shoulders level, hit same window repeatedly' },

  // Throwing Warmups — Game-Speed / Competitive
  { id: 'dl23', name: 'Rapid Fire Catch', category: 'Conditioning', suggestedMinutes: 6, objective: 'High-tempo throwing with clean mechanics', equipment: 'Baseballs, partner', coachingCues: 'Fast pace, no rushing mechanics, quick reset each rep' },
  { id: 'dl24', name: 'Around the World Throws', category: 'Defense', suggestedMinutes: 10, objective: 'Throw from multiple angles and body positions', equipment: 'Cones, baseballs', coachingCues: 'Adjust feet early, keep arm slot consistent to target' },
  { id: 'dl25', name: 'Relay Throws', category: 'Situational', suggestedMinutes: 12, objective: 'Simulate cutoff/relay timing and communication', equipment: 'Cones, baseballs, full field area', coachingCues: 'Early communication, line up shoulders, quick transfer' },
  { id: 'dl26', name: 'Crow Hop Throwing', category: 'Defense', suggestedMinutes: 8, objective: 'Outfield momentum throws with carry', equipment: 'Baseballs, outfield space', coachingCues: 'Gather cleanly, powerful crow hop, throw on line' },

  // Throwing Warmups — Athletic / Youth Friendly
  { id: 'dl27', name: 'Barehand Catch + Throw', category: 'Warmup', suggestedMinutes: 6, objective: 'Improve hand softness and rapid exchange', equipment: 'Soft baseballs/tennis balls', coachingCues: 'Soft hands, eyes to ball, quick but controlled release' },
  { id: 'dl28', name: 'Backhand & Forehand Throws', category: 'Defense', suggestedMinutes: 8, objective: 'Work glove side and backhand throwing plays', equipment: 'Baseballs, cones', coachingCues: 'Stay low, field out front, throw from balanced base' },
  { id: 'dl29', name: 'Competition: First to 10 Perfect Throws', category: 'Game Sim', suggestedMinutes: 10, objective: 'Pressure-based accuracy and focus challenge', equipment: 'Targets, baseballs, scoreboard', coachingCues: 'Reset routine each rep, accuracy over max effort' },

  // Throwing / Warmups
  { id: 'dl30', name: 'Stretch / Throw', category: 'Warmup', suggestedMinutes: 8, objective: 'Prepare body and arm before high intent throws', equipment: 'Bands, baseballs', coachingCues: 'Stretch with control, build intensity gradually' },
  { id: 'dl31', name: 'Dynamic Stretch & Throw', category: 'Warmup', suggestedMinutes: 10, objective: 'Blend movement prep with light throwing', equipment: 'Cones, baseballs', coachingCues: 'Stay athletic through each movement, clean arm path' },
  { id: 'dl32', name: 'Pregame Outfield Throwing Series', category: 'Defense', suggestedMinutes: 10, objective: 'Outfield arm prep to game-speed targets', equipment: 'Baseballs, outfield space', coachingCues: 'Gather quickly, stay online to each base target' },
  { id: 'dl33', name: 'Positional Throwing Drills', category: 'Defense', suggestedMinutes: 12, objective: 'Position-specific footwork and throw patterns', equipment: 'Baseballs, cones', coachingCues: 'Prioritize feet before hand speed' },
  { id: 'dl34', name: 'Throwing to Bases', category: 'Defense', suggestedMinutes: 10, objective: 'Accurate throws to each base from different spots', equipment: 'Baseballs, full infield', coachingCues: 'Hit the cutoff side of each base, throw on a line' },
  { id: 'dl35', name: 'Throwing Home', category: 'Defense', suggestedMinutes: 10, objective: 'Develop carry and accuracy to home plate', equipment: 'Baseballs, plate target', coachingCues: 'Stay through the throw, finish chest to target' },

  // Infield / Ground Ball Work
  { id: 'dl36', name: 'Mass Ground Balls', category: 'Defense', suggestedMinutes: 12, objective: 'High-volume clean fielding reps', equipment: 'Fungo bat, baseballs', coachingCues: 'Read hop early, funnel to center, quick feet' },
  { id: 'dl37', name: 'Situational Ground Balls', category: 'Situational', suggestedMinutes: 14, objective: 'Execute ground balls by game situation', equipment: 'Fungo bat, baseballs', coachingCues: 'Know the play before the ball is hit' },
  { id: 'dl38', name: 'Modified Infield/Outfield', category: 'Defense', suggestedMinutes: 12, objective: 'Mixed positional reads and transitions', equipment: 'Fungo bat, baseballs, cones', coachingCues: 'Communicate loudly and move early' },
  { id: 'dl39', name: 'Rolled Ball Drills', category: 'Defense', suggestedMinutes: 8, objective: 'Work first-step reads and glove presentation', equipment: 'Baseballs', coachingCues: 'Stay low, quiet glove, fast transfer' },
  { id: 'dl40', name: 'Hands Drills', category: 'Defense', suggestedMinutes: 8, objective: 'Improve hand softness and exchange speed', equipment: 'Short hop balls, gloves', coachingCues: 'Soft fingers, receive out front' },
  { id: 'dl41', name: 'Short Fungo Drill', category: 'Defense', suggestedMinutes: 10, objective: 'Quick infield reps at short distance', equipment: 'Fungo bat, baseballs', coachingCues: 'Fast feet, compact arm action' },
  { id: 'dl42', name: 'Mass Fungo (No Throw)', category: 'Defense', suggestedMinutes: 10, objective: 'Footwork and glove work without throws', equipment: 'Fungo bat, baseballs', coachingCues: 'Field through the ball, balance on finish' },
  { id: 'dl43', name: 'Routine Ground Balls w/ Baserunners', category: 'Situational', suggestedMinutes: 14, objective: 'Routine plays with live runner pressure', equipment: 'Fungo bat, baseballs, baserunners', coachingCues: 'Secure outs first, communicate priorities' },
  { id: 'dl44', name: 'High Tempo Ground Balls', category: 'Conditioning', suggestedMinutes: 10, objective: 'Conditioning via quick-rep infield work', equipment: 'Fungo bat, baseballs', coachingCues: 'Keep pace high without sacrificing fundamentals' },
  { id: 'dl45', name: 'Infield In Ground Balls', category: 'Situational', suggestedMinutes: 12, objective: 'Play in and throw home under control', equipment: 'Fungo bat, baseballs', coachingCues: 'Charge aggressively, get rid of it quickly' },
  { id: 'dl46', name: 'Middle Back Ground Balls', category: 'Defense', suggestedMinutes: 10, objective: 'Middle infield depth reads and pivots', equipment: 'Fungo bat, baseballs', coachingCues: 'Angle body to target and keep feet moving' },

  // Double Play / Infield Actions
  { id: 'dl47', name: 'Double Play Feeds', category: 'Defense', suggestedMinutes: 10, objective: 'Improve feed quality from all angles', equipment: 'Baseballs, bases', coachingCues: 'Accurate chest-high feeds with rhythm' },
  { id: 'dl48', name: 'Double Play Turns', category: 'Defense', suggestedMinutes: 10, objective: 'Footwork and release on turn at second', equipment: 'Baseballs, bases', coachingCues: 'Catch-shuffle-throw with quick feet' },
  { id: 'dl49', name: 'MIF DP Flips', category: 'Defense', suggestedMinutes: 8, objective: 'Middle infield flip timing and touch', equipment: 'Baseballs, bases', coachingCues: 'Soft flip, keep momentum through bag' },
  { id: 'dl50', name: '4-6 / 6-4 / 5-4 Double Play Work', category: 'Defense', suggestedMinutes: 12, objective: 'Patterned DP reps from game feeds', equipment: 'Baseballs, full infield', coachingCues: 'Anticipate feed path and clear throwing lane' },

  // Bunt / Small Ball
  { id: 'dl51', name: 'Bunt Mechanics / Fundamentals', category: 'Hitting', suggestedMinutes: 10, objective: 'Square early and deaden the ball', equipment: 'Bats, baseballs, tees', coachingCues: 'Top hand controls barrel angle and direction' },
  { id: 'dl52', name: 'Bunt Game', category: 'Game Sim', suggestedMinutes: 12, objective: 'Competitive bunt placement under pressure', equipment: 'Bats, baseballs, cone targets', coachingCues: 'Compete for quality placement, not power' },
  { id: 'dl53', name: 'Bunt Coverage Drill', category: 'Situational', suggestedMinutes: 12, objective: 'Defensive rotations and communication on bunts', equipment: 'Baseballs, full infield', coachingCues: 'Call coverage early and move decisively' },
  { id: 'dl54', name: 'Bunting in Bullpen', category: 'Hitting', suggestedMinutes: 8, objective: 'Controlled bunt reps in small-space setup', equipment: 'Bats, baseballs', coachingCues: 'Quiet body and soft barrel' },
  { id: 'dl55', name: 'Sac Bunt Drill', category: 'Situational', suggestedMinutes: 10, objective: 'Advance runner with consistent sac execution', equipment: 'Bats, baseballs', coachingCues: 'Angle ball to first-base side when possible' },
  { id: 'dl56', name: 'Push / Drag Bunt Drill', category: 'Hitting', suggestedMinutes: 12, objective: 'Develop push and drag bunt feel', equipment: 'Bats, baseballs, cone lanes', coachingCues: 'Control barrel and maintain running posture' },
  { id: 'dl57', name: 'Bunt & Crash Coverage', category: 'Situational', suggestedMinutes: 12, objective: 'Team responses to bunt and hard crash reads', equipment: 'Baseballs, full infield', coachingCues: 'Read angle fast, trust assigned coverage' },

  // Pickoffs / Rundowns
  { id: 'dl58', name: 'Pick Drill', category: 'Pitching', suggestedMinutes: 8, objective: 'Pitcher and infielder timing on picks', equipment: 'Baseballs, bases', coachingCues: 'Sell to home, quick feet and accurate throw' },
  { id: 'dl59', name: '3-Man Pick Drill', category: 'Situational', suggestedMinutes: 10, objective: 'Three-player timing for pick plays', equipment: 'Baseballs, bases', coachingCues: 'Synchronize break, tag, and backup responsibilities' },
  { id: 'dl60', name: 'Pick Series', category: 'Pitching', suggestedMinutes: 10, objective: 'Sequence multiple pick variations', equipment: 'Baseballs, bases', coachingCues: 'Vary looks and tempos while staying legal' },
  { id: 'dl61', name: 'Pickoff Rundown Drill', category: 'Situational', suggestedMinutes: 10, objective: 'Convert pick attempts into clean rundowns', equipment: 'Baseballs, bases', coachingCues: 'Throw early, run at runner, short exchanges' },
  { id: 'dl62', name: 'Rundown Drill', category: 'Situational', suggestedMinutes: 10, objective: 'Efficient tag execution in rundowns', equipment: 'Baseballs, bases', coachingCues: 'No extra throws, close distance before release' },

  // Pitchers / PFP
  { id: 'dl63', name: 'PFP (Pitcher Fielding Practice)', category: 'Pitching', suggestedMinutes: 14, objective: 'Pitcher defense on bunts, comebackers, coverages', equipment: 'Mound, baseballs, fungo', coachingCues: 'Athletic finish, get off mound quickly' },
  { id: 'dl64', name: '3-Man PFP', category: 'Pitching', suggestedMinutes: 12, objective: 'Three-man pitcher fielding sequences', equipment: 'Mound, baseballs', coachingCues: 'Communicate roles and execute cleanly' },
  { id: 'dl65', name: 'PFP Cover 1B', category: 'Pitching', suggestedMinutes: 10, objective: 'Pitcher footwork and timing covering first', equipment: 'Baseballs, first base', coachingCues: 'Break immediately and find inside corner of bag' },
  { id: 'dl66', name: 'Pitcher Pick Drill', category: 'Pitching', suggestedMinutes: 8, objective: 'Improve pitcher pickoff speed and deception', equipment: 'Baseballs, bases', coachingCues: 'Quick feet, compact move, accurate finish' },

  // Catcher Work
  { id: 'dl67', name: 'Receiving Drills', category: 'Defense', suggestedMinutes: 10, objective: 'Quiet glove presentation and framing consistency', equipment: 'Catcher gear, baseballs', coachingCues: 'Beat ball to spot, present subtly' },
  { id: 'dl68', name: 'Blocking Drills', category: 'Defense', suggestedMinutes: 10, objective: 'Lateral and forward block technique', equipment: 'Catcher gear, baseballs', coachingCues: 'Chest over ball, recover to throw quickly' },
  { id: 'dl69', name: 'Catch & Tag Drill', category: 'Defense', suggestedMinutes: 10, objective: 'Tag mechanics at plate with receiving transfer', equipment: 'Catcher gear, plate area, baseballs', coachingCues: 'Receive first, quick swipe with secure possession' },
  { id: 'dl70', name: 'Throws to Bases', category: 'Defense', suggestedMinutes: 10, objective: 'Catcher pop and carry to each base', equipment: 'Catcher gear, baseballs', coachingCues: 'Fast transfer, direct line to target' },
  { id: 'dl71', name: 'Snap Throws', category: 'Defense', suggestedMinutes: 8, objective: 'Quick catcher snap throws to pick runners', equipment: 'Catcher gear, baseballs', coachingCues: 'Explosive feet and short accurate arm path' },

  // Outfield Work
  { id: 'dl72', name: 'Long Hop Throws', category: 'Defense', suggestedMinutes: 10, objective: 'Outfield long-hop reads and accurate throws', equipment: 'Baseballs, outfield space', coachingCues: 'Stay behind hop and throw through cutoff' },
  { id: 'dl73', name: 'Flyball Work (Hack Attack)', category: 'Defense', suggestedMinutes: 12, objective: 'Machine flyball reads and routes', equipment: 'Hack Attack machine, baseballs', coachingCues: 'Read spin early, first step is critical' },
  { id: 'dl74', name: 'Machine Flyballs', category: 'Defense', suggestedMinutes: 12, objective: 'Consistent high-volume fly ball reps', equipment: 'Flyball machine, baseballs', coachingCues: 'Create angle quickly and catch with momentum' },
  { id: 'dl75', name: 'Flyball Communication Drill', category: 'Defense', suggestedMinutes: 10, objective: 'Call priority and avoid collisions', equipment: 'Baseballs, full outfield', coachingCues: 'Loud early calls and clear right-of-way' },
  { id: 'dl76', name: 'Corner Throws', category: 'Defense', suggestedMinutes: 10, objective: 'Corner outfield throw decisions and execution', equipment: 'Baseballs, outfield corners', coachingCues: 'Read ball off wall and align feet quickly' },
  { id: 'dl77', name: 'Relay Throws', category: 'Situational', suggestedMinutes: 10, objective: 'Outfield-to-infield relay chain timing', equipment: 'Baseballs, cones', coachingCues: 'Hit cutoff chest-high with momentum' },
  { id: 'dl78', name: 'Throwing from Gap', category: 'Defense', suggestedMinutes: 10, objective: 'Gap ball recovery and throw accuracy', equipment: 'Baseballs, full outfield', coachingCues: 'Efficient route and aggressive crow hop' },

  // Defensive Situations / Team Defense
  { id: 'dl79', name: 'Defensive Situation Drill', category: 'Situational', suggestedMinutes: 14, objective: 'Execute team defense by game context', equipment: 'Full field, baseballs', coachingCues: 'Anticipate next play before pitch' },
  { id: 'dl80', name: 'Runner at 1B Situations', category: 'Situational', suggestedMinutes: 12, objective: 'Defensive priorities with runner on first', equipment: 'Full field, baseballs', coachingCues: 'Know force options and cutoff alignment' },
  { id: 'dl81', name: 'Runner at 1B & 2B Situations', category: 'Situational', suggestedMinutes: 12, objective: 'Defense with two runners and limited margins', equipment: 'Full field, baseballs', coachingCues: 'Communicate responsibilities before each rep' },
  { id: 'dl82', name: 'Bases Loaded Situations', category: 'Situational', suggestedMinutes: 12, objective: 'Pressure defense with bases loaded', equipment: 'Full field, baseballs', coachingCues: 'Prioritize clean execution and sure outs' },
  { id: 'dl83', name: '1st & 3rd Situations', category: 'Situational', suggestedMinutes: 12, objective: 'Control running game in 1st-and-3rd scenarios', equipment: 'Full field, baseballs', coachingCues: 'Stay calm, communicate fake/throw decisions' },
  { id: 'dl84', name: 'Wheel Play', category: 'Situational', suggestedMinutes: 10, objective: 'Corner-infield wheel action on bunt threats', equipment: 'Full infield, baseballs', coachingCues: 'Explode on cue and trust role assignments' },
  { id: 'dl85', name: '12 Outs Drill', category: 'Game Sim', suggestedMinutes: 14, objective: 'String together clean defensive outs under pressure', equipment: 'Full field, baseballs', coachingCues: 'One pitch at a time, no mental reset lapses' },
  { id: 'dl86', name: '18 Play Drill', category: 'Game Sim', suggestedMinutes: 16, objective: 'Execute a scripted set of defensive plays', equipment: 'Full field, baseballs', coachingCues: 'Treat every rep as inning-defining' },
  { id: 'dl87', name: 'Live Defensive Scrimmage w/ Baserunners', category: 'Game Sim', suggestedMinutes: 20, objective: 'Full-speed defense with active baserunning pressure', equipment: 'Full field, baseballs, baserunners', coachingCues: 'Game pace communication and decision making' },

  // Base Running
  { id: 'dl88', name: 'Baserunning Drill', category: 'Conditioning', suggestedMinutes: 10, objective: 'General baserunning technique and turns', equipment: 'Bases, cones', coachingCues: 'Hit corners aggressively with controlled body lean' },
  { id: 'dl89', name: 'Steal Work', category: 'Situational', suggestedMinutes: 10, objective: 'Leads, jumps, and steal timing', equipment: 'Bases, stopwatch', coachingCues: 'Read pitcher cues and explode on first move' },
  { id: 'dl90', name: '1st to 3rd Drill', category: 'Situational', suggestedMinutes: 10, objective: 'Decision making and turns from first to third', equipment: 'Full field, baseballs', coachingCues: 'Read ball angle early and commit decisively' },
  { id: 'dl91', name: 'Tag Plays', category: 'Situational', suggestedMinutes: 8, objective: 'Advance on tag and retreat mechanics', equipment: 'Bases, baseballs', coachingCues: 'Time shuffle and break on contact cues' },
  { id: 'dl92', name: 'Reads & Leads Drill', category: 'Situational', suggestedMinutes: 10, objective: 'Improve primary/secondary leads and reads', equipment: 'Bases, baseballs', coachingCues: 'Consistent lead length and reactive first step' },

  // Competitive / Game-Based
  { id: 'dl93', name: 'Coach BP Scrimmage', category: 'Game Sim', suggestedMinutes: 18, objective: 'Live team reps off coach BP', equipment: 'Full field, balls, L-screen', coachingCues: 'Compete every pitch with game decisions' },
  { id: 'dl94', name: 'Live Arm Scrimmage', category: 'Game Sim', suggestedMinutes: 20, objective: 'Scrimmage against live pitching', equipment: 'Full field, baseballs', coachingCues: 'Approach each AB with a game plan' },
  { id: 'dl95', name: 'Controlled BP', category: 'Hitting', suggestedMinutes: 15, objective: 'Targeted swing work with controlled intent', equipment: 'Cage/field BP setup', coachingCues: 'Quality contacts to assigned zones' },
  { id: 'dl96', name: 'Directional BP', category: 'Hitting', suggestedMinutes: 15, objective: 'Train opposite-field and pull-side execution', equipment: 'BP setup, zone markers', coachingCues: 'Stay through ball path to target area' },
  { id: 'dl97', name: 'Situational Scrimmage', category: 'Game Sim', suggestedMinutes: 20, objective: 'Scrimmage with scripted game situations', equipment: 'Full field, baseballs', coachingCues: 'Communicate count, outs, and runner goals each rep' },
  { id: 'dl98', name: 'Live AB’s', category: 'Game Sim', suggestedMinutes: 20, objective: 'Competitive live at-bats for hitters/pitchers', equipment: 'Mound, catcher, hitters', coachingCues: 'Compete with intent and track quality of pitch decisions' },

  // Misc / Specialty
  { id: 'dl99', name: 'Quick Hands Drill', category: 'Defense', suggestedMinutes: 8, objective: 'Speed up glove-to-throw transitions', equipment: 'Short hop balls, gloves', coachingCues: 'Fast feet and quiet upper body' },
  { id: 'dl100', name: 'Short Hop Drill', category: 'Defense', suggestedMinutes: 8, objective: 'Improve short-hop receiving confidence', equipment: 'Baseballs, gloves', coachingCues: 'See ball deep and funnel smoothly' },
  { id: 'dl101', name: 'Four Corner Drill', category: 'Conditioning', suggestedMinutes: 10, objective: 'Footwork and movement pattern conditioning', equipment: '4 cones, baseballs optional', coachingCues: 'Stay low, quick directional changes' },
  { id: 'dl102', name: 'Relay Hands & Feet Drill', category: 'Defense', suggestedMinutes: 10, objective: 'Coordinate relay footwork with clean exchanges', equipment: 'Baseballs, cones', coachingCues: 'Feet first, exchange smooth, throw on line' },
  { id: 'dl103', name: 'Long Toss / Bullpens', category: 'Pitching', suggestedMinutes: 20, objective: 'Arm build-up into bullpen command work', equipment: 'Baseballs, mound, catcher', coachingCues: 'Build intent gradually, finish with command focus' },
  { id: 'dl104', name: 'Driveline Work', category: 'Pitching', suggestedMinutes: 15, objective: 'Arm care and velocity development progression', equipment: 'Weighted balls, bands, plyo setup', coachingCues: 'Prioritize safe mechanics and recovery protocol' },
];
