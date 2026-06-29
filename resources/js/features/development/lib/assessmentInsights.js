/**
 * FMTRX Assessment Insight Engine
 *
 * Deterministic "AI" development insights generated from a player's assessment
 * scores. No external API: every output is derived from the structured data so
 * it is instant, free and offline. Coaches can edit the generated text before
 * sharing/exporting.
 *
 * Rules honoured throughout:
 *  - Missing data is never scored as zero — null sections are "Not Tested" and
 *    are excluded from strengths/limiters/averages.
 *  - Language stays simple (parent-friendly) but useful for coaches.
 *  - No medical claims. If arm pain is reported we advise reducing throwing
 *    workload and consulting a qualified medical professional.
 */

const num = (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};

const parseData = (value) => {
  if (!value) return {};
  if (typeof value === 'object') return value;
  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
};

// Section metadata: label + the practice/drill bucket each maps to.
export const SECTION_META = {
  athletic: { label: 'Athleticism', drill: 'Speed & Explosiveness' },
  throwing: { label: 'Throwing / Arm', drill: 'Arm Care & Throwing Mechanics' },
  hitting: { label: 'Hitting', drill: 'Hitting Mechanics & Bat Speed' },
  mobility: { label: 'Mobility', drill: 'Mobility & Movement Prep' },
  armHealth: { label: 'Arm Health', drill: 'Arm Care & Recovery' },
  strength: { label: 'Strength', drill: 'Strength Training' },
};

const SECTION_ORDER = ['athletic', 'throwing', 'hitting', 'mobility', 'armHealth', 'strength'];

const statusFor = (s) => {
  if (s == null) return 'Not Tested';
  if (s >= 80) return 'Excellent';
  if (s >= 70) return 'Good';
  if (s >= 60) return 'Fair';
  return 'Needs Work';
};

/**
 * Pull the comparable section scores from a saved assessment record. Anything
 * not collected stays null (never 0) so it is excluded downstream.
 */
export function extractScores(report = {}) {
  const throwingScore = report.arm_health_score != null || report.throwing_workload_score != null
    ? Math.round(((num(report.arm_health_score) ?? 0) + (100 - (num(report.throwing_workload_score) ?? 0)))
        / (report.arm_health_score != null && report.throwing_workload_score != null ? 2 : 1))
    : null;

  return {
    overall: num(report.overall_score),
    athletic: num(report.strength_explosive_score ?? report.strength_overall_score),
    throwing: throwingScore,
    hitting: num(report.hitting_score),
    mobility: num(report.mobility_overall_score),
    armHealth: num(report.arm_health_score),
    strength: num(report.strength_overall_score),
    // finer signals used for classification
    lowerBody: num(report.strength_lower_body_score),
    upperBody: num(report.strength_upper_body_score),
    explosive: num(report.strength_explosive_score),
    rotational: num(report.strength_rotational_score),
    pitching: num(report.pitching_score),
    workload: num(report.throwing_workload_score),
    hipMobility: num(report.hip_mobility),
    ankleMobility: num(report.ankle_mobility),
  };
}

const mechanics = (report, key) => parseData(report[`${key}_data`]).mechanics || {};

/** Ordered list of available sections with their scores (nulls dropped). */
function rankedSections(scores) {
  return SECTION_ORDER
    .map((key) => ({ key, label: SECTION_META[key].label, drill: SECTION_META[key].drill, score: scores[key] }))
    .filter((s) => s.score != null);
}

/**
 * Classify the athlete into one of the FMTRX player types. Rules are evaluated
 * in priority order; the first match wins.
 */
export function classifyPlayerType(scores, report = {}) {
  const { mobility, strength, hitting, pitching, armHealth, workload, lowerBody, rotational } = scores;
  const hitMech = mechanics(report, 'hitting');
  const pitMech = mechanics(report, 'pitching');
  const low = (v, t = 60) => v != null && v < t;
  const high = (v, t = 78) => v != null && v >= t;

  const timingWeak = low(num(hitMech.timing), 3) || low(num(pitMech.tempo), 3);
  const lowerHalfWeak = low(num(hitMech.lower_half), 3) || low(num(pitMech.lower_half), 3) || low(lowerBody);
  const frontLegWeak = low(num(pitMech.front_leg), 3);

  const def = (type, body) => ({ type, body });

  if (high(pitching, 80) && high(hitting, 72)) return def('Two-Way Athlete', 'Both the bat and the arm project as real strengths — protect workload while developing both.');
  if (low(mobility)) return def('Mobility Limited', 'Mobility is limiting positions, sequencing and power transfer. Unlocking it should raise several scores at once.');
  if (high(hitting, 78) && (low(strength, 72) || lowerHalfWeak)) return def('Power Leaker', 'The bat shows tools, but strength or lower-half transfer is leaking power before contact.');
  if ((frontLegWeak || lowerHalfWeak) && (high(pitching, 65) || high(hitting, 65))) return def('Lower-Half Limited', 'Upper body is doing too much of the work — building lower-half drive and front-leg stability will add efficiency.');
  if (timingWeak) return def('Timing Limited', 'Raw tools are there, but timing/sequencing is costing consistency and on-time contact.');
  if (high(workload, 70) || (high(pitching, 65) && low(strength))) return def('Arm Dominant', 'Throwing load is elevated and arm-driven. Manage volume while improving movement quality.');
  if (high(pitching, 80) && high(armHealth, 75)) return def('Command Pitcher', 'Pitching profile is ahead and durable — keep building with targeted, controlled workload.');
  if (high(hitting, 80)) return def('Power Hitter', 'Impact bat profile. Support it with efficient movement so the power keeps showing up in games.');
  if (low(strength)) return def('Strength Limited', 'Adding usable strength should unlock better force production across the board.');
  if ((scores.pitching != null && low(scores.pitching, 60)) && high(mobility, 70) && high(strength, 65)) return def('Velocity Project', 'The athletic base is there to build velocity — prioritise arm speed and sequencing.');
  if (high(rotational, 72) && high(mobility, 70)) return def('Efficient Mover', 'Moves and sequences well. Layer on strength and intent to turn efficiency into output.');
  return def('Athletic Mover', 'A balanced profile with clear, trainable next steps.');
}

/** 3–5 strengths from the highest available section scores. */
export function buildStrengths(scores) {
  return rankedSections(scores)
    .sort((a, b) => b.score - a.score)
    .filter((s) => s.score >= 65)
    .slice(0, 5)
    .map((s) => `${s.label}: ${statusFor(s.score)} (${s.score})`);
}

/** 3–5 limiters from the lowest available section scores (non-overlapping with strengths). */
export function buildLimiters(scores) {
  return rankedSections(scores)
    .sort((a, b) => a.score - b.score)
    .filter((s) => s.score < 65)
    .slice(0, 5)
    .map((s) => `${s.label}: ${statusFor(s.score)} (${s.score})`);
}

const focusReason = (key, score) => {
  const map = {
    mobility: 'Restricted mobility is limiting positions and power transfer — daily prep should open this up.',
    strength: 'Building usable strength will raise force production and protect against late-game fatigue.',
    athletic: 'Improving speed and explosiveness will show up in jumps, sprints and on-field quickness.',
    throwing: 'Cleaning up throwing mechanics and managing load will support velocity and durability.',
    armHealth: 'Recovery and arm-care habits need attention to keep the arm fresh and available.',
    hitting: 'Tightening up swing mechanics and bat speed will turn tools into consistent contact.',
  };
  return map[key] || 'Targeted work here should raise the overall profile.';
};

/** Primary / Secondary / Tertiary focus from the lowest available sections. */
export function buildFocusAreas(scores) {
  const lowest = rankedSections(scores).sort((a, b) => a.score - b.score).slice(0, 3);
  const tiers = ['primary', 'secondary', 'tertiary'];
  const out = {};
  lowest.forEach((s, i) => {
    out[tiers[i]] = {
      title: s.label,
      reason: focusReason(s.key, s.score),
      drillCategory: s.drill,
      score: s.score,
    };
  });
  return out;
}

const addDays = (dateStr, days) => {
  // Parse YYYY-MM-DD as a LOCAL date so the result isn't a day early in negative
  // timezones (new Date('YYYY-MM-DD') would parse as UTC midnight).
  const m = /^(\d{4})-(\d{1,2})-(\d{1,2})/.exec(String(dateStr || ''));
  const base = m ? new Date(+m[1], +m[2] - 1, +m[3]) : (dateStr ? new Date(dateStr) : new Date());
  if (Number.isNaN(base.getTime())) return '';
  const d = new Date(base.getFullYear(), base.getMonth(), base.getDate() + days);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

/** 30-day development plan derived from the focus areas. */
export function buildPlan(report, scores, focus) {
  const primary = focus.primary;
  const priorities = ['primary', 'secondary', 'tertiary']
    .map((t) => focus[t])
    .filter(Boolean)
    .map((f) => `${f.title} — ${f.drillCategory}`);

  return {
    goal: primary
      ? `Move ${primary.title.toLowerCase()} from "${statusFor(primary.score)}" toward the next level while holding current strengths.`
      : 'Maintain strengths and keep building a balanced development base.',
    priorities: priorities.length ? priorities : ['Maintain current strengths', 'Stay consistent with movement prep', 'Track session quality'],
    measure: `Re-run the FMTRX assessment and compare overall score${primary ? ` and ${primary.title} score` : ''}.`,
    retestDate: addDays(report.assessment_date, 30),
  };
}

/** One-paragraph, parent-friendly player summary. */
export function buildSummary(scores, type, strengths, limiters, focus) {
  const topStrength = strengths[0] ? strengths[0].split(':')[0] : null;
  const topLimiter = limiters[0] ? limiters[0].split(':')[0] : null;
  const bestFocus = focus.primary ? focus.primary.title : null;
  const parts = [`This player profiles as ${type.type}. ${type.body}`];
  if (topStrength) parts.push(`Their biggest strength right now is ${topStrength.toLowerCase()}.`);
  if (topLimiter && topLimiter !== topStrength) parts.push(`The biggest limiter is ${topLimiter.toLowerCase()}.`);
  if (bestFocus) parts.push(`The best place to develop next is ${bestFocus.toLowerCase()}.`);
  return parts.join(' ');
}

/** Arm-pain advisory (no medical claims), or null. */
export function buildArmAdvisory(report) {
  const throwing = parseData(report.throwing_workload_data);
  const pain = String(throwing.arm_pain || '').toLowerCase();
  if (pain === 'yes' || pain === 'true') {
    return 'Arm pain was reported on this assessment. Reduce throwing workload and consult a qualified medical professional before increasing volume or intensity. This is not a medical diagnosis.';
  }
  return null;
}

/** Full bundle of generated insights for a single assessment. */
export function buildPlayerInsights(report = {}) {
  const scores = extractScores(report);
  const type = classifyPlayerType(scores, report);
  const strengths = buildStrengths(scores);
  const limiters = buildLimiters(scores);
  const focus = buildFocusAreas(scores);
  const plan = buildPlan(report, scores, focus);
  const summary = buildSummary(scores, type, strengths, limiters, focus);
  const armAdvisory = buildArmAdvisory(report);
  return { scores, type, summary, strengths, limiters, focus, plan, armAdvisory };
}

/**
 * Team-level practice recommendation from the latest assessment per player.
 * Averages only sections that were actually tested.
 */
export function buildTeamInsight(reports = []) {
  if (!Array.isArray(reports) || !reports.length) return null;

  // Latest assessment per player.
  const latest = new Map();
  for (const r of reports) {
    const pid = r.user_id ?? r.id;
    const prev = latest.get(pid);
    if (!prev || new Date(r.assessment_date || 0) > new Date(prev.assessment_date || 0)) latest.set(pid, r);
  }
  const rosters = [...latest.values()].map(extractScores);

  const avg = {};
  for (const key of SECTION_ORDER) {
    const vals = rosters.map((s) => s[key]).filter((v) => v != null);
    avg[key] = vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length) : null;
  }
  const overallVals = rosters.map((s) => s.overall).filter((v) => v != null);
  const teamOverall = overallVals.length ? Math.round(overallVals.reduce((a, b) => a + b, 0) / overallVals.length) : null;

  const ranked = SECTION_ORDER
    .map((key) => ({ key, label: SECTION_META[key].label, drill: SECTION_META[key].drill, score: avg[key] }))
    .filter((s) => s.score != null)
    .sort((a, b) => a.score - b.score);

  if (!ranked.length) return null;
  const weakest = ranked[0];

  // Mobility-specific phrasing when hip mobility is the weak link.
  const hipVals = rosters.map((s) => s.hipMobility).filter((v) => v != null);
  const hipAvg = hipVals.length ? hipVals.reduce((a, b) => a + b, 0) / hipVals.length : null;
  const mobilityHipPhrase = weakest.key === 'mobility' && hipAvg != null && hipAvg < 6
    ? 'hip mobility and front-leg stability are limiting velocity'
    : `${weakest.label.toLowerCase()} is the team's biggest limiter`;

  const health = teamOverall == null ? 'is developing'
    : teamOverall >= 70 ? 'is healthy'
      : teamOverall >= 55 ? 'is progressing' : 'has a developing base';

  return {
    teamOverall,
    weakest,
    averages: avg,
    sentence: `Your team ${health}, but ${mobilityHipPhrase}. Spend today's first 20 minutes on ${weakest.drill.toLowerCase()}.`,
  };
}

export { statusFor };
