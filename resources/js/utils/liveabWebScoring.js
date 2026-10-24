import {
  PLAY_RESULTS,
  normalizePlayResult,
  doesThirdOutCancelRun,
} from "./liveabScoringLogic";
export const clone = (value) => JSON.parse(JSON.stringify(value));
export const resultLabels = {
  SINGLE: "Single",
  DOUBLE: "Double",
  TRIPLE: "Triple",
  HR: "Home run",
  GROUND_OUT: "Ground out",
  FLY_OUT: "Fly out",
  LINE_OUT: "Line out",
  POP_OUT: "Pop out",
  FOUL_OUT: "Foul out",
  ERROR: "Reached on error",
  FC: "Fielder’s choice",
  DOUBLE_PLAY: "Double play",
  TRIPLE_PLAY: "Triple play",
  SAC_FLY: "Sacrifice fly",
  SAC_BUNT: "Sacrifice bunt",
  IBB: "Intentional walk",
  HBP: "Hit by pitch",
  CI: "Catcher interference",
  DROPPED_THIRD_STRIKE_SAFE: "Dropped third strike — safe",
  DROPPED_THIRD_STRIKE_OUT: "Dropped third strike — out",
  BUNT_SINGLE: "Bunt single",
  BUNT_FOUL_STRIKE_THREE: "Bunt foul strike three",
  BATTER_INTERFERENCE: "Batter interference",
  RUNNER_INTERFERENCE: "Runner interference",
  INFIELD_FLY: "Infield fly",
  SAFE_MANUAL: "Safe",
  OUT: "Out",
};
export const totals = (state) => ({
  away: state.scores
    .filter((s) => s.half === "top")
    .reduce((n, s) => n + s.runs, 0),
  home: state.scores
    .filter((s) => s.half === "bottom")
    .reduce((n, s) => n + s.runs, 0),
});
export const battingSide = (state) =>
  state.half === "bottom" ? "home" : "away";
export const pitchingSide = (state) =>
  state.half === "bottom" ? "away" : "home";
export function countAfter(state, zone, trajectory) {
  let balls = state.balls,
    strikes = state.strikes;
  if (trajectory === "HBP") return { balls, strikes, result: "HBP" };
  if (trajectory === "F") {
    strikes = Math.min(2, strikes + 1);
  } else if (trajectory === "SM" || zone === "S") strikes++;
  else balls++;
  return {
    balls,
    strikes,
    result:
      strikes >= 3
        ? trajectory === "SM"
          ? "STRIKEOUT_SWINGING"
          : "STRIKEOUT_LOOKING"
        : balls >= 4
          ? "WALK"
          : "",
  };
}
export function defaultMovements(state, batter, result) {
  result = normalizePlayResult(result);
  const bases = { SINGLE: 1, BUNT_SINGLE: 1, DOUBLE: 2, TRIPLE: 3, HR: 4 }[
    result
  ];
  const award = ["WALK", "IBB", "HBP", "CI"].includes(result);
  const movements = state.runners
    .map((id, i) =>
      id ? { id, from: i + 1, to: Math.min(4, i + 1 + (bases || 0)) } : null,
    )
    .filter(Boolean);
  if (award) {
    for (const m of movements)
      m.to =
        m.from === 1
          ? 2
          : m.from === 2 && state.runners[0]
            ? 3
            : m.from === 3 && state.runners[0] && state.runners[1]
              ? 4
              : m.from;
  }
  if (result === "SAC_FLY")
    for (const m of movements) if (m.from === 3) m.to = 4;
  if (["DOUBLE_PLAY", "TRIPLE_PLAY"].includes(result)) {
    let remaining = result === "DOUBLE_PLAY" ? 1 : 2;
    for (const m of movements) {
      if (remaining) {
        m.to = 0;
        remaining--;
      }
    }
  }
  const safe = PLAY_RESULTS.SAFE.includes(result);
  if (result)
    movements.unshift({ id: batter, from: 0, to: safe ? bases || 1 : 0 });
  return movements;
}
export function resolveMovements(movements, outsBefore = 0, thirdOut = "none") {
  const occupied = new Set(),
    runners = [null, null, null];
  let outs = 0,
    runs = 0;
  for (const m of movements) {
    const to = Number(m.to);
    if (!Number.isInteger(to) || to < 0 || to > 4)
      throw Error("Choose a destination for every runner.");
    if (to === 0) outs++;
    else if (to === 4) runs++;
    else {
      if (occupied.has(to))
        throw Error("Two runners cannot occupy the same base.");
      occupied.add(to);
      runners[to - 1] = m.id;
    }
  }
  if (outsBefore + outs > 3)
    throw Error("This play records more than three outs.");
  if (
    outsBefore + outs === 3 &&
    doesThirdOutCancelRun({ thirdOutType: thirdOut })
  )
    runs = 0;
  return { runners, outs, runs };
}
function scoreRuns(state, runs) {
  let entry = state.scores.find(
    (s) => s.inning === state.inning && s.half === state.half,
  );
  if (!entry) {
    entry = { inning: state.inning, half: state.half, runs: 0 };
    state.scores.push(entry);
  }
  entry.runs += runs;
}
export function advanceHalf(state, config) {
  const next = clone(state);
  scoreRuns(next, 0);
  const score = totals(next);
  if (
    !config.unlimited &&
    next.inning >= config.innings &&
    ((next.half === "top" && score.home > score.away) ||
      (next.half === "bottom" && score.home !== score.away))
  ) {
    next.finished = true;
  } else if (next.half === "top") next.half = "bottom";
  else {
    next.half = "top";
    next.inning++;
  }
  if (next.balls || next.strikes) next.turn++;
  next.outs = 0;
  next.balls = 0;
  next.strikes = 0;
  next.runners = [null, null, null];
  return next;
}
export function scorePitch(
  state,
  config,
  {
    zone,
    trajectory,
    result = "",
    movements = [],
    thirdOut = "none",
    rbi = null,
  },
) {
  let next = clone(state);
  const inPlay = ["GB", "LD", "FB", "PF"].includes(trajectory);
  const count = inPlay
    ? { balls: state.balls, strikes: state.strikes, result: "" }
    : countAfter(state, zone, trajectory);
  result = normalizePlayResult(result || count.result);
  if (inPlay && !result) throw Error("Choose the ball-in-play result.");
  const ended = !!result,
    action = ended
      ? resolveMovements(movements, state.outs, thirdOut)
      : { runners: [...state.runners], outs: 0, runs: 0 };
  next.pitches++;
  next.balls = ended ? 0 : count.balls;
  next.strikes = ended ? 0 : count.strikes;
  if (ended) {
    next.turn++;
    const side = battingSide(state),
      lineup = config[side].hitters;
    next.hitterIndex[side] =
      (next.hitterIndex[side] + 1) % Math.max(1, lineup.length);
    next.runners = action.runners;
    next.outs += action.outs;
    scoreRuns(next, action.runs);
  }
  const runs = totals(next);
  if (
    config.mode === "game" &&
    !config.unlimited &&
    next.half === "bottom" &&
    next.inning >= config.innings &&
    runs.home > runs.away
  )
    next.finished = true;
  if (next.outs >= 3) {
    if (config.mode === "game" && !config.continuous && !next.finished)
      next = advanceHalf(next, config);
    else {
      next.outs = 0;
      next.runners = [null, null, null];
    }
  }
  const blocked = ["ERROR", "DOUBLE_PLAY", "TRIPLE_PLAY"].includes(result);
  const credited = rbi === null ? (blocked ? 0 : action.runs) : Number(rbi);
  if (!Number.isInteger(credited) || credited < 0 || credited > action.runs)
    throw Error("RBI must be between zero and the number of runs scored.");
  return {
    state: next,
    play: {
      play_result: result || null,
      outs_recorded: action.outs,
      runs_scored: action.runs,
      rbi: credited,
      is_safe: PLAY_RESULTS.SAFE.includes(result),
      sac_fly: result === "SAC_FLY",
      sac_bunt: result === "SAC_BUNT",
      runners_before: state.runners,
      runners_after: action.runners,
      bases:
        {
          SINGLE: 1,
          BUNT_SINGLE: 1,
          DOUBLE: 2,
          TRIPLE: 3,
          HR: 5,
          WALK: 4,
          IBB: 4,
          HBP: 6,
          STRIKEOUT_SWINGING: 7,
          STRIKEOUT_LOOKING: 7,
          DROPPED_THIRD_STRIKE_OUT: 7,
          DROPPED_THIRD_STRIKE_SAFE: 7,
          BUNT_FOUL_STRIKE_THREE: 7,
        }[result] || (result ? 8 : 0),
    },
  };
}
export function adjustRunners(state, config, movements, reason) {
  let next = clone(state);
  const action = resolveMovements(movements, state.outs);
  next.runners = action.runners;
  next.outs += action.outs;
  scoreRuns(next, action.runs);
  if (next.outs >= 3)
    next =
      config.mode === "game" && !config.continuous
        ? advanceHalf(next, config)
        : {
            ...next,
            outs: 0,
            runners: [null, null, null],
            balls: 0,
            strikes: 0,
          };
  const score = totals(next);
  if (
    config.mode === "game" &&
    !config.unlimited &&
    state.half === "bottom" &&
    state.inning >= config.innings &&
    score.home > score.away
  )
    next.finished = true;
  return next;
}
