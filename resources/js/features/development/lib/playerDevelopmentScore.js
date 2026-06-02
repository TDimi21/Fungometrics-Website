import { computeRecoveryScore } from './recoveryScore';
import { computeStrengthScore } from './strengthScore';
import { computeMobilityScore } from './mobilityScore';
import { buildTrendSummary } from './trendEngine';

const num = (v) => (Number.isFinite(Number(v)) ? Number(v) : null);
const clamp = (v) => Math.max(0, Math.min(100, Number.isFinite(v) ? v : 0));
const mean = (arr) => {
  const vals = arr.filter((x) => x !== null);
  if (!vals.length) return null;
  return vals.reduce((a, b) => a + b, 0) / vals.length;
};

function performanceScore(input = {}, role = 'two-way') {
  const hitter = mean([
    num(input.avg_exit_velocity),
    num(input.max_exit_velocity),
    num(input.hard_contact_percentage),
    num(input.line_drive_percentage),
    num(input.bp_score),
    num(input.cage_score),
    num(input.live_ab_score),
    num(input.swing_miss_percentage) !== null ? 100 - num(input.swing_miss_percentage) : null,
  ]);

  const pitcher = mean([
    num(input.avg_pitch_velocity),
    num(input.max_pitch_velocity),
    num(input.bullpen_score),
    num(input.command_score),
    num(input.competitive_pitch_percentage),
    num(input.strike_percentage),
    num(input.pitch_quality_score),
  ]);

  if (role === 'hitter') return Math.round(clamp(hitter ?? 0));
  if (role === 'pitcher') return Math.round(clamp(pitcher ?? 0));

  return Math.round(clamp(mean([hitter, pitcher]) ?? hitter ?? pitcher ?? 0));
}

function trendScoreFromSummary(summary = {}) {
  const rows = Object.values(summary?.changes ?? {});
  if (!rows.length) return 50;

  const tally = rows.reduce((acc, r) => {
    if (r.direction === 'up') acc += 1;
    if (r.direction === 'down') acc -= 1;
    return acc;
  }, 0);

  return Math.round(clamp(50 + tally * 6));
}

export function buildPlayerDevelopmentModel(input = {}, history = [], role = 'two-way') {
  const pScore = performanceScore(input, role);
  const strengthComputed = computeStrengthScore(input);
  const strengthExplicit = num(input.strength_score);
  const strength = strengthExplicit !== null
    ? { ...strengthComputed, score: Math.round(clamp(strengthExplicit)) }
    : strengthComputed;
  const mobilityComputed = computeMobilityScore(input);
  const mobilityExplicit = num(input.mobility_score);
  const mobility = mobilityExplicit !== null
    ? { ...mobilityComputed, score: Math.round(clamp(mobilityExplicit)) }
    : mobilityComputed;

  const recoveryComputed = computeRecoveryScore(input);
  const recoveryExplicit = num(input.recovery_score);
  const recovery = recoveryExplicit !== null
    ? { ...recoveryComputed, score: Math.round(clamp(recoveryExplicit)) }
    : recoveryComputed;
  const trend = buildTrendSummary(history);
  const tScore = trendScoreFromSummary(trend);

  const developmentIndex = clamp(
    pScore * 0.40 +
    strength.score * 0.20 +
    mobility.score * 0.15 +
    recovery.score * 0.15 +
    tScore * 0.10
  );

  const status = trend.status === 'no_recent_data'
    ? 'No Recent Data'
    : developmentIndex >= 85
      ? 'Hot'
      : trend.status === 'improving'
        ? 'Improving'
        : trend.status === 'declining'
          ? 'Needs Work'
          : 'Steady';

  return {
    developmentIndex: Math.round(developmentIndex),
    status,
    performanceScore: pScore,
    strengthScore: strength.score,
    mobilityScore: mobility.score,
    recoveryScore: recovery.score,
    trendScore: tScore,
    trend,
    mobility,
    strength,
    recovery,
  };
}

export default buildPlayerDevelopmentModel;
