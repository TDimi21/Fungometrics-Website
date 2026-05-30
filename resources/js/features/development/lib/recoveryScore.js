const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, Number.isFinite(v) ? v : 0));
const toFiveScale = (v) => clamp((Number(v) || 0) * 20);

export function sleepHoursScore(hours) {
  const h = Number(hours);
  if (!Number.isFinite(h)) return 0;
  if (h >= 9) return 100;
  if (h >= 8) return 90;
  if (h >= 7) return 80;
  if (h >= 6) return 65;
  if (h >= 5) return 45;
  return 25;
}

export function computeRecoveryScore(input = {}) {
  const sleep = sleepHoursScore(input.sleep_hours);
  const sleepQuality = toFiveScale(input.sleep_quality_1_to_5);
  const energy = toFiveScale(input.energy_1_to_5);
  const sorenessInverted = clamp(120 - toFiveScale(input.soreness_1_to_5));
  const stressInverted = clamp(120 - toFiveScale(input.stress_1_to_5));
  const hydration = toFiveScale(input.hydration_1_to_5);
  const armHealth = toFiveScale(input.arm_health_1_to_5);

  const weighted = (
    sleep * 0.40 +
    sleepQuality * 0.20 +
    energy * 0.15 +
    sorenessInverted * 0.10 +
    hydration * 0.10 +
    stressInverted * 0.05
  );

  const score = clamp(weighted);
  return {
    score: Math.round(score),
    parts: {
      sleep,
      sleepQuality,
      energy,
      sorenessInverted,
      stressInverted,
      hydration,
      armHealth,
    },
  };
}

export default computeRecoveryScore;
