const pct = (value) => (Number.isFinite(value) ? `${value > 0 ? '+' : ''}${value.toFixed(1)}%` : 'n/a');

export function buildCorrelationInsights(trend = {}) {
  const c = trend?.changes ?? {};
  const insights = [];

  if (c.avg_exit_velocity?.direction === 'up' && c.rotational_power_score?.direction === 'up') {
    insights.push(`EV improvement may be connected to rotational power gains (${pct(c.avg_exit_velocity.changePct)} EV, ${pct(c.rotational_power_score.changePct)} rotational power).`);
  }

  if (c.avg_pitch_velocity?.direction === 'up' && c.bp_score?.direction === 'up') {
    insights.push(`Pitch velocity is rising while practice execution is improving (${pct(c.avg_pitch_velocity.changePct)} velo, ${pct(c.bp_score.changePct)} BP score).`);
  }

  if (c.command_score?.direction === 'down' && c.mobility_score?.direction === 'down') {
    insights.push(`Command drop may be related to reduced mobility (${pct(c.command_score.changePct)} command, ${pct(c.mobility_score.changePct)} mobility).`);
  }

  if (c.hard_contact_percentage?.direction === 'up' && c.sleep_hours?.direction === 'up') {
    insights.push(`Hard contact is trending up on improved sleep (${pct(c.hard_contact_percentage.changePct)} hard contact, ${pct(c.sleep_hours.changePct)} sleep).`);
  }

  if (c.bullpen_score?.direction === 'down' && c.recovery_score?.direction === 'down') {
    insights.push(`Bullpen score dipped during lower recovery windows (${pct(c.bullpen_score.changePct)} bullpen, ${pct(c.recovery_score.changePct)} recovery).`);
  }

  if (!insights.length) {
    insights.push('No strong correlation signal yet. Keep collecting daily recovery + mobility to improve insight quality.');
  }

  return insights;
}

export default buildCorrelationInsights;
