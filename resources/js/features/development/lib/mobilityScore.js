const safe = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));

const asymmetryPenalty = (l, r, threshold = 6) => {
  const diff = Math.abs(safe(l) - safe(r));
  if (diff <= threshold) return 0;
  return Math.min(25, (diff - threshold) * 2.2);
};

export function computeMobilityScore(input = {}) {
  const baseShoulder = (
    safe(input.shoulder_ir_left) + safe(input.shoulder_ir_right) +
    safe(input.shoulder_er_left) + safe(input.shoulder_er_right)
  ) / 4;

  const baseHip = (
    safe(input.hip_ir_left) + safe(input.hip_ir_right) +
    safe(input.hip_er_left) + safe(input.hip_er_right)
  ) / 4;

  const baseAnkle = (safe(input.ankle_dorsiflexion_left) + safe(input.ankle_dorsiflexion_right)) / 2;
  const baseThoracic = (safe(input.thoracic_rotation_left) + safe(input.thoracic_rotation_right)) / 2;
  const baseHamstring = (safe(input.hamstring_slr_left) + safe(input.hamstring_slr_right)) / 2;

  const normalized = clamp(
    baseShoulder * 0.24 +
    baseHip * 0.20 +
    baseAnkle * 0.14 +
    baseThoracic * 0.16 +
    baseHamstring * 0.26
  );

  const penalties = {
    shoulderIR: asymmetryPenalty(input.shoulder_ir_left, input.shoulder_ir_right),
    hipIR: asymmetryPenalty(input.hip_ir_left, input.hip_ir_right),
    ankle: asymmetryPenalty(input.ankle_dorsiflexion_left, input.ankle_dorsiflexion_right, 4),
    thoracic: asymmetryPenalty(input.thoracic_rotation_left, input.thoracic_rotation_right),
  };

  const penaltyTotal = penalties.shoulderIR + penalties.hipIR + penalties.ankle + penalties.thoracic;

  return {
    score: Math.round(clamp(normalized - penaltyTotal * 0.35)),
    penalties,
    asymmetries: {
      shoulder_ir_diff: Math.abs(safe(input.shoulder_ir_left) - safe(input.shoulder_ir_right)),
      hip_ir_diff: Math.abs(safe(input.hip_ir_left) - safe(input.hip_ir_right)),
      ankle_diff: Math.abs(safe(input.ankle_dorsiflexion_left) - safe(input.ankle_dorsiflexion_right)),
      thoracic_diff: Math.abs(safe(input.thoracic_rotation_left) - safe(input.thoracic_rotation_right)),
    },
  };
}

export default computeMobilityScore;
