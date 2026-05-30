export function buildRecommendations({ scores = {}, trend = {}, mobility = {} } = {}) {
  const items = [];
  const add = (priority, category, title, recommendation, reason) => {
    items.push({ priority, category, title, recommendation, reason, completed: false, date: new Date().toISOString().slice(0, 10) });
  };

  if ((scores.recoveryScore ?? 100) < 65) {
    add(
      'high',
      'recovery',
      'Raise sleep + recovery baseline',
      'Set 7.5+ hour sleep goal for the next 14 days. Add hydration check and post-session cooldown.',
      'Recovery score is currently limiting performance outputs.'
    );
  }

  if ((scores.mobilityScore ?? 100) < 65 || (mobility.asymmetries?.shoulder_ir_diff ?? 0) >= 6) {
    add(
      'high',
      'mobility',
      'Restore shoulder and hip symmetry',
      'Add shoulder IR and hip IR mobility blocks (8-12 minutes) before throwing days.',
      'Mobility trend and asymmetry suggest movement restrictions.'
    );
  }

  if ((trend.changes?.swing_miss_percentage?.direction ?? 'flat') === 'up') {
    add(
      'medium',
      'performance',
      'Reduce two-strike swing/miss',
      'Prioritize short-bat and two-strike contact rounds in next 3 BP sessions.',
      'Swing/miss trend is increasing over the last 30 days.'
    );
  }

  if ((scores.strengthScore ?? 100) < 70) {
    add(
      'medium',
      'strength',
      'Build lower-body force output',
      'Focus on trap bar and squat progression with rotational med-ball pairing twice weekly.',
      'Strength score is lagging behind performance profile.'
    );
  }

  if (!items.length) {
    add(
      'low',
      'development',
      'Maintain current progression',
      'Keep current split and set a new 2-week target: +1 mph EV and +1 command point.',
      'Player is steady/improving with no urgent constraint detected.'
    );
  }

  items.sort((a, b) => {
    const rank = { high: 0, medium: 1, low: 2 };
    return rank[a.priority] - rank[b.priority];
  });

  return items;
}

export default buildRecommendations;
