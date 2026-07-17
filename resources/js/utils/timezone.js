/**
 * timezone.js — display-only timezone handling.
 *
 * Timestamps are stored/returned by the API as-is; for DISPLAY we convert them to
 * the timezone implied by the user's saved ZIP code, defaulting to Eastern when the
 * ZIP is missing or non-US. Nothing here changes stored data.
 *
 * ZIP → timezone is approximated by 3-digit prefix ranges (US states rarely split a
 * ZIP3 across zones); boundary ZIPs may be off, and everything unknown falls back to
 * Eastern. Arizona (no DST) and Hawaii are handled explicitly.
 */

export const DEFAULT_TIMEZONE = 'America/New_York' // Eastern

// [minPrefix, maxPrefix, IANA timezone] — first match wins, so list exceptions first.
const ZIP3_RANGES = [
  [6, 9, 'America/Puerto_Rico'], // PR / VI (AST, no DST)
  [10, 299, 'America/New_York'], // New England, NY, NJ, PA, mid-Atlantic, DC, VA, WV, NC, SC
  [300, 319, 'America/New_York'], // GA
  [320, 349, 'America/New_York'], // FL (peninsula)
  [350, 369, 'America/Chicago'], // AL
  [370, 385, 'America/Chicago'], // TN / MS
  [386, 397, 'America/Chicago'], // MS
  [398, 399, 'America/New_York'], // GA
  [400, 427, 'America/New_York'], // KY (most)
  [430, 458, 'America/New_York'], // OH
  [459, 479, 'America/New_York'], // IN (most)
  [480, 499, 'America/New_York'], // MI (most)
  [500, 528, 'America/Chicago'], // IA
  [530, 549, 'America/Chicago'], // WI
  [550, 567, 'America/Chicago'], // MN
  [570, 577, 'America/Chicago'], // SD (east)
  [580, 588, 'America/Chicago'], // ND (most)
  [590, 599, 'America/Denver'], // MT
  [600, 629, 'America/Chicago'], // IL
  [630, 658, 'America/Chicago'], // MO
  [660, 679, 'America/Chicago'], // KS
  [680, 693, 'America/Chicago'], // NE
  [700, 715, 'America/Chicago'], // LA
  [716, 729, 'America/Chicago'], // AR
  [730, 749, 'America/Chicago'], // OK
  [750, 797, 'America/Chicago'], // TX (most)
  [798, 799, 'America/Denver'], // TX (El Paso — Mountain)
  [800, 816, 'America/Denver'], // CO
  [820, 831, 'America/Denver'], // WY
  [832, 838, 'America/Boise'], // ID (south)
  [840, 847, 'America/Denver'], // UT
  [850, 865, 'America/Phoenix'], // AZ (no DST)
  [870, 884, 'America/Denver'], // NM
  [889, 898, 'America/Los_Angeles'], // NV
  [900, 961, 'America/Los_Angeles'], // CA
  [967, 968, 'Pacific/Honolulu'], // HI (no DST)
  [969, 969, 'Pacific/Guam'], // GU / territories
  [970, 979, 'America/Los_Angeles'], // OR
  [980, 994, 'America/Los_Angeles'], // WA
  [995, 999, 'America/Anchorage'], // AK
]

/** Resolve a US ZIP (any format) to an IANA timezone, defaulting to Eastern. */
export function zipToTimezone(zip) {
  const digits = String(zip ?? '').replace(/\D/g, '')
  if (digits.length < 3) return DEFAULT_TIMEZONE
  const prefix = parseInt(digits.slice(0, 3), 10)
  if (!Number.isFinite(prefix)) return DEFAULT_TIMEZONE
  for (const [lo, hi, tz] of ZIP3_RANGES) {
    if (prefix >= lo && prefix <= hi) return tz
  }
  return DEFAULT_TIMEZONE
}

/** Parse an API timestamp into a Date (absolute instant), or null if unusable. */
export function toDate(input) {
  if (input == null || input === '') return null
  if (input instanceof Date) return Number.isNaN(input.getTime()) ? null : input
  const d = new Date(input)
  return Number.isNaN(d.getTime()) ? null : d
}

/**
 * Format a timestamp in the timezone of `zip` (Eastern default).
 * `opts` are Intl.DateTimeFormat options; a short tz label ("ET", "PT") is appended
 * unless opts.hideZone is set.
 */
export function formatInZone(input, zip, opts = {}) {
  const d = toDate(input)
  if (!d) return ''
  const timeZone = zipToTimezone(zip)
  const { hideZone, ...intlOpts } = opts
  const base = {
    timeZone,
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    ...intlOpts,
  }
  const text = new Intl.DateTimeFormat('en-US', base).format(d)
  return hideZone ? text : `${text} ${shortZoneLabel(timeZone, d)}`
}

/** Short label for a timezone at a given instant (ET/CT/MT/PT/AKT/HT/AZ…). */
export function shortZoneLabel(timeZone, when = new Date()) {
  try {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'short' }).formatToParts(when)
    const raw = parts.find((p) => p.type === 'timeZoneName')?.value || ''
    // Intl gives e.g. "EDT"/"EST" — collapse to a stable ET/CT/MT/PT label.
    const map = { EST: 'ET', EDT: 'ET', CST: 'CT', CDT: 'CT', MST: 'MT', MDT: 'MT', PST: 'PT', PDT: 'PT', AKST: 'AKT', AKDT: 'AKT', HST: 'HT' }
    if (timeZone === 'America/Phoenix') return 'MST'
    return map[raw] || raw
  } catch {
    return ''
  }
}
