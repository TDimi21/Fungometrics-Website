// Feet and inches are separate units, never a decimal string such as "14.6".
export function cageDimensions(cage, body) {
  const feet = value => {
    if (!value || value.ft == null || value.inch == null) return NaN
    const ft = Number(value.ft), inch = Number(value.inch)
    return Number.isFinite(ft) && ft > 0 && Number.isFinite(inch) && inch >= 0 && inch < 12 ? ft + inch / 12 : NaN
  }
  const widthFt = feet(cage?.width), heightFt = feet(cage?.height), lengthFt = feet(cage?.length)
  if (![widthFt, heightFt, lengthFt].every(n => Number.isFinite(n) && n > 0)) return null
  const height = feet(body)
  return { widthFt, heightFt, lengthFt, contactHeightFt: Math.max(2, (Number.isFinite(height) ? height : 6) / 2) }
}
