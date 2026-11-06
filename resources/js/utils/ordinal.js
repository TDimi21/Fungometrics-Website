export const ordinal = value => {
  if (value == null || value === '' || !Number.isFinite(Number(value))) return '—'
  const n = Math.round(Number(value)), last = Math.abs(n) % 100
  return `${n}${last >= 11 && last <= 13 ? 'th' : ({1:'st',2:'nd',3:'rd'}[Math.abs(n) % 10] || 'th')}`
}
