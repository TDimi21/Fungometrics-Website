// ─── Source notes ──────────────────────────────────────────────────────────────
// EV benchmarks: Blast Motion / Driveline / Perfect Game population data
// Pitching velo benchmarks: Perfect Game / PITCHF/x youth population data
// Athlete benchmarks (vertical, broad jump, sprint): NSCA youth norms
// Strength benchmarks: NSCA / EXOS youth baseball weight-room norms
// All values in standard units: mph, inches, lbs, seconds, hrs
// ──────────────────────────────────────────────────────────────────────────────

export const DEFAULT_AGE_PERCENTILE_BENCHMARKS = [

  // ── MAX EXIT VELOCITY (mph) ─────────────────────────────────────────────────
  { metric_key: 'max_exit_velocity', age_group: '9U', level: 'travel', p10: 42, p25: 48, p50: 54, p75: 61, p90: 67, p95: 71, p99: 76, source: 'FMTRX v2', active: true },
  { metric_key: 'max_exit_velocity', age_group: '11',  level: 'travel', p10: 50, p25: 55, p50: 62, p75: 69, p90: 75, p95: 79, p99: 83, source: 'FMTRX v2', active: true },
  { metric_key: 'max_exit_velocity', age_group: '13',  level: 'travel', p10: 58, p25: 63, p50: 70, p75: 77, p90: 83, p95: 87, p99: 92, source: 'FMTRX v2', active: true },
  { metric_key: 'max_exit_velocity', age_group: '14',  level: 'travel', p10: 62, p25: 68, p50: 75, p75: 83, p90: 90, p95: 94, p99: 99, source: 'FMTRX v2', active: true },
  { metric_key: 'max_exit_velocity', age_group: '16',  level: 'travel', p10: 70, p25: 76, p50: 84, p75: 92, p90: 98, p95: 102, p99: 107, source: 'FMTRX v2', active: true },
  { metric_key: 'max_exit_velocity', age_group: '18',  level: 'travel', p10: 78, p25: 85, p50: 93, p75: 100, p90: 106, p95: 110, p99: 115, source: 'FMTRX v2', active: true },

  // ── AVG EXIT VELOCITY (mph) ─────────────────────────────────────────────────
  { metric_key: 'avg_exit_velocity', age_group: '9U', level: 'travel', p10: 36, p25: 41, p50: 47, p75: 53, p90: 58, p95: 62, p99: 66, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_exit_velocity', age_group: '11',  level: 'travel', p10: 43, p25: 48, p50: 54, p75: 60, p90: 65, p95: 69, p99: 73, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_exit_velocity', age_group: '13',  level: 'travel', p10: 51, p25: 56, p50: 62, p75: 68, p90: 73, p95: 77, p99: 81, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_exit_velocity', age_group: '14',  level: 'travel', p10: 55, p25: 61, p50: 67, p75: 74, p90: 80, p95: 84, p99: 88, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_exit_velocity', age_group: '16',  level: 'travel', p10: 62, p25: 68, p50: 75, p75: 82, p90: 88, p95: 92, p99: 97, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_exit_velocity', age_group: '18',  level: 'travel', p10: 70, p25: 76, p50: 83, p75: 90, p90: 96, p95: 100, p99: 105, source: 'FMTRX v2', active: true },

  // ── MAX PITCH VELOCITY (mph) ────────────────────────────────────────────────
  { metric_key: 'max_pitch_velocity', age_group: '9U', level: 'travel', p10: 40, p25: 45, p50: 51, p75: 57, p90: 62, p95: 65, p99: 69, source: 'FMTRX v2', active: true },
  { metric_key: 'max_pitch_velocity', age_group: '11',  level: 'travel', p10: 48, p25: 53, p50: 59, p75: 65, p90: 70, p95: 73, p99: 77, source: 'FMTRX v2', active: true },
  { metric_key: 'max_pitch_velocity', age_group: '13',  level: 'travel', p10: 56, p25: 61, p50: 67, p75: 72, p90: 77, p95: 80, p99: 84, source: 'FMTRX v2', active: true },
  { metric_key: 'max_pitch_velocity', age_group: '14',  level: 'travel', p10: 60, p25: 65, p50: 71, p75: 77, p90: 82, p95: 85, p99: 89, source: 'FMTRX v2', active: true },
  { metric_key: 'max_pitch_velocity', age_group: '16',  level: 'travel', p10: 68, p25: 73, p50: 79, p75: 85, p90: 90, p95: 93, p99: 97, source: 'FMTRX v2', active: true },
  { metric_key: 'max_pitch_velocity', age_group: '18',  level: 'travel', p10: 74, p25: 79, p50: 85, p75: 91, p90: 96, p95: 99, p99: 103, source: 'FMTRX v2', active: true },

  // ── AVG PITCH VELOCITY (mph) ────────────────────────────────────────────────
  { metric_key: 'avg_pitch_velocity', age_group: '9U', level: 'travel', p10: 35, p25: 40, p50: 46, p75: 52, p90: 57, p95: 60, p99: 64, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_pitch_velocity', age_group: '11',  level: 'travel', p10: 43, p25: 48, p50: 54, p75: 60, p90: 65, p95: 68, p99: 72, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_pitch_velocity', age_group: '13',  level: 'travel', p10: 52, p25: 57, p50: 62, p75: 67, p90: 72, p95: 75, p99: 79, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_pitch_velocity', age_group: '14',  level: 'travel', p10: 56, p25: 61, p50: 66, p75: 71, p90: 76, p95: 79, p99: 83, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_pitch_velocity', age_group: '16',  level: 'travel', p10: 63, p25: 68, p50: 73, p75: 78, p90: 83, p95: 86, p99: 90, source: 'FMTRX v2', active: true },
  { metric_key: 'avg_pitch_velocity', age_group: '18',  level: 'travel', p10: 70, p25: 75, p50: 80, p75: 86, p90: 91, p95: 94, p99: 98, source: 'FMTRX v2', active: true },

  // ── VERTICAL JUMP (inches) ──────────────────────────────────────────────────
  { metric_key: 'vertical_jump', age_group: '9U', level: 'travel', p10: 9,  p25: 11, p50: 14, p75: 17, p90: 20, p95: 22, p99: 25, source: 'FMTRX v2', active: true },
  { metric_key: 'vertical_jump', age_group: '11',  level: 'travel', p10: 11, p25: 14, p50: 17, p75: 20, p90: 23, p95: 25, p99: 28, source: 'FMTRX v2', active: true },
  { metric_key: 'vertical_jump', age_group: '13',  level: 'travel', p10: 13, p25: 16, p50: 19, p75: 22, p90: 25, p95: 27, p99: 30, source: 'FMTRX v2', active: true },
  { metric_key: 'vertical_jump', age_group: '14',  level: 'travel', p10: 14, p25: 17, p50: 20, p75: 23, p90: 26, p95: 28, p99: 31, source: 'FMTRX v2', active: true },
  { metric_key: 'vertical_jump', age_group: '16',  level: 'travel', p10: 16, p25: 19, p50: 23, p75: 27, p90: 30, p95: 32, p99: 36, source: 'FMTRX v2', active: true },
  { metric_key: 'vertical_jump', age_group: '18',  level: 'travel', p10: 18, p25: 22, p50: 26, p75: 30, p90: 34, p95: 36, p99: 40, source: 'FMTRX v2', active: true },

  // ── BROAD JUMP (inches) ─────────────────────────────────────────────────────
  { metric_key: 'broad_jump', age_group: '9U', level: 'travel', p10: 42, p25: 50, p50: 58, p75: 66, p90: 73, p95: 77, p99: 83, source: 'FMTRX v2', active: true },
  { metric_key: 'broad_jump', age_group: '11',  level: 'travel', p10: 52, p25: 60, p50: 68, p75: 76, p90: 83, p95: 87, p99: 93, source: 'FMTRX v2', active: true },
  { metric_key: 'broad_jump', age_group: '13',  level: 'travel', p10: 60, p25: 67, p50: 75, p75: 83, p90: 90, p95: 94, p99: 100, source: 'FMTRX v2', active: true },
  { metric_key: 'broad_jump', age_group: '14',  level: 'travel', p10: 62, p25: 70, p50: 78, p75: 86, p90: 94, p95: 99, p99: 106, source: 'FMTRX v2', active: true },
  { metric_key: 'broad_jump', age_group: '16',  level: 'travel', p10: 70, p25: 78, p50: 87, p75: 96, p90: 104, p95: 109, p99: 116, source: 'FMTRX v2', active: true },
  { metric_key: 'broad_jump', age_group: '18',  level: 'travel', p10: 76, p25: 85, p50: 95, p75: 104, p90: 112, p95: 117, p99: 124, source: 'FMTRX v2', active: true },

  // ── 60-YD SPRINT (seconds, lower is better) ─────────────────────────────────
  { metric_key: 'sprint_time', age_group: '9U', level: 'travel', p10: 10.8, p25: 10.2, p50: 9.5, p75: 9.0, p90: 8.6, p95: 8.3, p99: 8.0, source: 'FMTRX v2', active: true, lower_is_better: true },
  { metric_key: 'sprint_time', age_group: '11',  level: 'travel', p10: 9.8,  p25: 9.2,  p50: 8.6, p75: 8.1, p90: 7.7, p95: 7.5, p99: 7.2, source: 'FMTRX v2', active: true, lower_is_better: true },
  { metric_key: 'sprint_time', age_group: '13',  level: 'travel', p10: 8.8,  p25: 8.3,  p50: 7.8, p75: 7.4, p90: 7.0, p95: 6.8, p99: 6.6, source: 'FMTRX v2', active: true, lower_is_better: true },
  { metric_key: 'sprint_time', age_group: '14',  level: 'travel', p10: 8.4,  p25: 8.0,  p50: 7.6, p75: 7.2, p90: 6.9, p95: 6.7, p99: 6.5, source: 'FMTRX v2', active: true, lower_is_better: true },
  { metric_key: 'sprint_time', age_group: '16',  level: 'travel', p10: 8.0,  p25: 7.6,  p50: 7.2, p75: 6.9, p90: 6.6, p95: 6.4, p99: 6.2, source: 'FMTRX v2', active: true, lower_is_better: true },
  { metric_key: 'sprint_time', age_group: '18',  level: 'travel', p10: 7.7,  p25: 7.3,  p50: 6.9, p75: 6.6, p90: 6.3, p95: 6.1, p99: 5.9, source: 'FMTRX v2', active: true, lower_is_better: true },

  // ── MED BALL SCOOP TOSS (feet) ──────────────────────────────────────────────
  { metric_key: 'med_ball_scoop_toss', age_group: '9U', level: 'travel', p10: 9,  p25: 11, p50: 14, p75: 17, p90: 20, p95: 22, p99: 25, source: 'FMTRX v2', active: true },
  { metric_key: 'med_ball_scoop_toss', age_group: '11',  level: 'travel', p10: 11, p25: 14, p50: 17, p75: 21, p90: 24, p95: 27, p99: 30, source: 'FMTRX v2', active: true },
  { metric_key: 'med_ball_scoop_toss', age_group: '13',  level: 'travel', p10: 13, p25: 16, p50: 20, p75: 24, p90: 28, p95: 31, p99: 35, source: 'FMTRX v2', active: true },
  { metric_key: 'med_ball_scoop_toss', age_group: '14',  level: 'travel', p10: 15, p25: 18, p50: 22, p75: 26, p90: 30, p95: 33, p99: 37, source: 'FMTRX v2', active: true },
  { metric_key: 'med_ball_scoop_toss', age_group: '16',  level: 'travel', p10: 17, p25: 21, p50: 25, p75: 30, p90: 34, p95: 37, p99: 42, source: 'FMTRX v2', active: true },
  { metric_key: 'med_ball_scoop_toss', age_group: '18',  level: 'travel', p10: 19, p25: 24, p50: 29, p75: 34, p90: 38, p95: 41, p99: 46, source: 'FMTRX v2', active: true },

  // ── TRAP BAR DEADLIFT (lbs) ─────────────────────────────────────────────────
  { metric_key: 'trap_bar_deadlift', age_group: '9U', level: 'travel', p10: 45,  p25: 60,  p50: 80,  p75: 105, p90: 130, p95: 145, p99: 165, source: 'FMTRX v2', active: true },
  { metric_key: 'trap_bar_deadlift', age_group: '11',  level: 'travel', p10: 65,  p25: 85,  p50: 115, p75: 150, p90: 185, p95: 210, p99: 245, source: 'FMTRX v2', active: true },
  { metric_key: 'trap_bar_deadlift', age_group: '13',  level: 'travel', p10: 95,  p25: 125, p50: 165, p75: 210, p90: 255, p95: 285, p99: 330, source: 'FMTRX v2', active: true },
  { metric_key: 'trap_bar_deadlift', age_group: '14',  level: 'travel', p10: 115, p25: 145, p50: 185, p75: 230, p90: 275, p95: 305, p99: 355, source: 'FMTRX v2', active: true },
  { metric_key: 'trap_bar_deadlift', age_group: '16',  level: 'travel', p10: 145, p25: 185, p50: 235, p75: 290, p90: 340, p95: 375, p99: 425, source: 'FMTRX v2', active: true },
  { metric_key: 'trap_bar_deadlift', age_group: '18',  level: 'travel', p10: 175, p25: 225, p50: 285, p75: 345, p90: 400, p95: 440, p99: 495, source: 'FMTRX v2', active: true },

  // ── BACK SQUAT (lbs) ────────────────────────────────────────────────────────
  { metric_key: 'back_squat', age_group: '9U', level: 'travel', p10: 35,  p25: 50,  p50: 65,  p75: 85,  p90: 105, p95: 120, p99: 140, source: 'FMTRX v2', active: true },
  { metric_key: 'back_squat', age_group: '11',  level: 'travel', p10: 55,  p25: 75,  p50: 100, p75: 130, p90: 160, p95: 180, p99: 210, source: 'FMTRX v2', active: true },
  { metric_key: 'back_squat', age_group: '13',  level: 'travel', p10: 80,  p25: 105, p50: 140, p75: 180, p90: 220, p95: 248, p99: 285, source: 'FMTRX v2', active: true },
  { metric_key: 'back_squat', age_group: '14',  level: 'travel', p10: 95,  p25: 125, p50: 165, p75: 205, p90: 245, p95: 275, p99: 315, source: 'FMTRX v2', active: true },
  { metric_key: 'back_squat', age_group: '16',  level: 'travel', p10: 125, p25: 160, p50: 205, p75: 255, p90: 300, p95: 335, p99: 385, source: 'FMTRX v2', active: true },
  { metric_key: 'back_squat', age_group: '18',  level: 'travel', p10: 155, p25: 200, p50: 255, p75: 310, p90: 360, p95: 395, p99: 450, source: 'FMTRX v2', active: true },

  // ── MOBILITY SCORE (0-100) ──────────────────────────────────────────────────
  { metric_key: 'mobility_score', age_group: '9U', level: 'travel', p10: 45, p25: 55, p50: 67, p75: 78, p90: 87, p95: 92, p99: 97, source: 'FMTRX v2', active: true },
  { metric_key: 'mobility_score', age_group: '11',  level: 'travel', p10: 43, p25: 53, p50: 65, p75: 76, p90: 86, p95: 91, p99: 97, source: 'FMTRX v2', active: true },
  { metric_key: 'mobility_score', age_group: '13',  level: 'travel', p10: 41, p25: 51, p50: 63, p75: 75, p90: 85, p95: 91, p99: 96, source: 'FMTRX v2', active: true },
  { metric_key: 'mobility_score', age_group: '14',  level: 'travel', p10: 40, p25: 50, p50: 62, p75: 74, p90: 84, p95: 90, p99: 96, source: 'FMTRX v2', active: true },
  { metric_key: 'mobility_score', age_group: '16',  level: 'travel', p10: 38, p25: 48, p50: 60, p75: 72, p90: 82, p95: 88, p99: 95, source: 'FMTRX v2', active: true },
  { metric_key: 'mobility_score', age_group: '18',  level: 'travel', p10: 36, p25: 46, p50: 58, p75: 70, p90: 80, p95: 86, p99: 93, source: 'FMTRX v2', active: true },

  // ── RECOVERY SCORE (0-100) ──────────────────────────────────────────────────
  { metric_key: 'recovery_score', age_group: '9U', level: 'travel', p10: 45, p25: 55, p50: 67, p75: 78, p90: 87, p95: 92, p99: 97, source: 'FMTRX v2', active: true },
  { metric_key: 'recovery_score', age_group: '11',  level: 'travel', p10: 42, p25: 52, p50: 64, p75: 75, p90: 85, p95: 91, p99: 96, source: 'FMTRX v2', active: true },
  { metric_key: 'recovery_score', age_group: '13',  level: 'travel', p10: 40, p25: 50, p50: 62, p75: 73, p90: 83, p95: 90, p99: 95, source: 'FMTRX v2', active: true },
  { metric_key: 'recovery_score', age_group: '14',  level: 'travel', p10: 38, p25: 48, p50: 60, p75: 72, p90: 82, p95: 89, p99: 95, source: 'FMTRX v2', active: true },
  { metric_key: 'recovery_score', age_group: '16',  level: 'travel', p10: 36, p25: 46, p50: 58, p75: 70, p90: 80, p95: 87, p99: 93, source: 'FMTRX v2', active: true },
  { metric_key: 'recovery_score', age_group: '18',  level: 'travel', p10: 34, p25: 44, p50: 56, p75: 68, p90: 78, p95: 85, p99: 91, source: 'FMTRX v2', active: true },

  // ── SLEEP HOURS ─────────────────────────────────────────────────────────────
  { metric_key: 'sleep_hours', age_group: '9U', level: 'travel', p10: 7.0, p25: 7.5, p50: 8.2, p75: 9.0, p90: 9.7, p95: 10.1, p99: 10.7, source: 'FMTRX v2', active: true },
  { metric_key: 'sleep_hours', age_group: '11',  level: 'travel', p10: 6.5, p25: 7.2, p50: 8.0, p75: 8.8, p90: 9.5, p95: 9.9,  p99: 10.5, source: 'FMTRX v2', active: true },
  { metric_key: 'sleep_hours', age_group: '13',  level: 'travel', p10: 6.0, p25: 6.8, p50: 7.6, p75: 8.4, p90: 9.1, p95: 9.5,  p99: 10.1, source: 'FMTRX v2', active: true },
  { metric_key: 'sleep_hours', age_group: '14',  level: 'travel', p10: 5.5, p25: 6.2, p50: 7.2, p75: 8.0, p90: 8.7, p95: 9.1,  p99: 9.8,  source: 'FMTRX v2', active: true },
  { metric_key: 'sleep_hours', age_group: '16',  level: 'travel', p10: 5.2, p25: 6.0, p50: 7.0, p75: 7.8, p90: 8.5, p95: 8.9,  p99: 9.5,  source: 'FMTRX v2', active: true },
  { metric_key: 'sleep_hours', age_group: '18',  level: 'travel', p10: 5.0, p25: 5.8, p50: 6.8, p75: 7.6, p90: 8.3, p95: 8.7,  p99: 9.3,  source: 'FMTRX v2', active: true },

]

export default DEFAULT_AGE_PERCENTILE_BENCHMARKS;
