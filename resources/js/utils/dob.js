/**
 * Uniform Date of Birth handling.
 *
 * DOB has historically been stored/returned under many names (born_date, dob,
 * date_of_birth, birth_date, nested `born.date`, …) and in mixed string formats,
 * which made it unreliable to read. These helpers give one canonical way to:
 *   - resolve the raw value from any player/user/profile shape  → resolveBornValue
 *   - parse it to a real Date (in LOCAL time, no off-by-one)     → parseDOB
 *   - format it consistently for display                         → formatDOB
 *   - compute age                                                → ageFromDOB
 *   - normalize to the canonical YYYY-MM-DD for storage          → toISODOB
 *
 * The canonical format everywhere is YYYY-MM-DD.
 */

const BORN_KEYS = ['born_date', 'date_of_birth', 'birth_date', 'birthdate', 'dob', 'born'];

const fromBornField = (v) => {
  if (!v) return null;
  if (typeof v === 'string') return v;
  if (typeof v === 'object') return v.date || v.born_date || null; // { date, age } shape
  return null;
};

/** Pull the DOB string out of any player / user / profile-ish object. */
export function resolveBornValue(obj) {
  if (!obj) return null;
  if (typeof obj === 'string') return obj;

  const sources = [obj, obj.profile, obj.player, obj.user, obj.physical, obj.data, obj.other];
  for (const src of sources) {
    if (!src || typeof src !== 'object') continue;
    for (const key of BORN_KEYS) {
      const val = fromBornField(src[key]);
      if (val) return val;
    }
  }
  return null;
}

/** Parse a DOB value to a Date in LOCAL time (avoids UTC off-by-one), or null. */
export function parseDOB(value) {
  const raw = typeof value === 'object' && value !== null && !(value instanceof Date)
    ? resolveBornValue(value)
    : value;
  if (!raw) return null;
  if (raw instanceof Date) return Number.isNaN(raw.getTime()) ? null : raw;

  const str = String(raw).trim();
  if (!str) return null;

  let y;
  let m;
  let d;

  // YYYY-MM-DD (optionally with a time component)
  let mt = /^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/.exec(str);
  if (mt) {
    y = +mt[1]; m = +mt[2]; d = +mt[3];
  } else {
    // DD/MM/YYYY or MM/DD/YYYY (4-digit year last)
    mt = /^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/.exec(str);
    if (mt) {
      const a = +mt[1];
      const b = +mt[2];
      y = +mt[3];
      if (a > 12 && b <= 12) { d = a; m = b; }       // unambiguous DD/MM
      else if (b > 12 && a <= 12) { m = a; d = b; }  // unambiguous MM/DD
      else { m = a; d = b; }                          // ambiguous → assume MM/DD
    }
  }

  if (y != null) {
    if (y < 1900 || y > 3000 || m < 1 || m > 12 || d < 1 || d > 31) return null;
    return new Date(y, m - 1, d);
  }

  // Last resort: let the engine try, then rebuild as a local date-only value.
  const parsed = new Date(str);
  if (Number.isNaN(parsed.getTime())) return null;
  return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
}

/** Format a DOB for display. style: 'short' (Mon D, YYYY) | 'numeric' (MM/DD/YYYY). */
export function formatDOB(value, style = 'short', fallback = '—') {
  const d = parseDOB(value);
  if (!d) return fallback;
  if (style === 'numeric') {
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${mm}/${dd}/${d.getFullYear()}`;
  }
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/** Whole-years age from a DOB value, or null when unknown. */
export function ageFromDOB(value) {
  const d = parseDOB(value);
  if (!d) return null;
  const now = new Date();
  let age = now.getFullYear() - d.getFullYear();
  const m = now.getMonth() - d.getMonth();
  if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age -= 1;
  return age >= 0 && age < 150 ? age : null;
}

/** Canonical YYYY-MM-DD string for storage / API, or '' when unknown. */
export function toISODOB(value) {
  const d = parseDOB(value);
  if (!d) return '';
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}
