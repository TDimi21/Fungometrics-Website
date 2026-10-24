import {
  deriveBatterOutcomeFlags,
  computeRateStats,
} from "./liveabScoringLogic";
export function playResult(row) {
  if (row.play_result) return row.play_result;
  return (
    {
      1: "SINGLE",
      2: "DOUBLE",
      3: "TRIPLE",
      4: "WALK",
      5: "HR",
      6: "HBP",
      7: "STRIKEOUT_SWINGING",
      8: "OUT",
    }[Number(row.bases)] || ""
  );
}
export function liveabBoxScore(rows = [], adjustments = [], config = null) {
  const hitters = new Map(),
    pitchers = new Map();
  const name = (p) =>
    [p?.first_name, p?.last_name].filter(Boolean).join(" ") || "Player";
  for (const row of rows) {
    const b = row.batting || {},
      p = row.pitching || {},
      result = playResult(row);
    if (!hitters.has(b.batter_id))
      hitters.set(b.batter_id, {
        id: b.batter_id,
        name: name(b.profile),
        PA: 0,
        AB: 0,
        H: 0,
        single: 0,
        double: 0,
        triple: 0,
        homeRun: 0,
        BB: 0,
        HBP: 0,
        SO: 0,
        SF: 0,
        SH: 0,
        CI: 0,
        RBI: 0,
      });
    if (!pitchers.has(p.pitcher_id))
      pitchers.set(p.pitcher_id, {
        id: p.pitcher_id,
        name: name(p.profile),
        pitches: 0,
        strikes: 0,
        outs: 0,
        H: 0,
        BB: 0,
        SO: 0,
        R: 0,
        speeds: [],
      });
    const hitter = hitters.get(b.batter_id),
      pitcher = pitchers.get(p.pitcher_id);
    pitcher.pitches++;
    if (row.is_strike) pitcher.strikes++;
    if (Number(p.miles_per_hour) > 0)
      pitcher.speeds.push(Number(p.miles_per_hour));
    if (result) {
      const flags = deriveBatterOutcomeFlags(result);
      for (const k of [
        "PA",
        "AB",
        "H",
        "single",
        "double",
        "triple",
        "homeRun",
        "BB",
        "HBP",
        "SO",
        "SF",
        "SH",
      ])
        hitter[k] += flags[k];
      if (result === "CI") hitter.CI++;
      hitter.RBI += Number(row.rbi || 0);
      pitcher.H += flags.H;
      pitcher.BB += flags.BB;
      pitcher.SO += flags.SO;
      pitcher.R += Number(row.runs_scored || 0);
      pitcher.outs += Number(row.outs_recorded || 0);
    }
  }
  for (const event of adjustments) {
    if (
      ![
        "stolen_base",
        "wild_pitch",
        "passed_ball",
        "balk",
        "pickoff",
        "caught_stealing",
        "manual_advance",
      ].includes(event.action)
    )
      continue;
    const before =
      typeof event.before_state === "string"
        ? JSON.parse(event.before_state)
        : event.before_state;
    const after =
      typeof event.after_state === "string"
        ? JSON.parse(event.after_state)
        : event.after_state;
    if (!before || !after || !config) continue;
    const side = before.half === "top" ? "home" : "away";
    const id = config[side]?.pitchers[before.pitcherIndex[side]];
    const pitcher = pitchers.get(id);
    if (!pitcher) continue;
    const sum = (state) => state.scores.reduce((n, s) => n + s.runs, 0);
    pitcher.R += Math.max(0, sum(after) - sum(before));
    pitcher.outs +=
      after.half !== before.half ||
      after.inning !== before.inning ||
      after.outs < before.outs
        ? 3 - before.outs
        : Math.max(0, after.outs - before.outs);
  }
  return {
    hitters: [...hitters.values()].map((h) => ({
      ...h,
      ...computeRateStats(h),
    })),
    pitchers: [...pitchers.values()].map((p) => ({
      ...p,
      IP: `${Math.floor(p.outs / 3)}.${p.outs % 3}`,
      strikePct: p.pitches ? Math.round((p.strikes / p.pitches) * 100) : 0,
      avgVelo: p.speeds.length
        ? (p.speeds.reduce((a, b) => a + b, 0) / p.speeds.length).toFixed(1)
        : "—",
    })),
  };
}
