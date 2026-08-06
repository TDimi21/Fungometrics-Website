// Shared constants for the player home dashboard.
// Keep every magic id / bound here so the numbers exist in exactly one place.

// Option ids the `result/statistics/...` endpoints expect for each training
// sub-mode. They identify the selectable drill options stored in the options
// table: 35-38 are the Exit Velocity drills, 39-44 the Long Toss drills, and
// 45-47 the Weighted Ball drills. Request payloads send them grouped by mode.
export const TRAINING_OPTION_IDS = {
  WB: [45, 46, 47],
  EV: [35, 36, 37, 38],
  LT: [39, 40, 41, 42, 43, 44],
}

// The pitch/cage location grid is 60x60 and a mark encodes column and row as
// mark = (col - 1) * 60 + row. The strike zone occupies columns 19-41 and
// rows 18-43 of that grid.
export const LOCATION_GRID_SIZE = 60
export const STRIKE_ZONE = {
  COL_MIN: 19,
  COL_MAX: 41,
  ROW_MIN: 18,
  ROW_MAX: 43,
}

export const markToColRow = (mark) => {
  const m = Number(mark)
  if (!Number.isFinite(m) || m <= 0) return null
  return {
    col: Math.floor((m - 1) / LOCATION_GRID_SIZE) + 1,
    row: ((m - 1) % LOCATION_GRID_SIZE) + 1,
  }
}

export const isStrikeZoneMark = (mark) => {
  const cell = markToColRow(mark)
  if (!cell) return false
  return (
    cell.col >= STRIKE_ZONE.COL_MIN &&
    cell.col <= STRIKE_ZONE.COL_MAX &&
    cell.row >= STRIKE_ZONE.ROW_MIN &&
    cell.row <= STRIKE_ZONE.ROW_MAX
  )
}

export const STAT_TABS = [
  { key: 'bp', label: 'BP Stats' },
  { key: 'bullpen', label: 'Bullpen' },
  { key: 'cage', label: 'Cage' },
  { key: 'weighted', label: 'Weighted' },
  { key: 'exitVel', label: 'Exit Velocity' },
  { key: 'longToss', label: 'Long Toss' },
]

export const SESSION_MODE_LABEL_MAP = {
  EV: 'Exit Velocity',
  LT: 'Long Toss',
  WB: 'Weighted Ball',
  HP: 'Hit or Pitch',
}

export const SESSION_TYPE_LABEL_MAP = {
  B: 'Batting Practice',
  P: 'Bullpen Practice',
  C: 'Cage Practice',
  L: 'LiveAB Practice',
}

// Maps a session's source (practice type, or training mode for type T) to the
// report route's type segment.
export const SESSION_REPORT_TYPE = {
  B: 'batting',
  P: 'bullpen',
  C: 'cage',
  EV: 'exit_velocity',
  LT: 'long_toss',
  WB: 'weight_ball',
}

export const SESSION_TYPE_COLOR = {
  batting:       { bg: 'bg-sky-500/20', border: 'border-sky-500/40', text: 'text-sky-300', label: 'BATTING' },
  bullpen:       { bg: 'bg-violet-500/20', border: 'border-violet-500/40', text: 'text-violet-300', label: 'BULLPEN' },
  cage:          { bg: 'bg-emerald-500/20', border: 'border-emerald-500/40', text: 'text-emerald-300', label: 'CAGE' },
  live:          { bg: 'bg-orange-500/20', border: 'border-orange-500/40', text: 'text-orange-300', label: 'LIVE AB' },
  long_toss:     { bg: 'bg-pink-500/20', border: 'border-pink-500/40', text: 'text-pink-300', label: 'LONG TOSS' },
  weight_ball:   { bg: 'bg-yellow-500/20', border: 'border-yellow-500/40', text: 'text-yellow-300', label: 'WEIGHT BALL' },
  exit_velocity: { bg: 'bg-red-500/20', border: 'border-red-500/40', text: 'text-red-300', label: 'EXIT VEL' },
}

// Expected velocity ranges (mph) per weighted-ball weight, used to scale the
// metric bars: [poor→avg threshold, avg→great threshold] inside [min, max].
export const WEIGHTED_VELO_SCALES = {
  3: { min: 60, max: 110, thresholds: [75, 88] },
  4: { min: 58, max: 108, thresholds: [72, 85] },
  5: { min: 55, max: 100, thresholds: [68, 80] },
  6: { min: 52, max: 95, thresholds: [65, 77] },
  7: { min: 50, max: 92, thresholds: [62, 74] },
  9: { min: 45, max: 85, thresholds: [58, 70] },
}

// Bar gauge colors (poor / average / great thirds).
export const BAR_COLOR_POOR = '#ef4444'
export const BAR_COLOR_AVG = '#facc15'
export const BAR_COLOR_GREAT = '#22c55e'

// Chart series colors used by the training report line/bar charts.
export const CHART_COLORS = {
  green: '#37D67A',
  blue: '#34A7FF',
  yellow: '#F7D774',
  accent: '#ef3340',
  white: '#FFFFFF',
}

// Spray / trajectory segment colors used by the distribution bars.
export const SEGMENT_COLORS = {
  red: '#ef3340',
  blue: '#3498DB',
  green: '#2ECC71',
  orange: '#F39C12',
  purple: '#9B59B6',
  navy: '#191C4A',
  maroon: '#8C234A',
}
