/**
 * Male strength standards by bodyweight (lbs).
 * Source: Strength Level community data.
 * All values are 1RM in lbs (or reps for pull-ups and push-ups).
 *
 * Bodyweights from 110 to 310 lbs in 10 lb increments.
 * Levels: beginner, novice, intermediate, advanced, elite
 */

export const STRENGTH_LEVELS = ['beginner', 'novice', 'intermediate', 'advanced', 'elite']
export const STRENGTH_LEVEL_LABELS = { beginner: 'Beginner', novice: 'Novice', intermediate: 'Intermediate', advanced: 'Advanced', elite: 'Elite' }
export const STRENGTH_LEVEL_COLORS = { beginner: '#94a3b8', novice: '#60a5fa', intermediate: '#facc15', advanced: '#f97316', elite: '#e11d48' }

// Bodyweight rows: 110–310 in 10 lb steps
const BW = [110,120,130,140,150,160,170,180,190,200,210,220,230,240,250,260,270,280,290,300,310]

// ── Front Squat (1RM lbs) ──────────────────────────────────────────────────
export const SQUAT_STANDARDS = {
  label: 'Front Squat',
  unit: 'lbs',
  type: '1rm',
  community: { beginner: 141, intermediate: 287 },
  rows: BW.map((bw, i) => ({ bw, beginner: [74,87,100,113,125,138,150,162,174,186,197,209,220,230,241,251,262,272,282,291,301][i], novice: [114,131,147,162,177,192,207,221,235,248,261,274,287,299,311,323,335,346,357,368,379][i], intermediate: [167,187,206,224,242,259,276,292,308,323,338,353,367,381,395,408,421,434,446,459,470][i], advanced: [229,252,274,295,316,336,355,373,391,408,425,442,457,473,488,503,517,531,545,559,572][i], elite: [298,324,349,373,396,418,439,460,479,499,517,535,553,570,586,603,618,634,649,664,678][i] })),
}

// ── Bench Press (1RM lbs) ──────────────────────────────────────────────────
export const BENCH_STANDARDS = {
  label: 'Bench Press',
  unit: 'lbs',
  type: '1rm',
  community: { beginner: 103, intermediate: 217 },
  rows: BW.map((bw, i) => ({ bw, beginner: [53,63,73,83,93,102,112,121,130,139,148,156,165,173,181,190,197,205,213,220,228][i], novice: [84,97,109,121,133,144,155,166,177,187,197,207,217,227,236,245,254,263,272,280,289][i], intermediate: [125,140,154,169,182,196,209,221,234,246,257,269,280,291,301,312,322,332,341,351,360][i], advanced: [173,191,208,224,240,255,270,284,298,312,325,338,350,362,374,386,397,408,419,429,439][i], elite: [226,247,266,285,302,319,336,352,367,382,397,411,425,438,451,464,476,488,500,511,523][i] })),
}

// ── Pull Ups (reps) ────────────────────────────────────────────────────────
export const PULLUP_STANDARDS = {
  label: 'Pull Ups',
  unit: 'reps',
  type: 'reps',
  community: { intermediate: 14, elite: 37 },
  rows: BW.map((bw, i) => ({ bw, beginner: ['<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1','<1'][i], novice: [5,6,6,6,6,6,6,6,6,6,6,6,5,5,5,5,4,4,4,3,3][i], intermediate: [15,15,15,15,14,14,14,13,13,13,12,12,11,11,10,10,10,10,9,9,9][i], advanced: [27,26,26,25,25,24,23,23,22,21,21,20,19,19,18,17,17,16,16,15,15][i], elite: [40,39,38,37,36,34,33,32,31,30,29,28,27,27,26,25,24,23,23,22,21][i] })),
}

// ── Push Ups (reps) ────────────────────────────────────────────────────────
export const PUSHUP_STANDARDS = {
  label: 'Push Ups',
  unit: 'reps',
  type: 'reps',
  community: { intermediate: 41, elite: 99 },
  rows: BW.map((bw, i) => ({ bw, beginner: ['<1','<1',1,2,3,4,4,5,5,5,6,6,6,6,6,6,6,6,6,6,6][i], novice: [16,17,18,19,19,19,19,20,20,19,19,19,19,19,18,18,18,18,17,17,17][i], intermediate: [42,42,42,42,41,41,40,40,39,39,38,37,37,36,35,35,34,33,33,32,31][i], advanced: [73,72,71,69,68,67,65,64,62,61,60,58,57,56,55,53,52,51,50,49,48][i], elite: [109,106,103,100,97,95,92,90,87,85,83,81,79,77,75,74,72,71,69,68,66][i] })),
}

export const ALL_STANDARDS = [SQUAT_STANDARDS, BENCH_STANDARDS, PULLUP_STANDARDS, PUSHUP_STANDARDS]

/**
 * Given a lift key, body weight in lbs, and a value (lbs or reps),
 * returns the strength level: 'elite' | 'advanced' | 'intermediate' | 'novice' | 'beginner'
 */
export function getStrengthLevel(standard, bodyWeight, value) {
  if (value == null || bodyWeight == null) return null
  // Find closest bodyweight row
  const rows = standard.rows
  let closest = rows[0]
  let minDiff = Math.abs(bodyWeight - rows[0].bw)
  for (const row of rows) {
    const diff = Math.abs(bodyWeight - row.bw)
    if (diff < minDiff) { minDiff = diff; closest = row }
  }
  const v = parseFloat(value)
  if (isNaN(v)) return null
  if (v >= parseFloat(closest.elite))        return 'elite'
  if (v >= parseFloat(closest.advanced))     return 'advanced'
  if (v >= parseFloat(closest.intermediate)) return 'intermediate'
  if (v >= parseFloat(closest.novice))       return 'novice'
  return 'beginner'
}
