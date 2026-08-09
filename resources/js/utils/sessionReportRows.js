const ROW_KEYS = {
  batting: ['ball_by_ball_results', 'ball_x_ball', 'ball_by_ball', 'pitches', 'results', 'batting'],
  bullpen: ['ball_by_ball_results', 'ball_x_ball', 'ball_by_ball', 'pitches', 'results', 'bullpen', 'pitching', 'P'],
  cage: ['ball_x_ball', 'ball_by_ball_results', 'cage_results', 'results', 'cage', 'batters'],
  exit_velocity: ['ball_x_ball', 'ball_by_ball_results', 'results', 'exit_velocity'],
  long_toss: ['ball_x_ball', 'ball_by_ball_results', 'throws', 'results', 'practice_match_result', 'long_toss', 'longtoss'],
  weight_ball: ['ball_x_ball', 'ball_by_ball_results', 'results', 'practice_match_result', 'weight_ball', 'weightball', 'weight_balls'],
  live_ab: ['ball_x_ball', 'ball_by_ball_results', 'pitches', 'results', 'live_ab', 'liveab', 'live'],
}

function parsePossibleJson(value) {
  if (typeof value !== 'string') return value
  try {
    return JSON.parse(value)
  } catch {
    return null
  }
}

export function normalizeSessionRows(value) {
  const parsed = parsePossibleJson(value)
  if (Array.isArray(parsed)) return parsed.filter(row => row && typeof row === 'object')
  if (!parsed || typeof parsed !== 'object') return []

  // Filtered Laravel collections may preserve numeric keys and serialize as
  // objects instead of arrays. Their values are still the report rows.
  const values = Object.values(parsed)
  return values.length && values.every(row => row && typeof row === 'object' && !Array.isArray(row))
    ? values
    : []
}

export function extractSessionRows(payload, sessionType) {
  const roots = [payload, payload?.data, payload?.data?.data].filter(Boolean)
  const keys = ROW_KEYS[sessionType] || ROW_KEYS.batting

  for (const root of roots) {
    if (Array.isArray(root)) return normalizeSessionRows(root)
    if (!root || typeof root !== 'object') continue

    for (const key of keys) {
      const candidate = root[key]
      const direct = normalizeSessionRows(candidate)
      if (direct.length) return direct

      // Legacy responses sometimes wrap the collection beneath a type key.
      if (candidate && typeof candidate === 'object' && !Array.isArray(candidate)) {
        for (const nestedKey of keys) {
          const nested = normalizeSessionRows(candidate[nestedKey])
          if (nested.length) return nested
        }
      }
    }
  }

  return []
}

export { ROW_KEYS as SESSION_REPORT_ROW_KEYS }
