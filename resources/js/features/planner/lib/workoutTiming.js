const minutesValue = value => value !== '' && value != null && Number.isFinite(Number(value)) && Number(value) >= 0 ? Number(value) : null
export const drillMinutes = item => minutesValue(item.plannedMinutes) ?? (Number(item.durationSec) > 0 ? Number(item.durationSec) / 60 : 4)
export const sectionMinutes = bucket => minutesValue(bucket.durationMinutes) ?? Math.ceil((bucket.items || []).reduce((sum, item) => sum + drillMinutes(item), 0))
export const clockMinutes = value => /^([01]\d|2[0-3]):[0-5]\d$/.test(value || '') ? Number(value.slice(0, 2)) * 60 + Number(value.slice(3)) : null
const clock = value => `${String(Math.floor(value / 60) % 24).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`
export function workoutTiming(plan = {}) {
  const start = clockMinutes(plan.startTime)
  let elapsed = 0
  const sections = (plan.buckets || []).map(bucket => {
    const minutes = Math.ceil(sectionMinutes(bucket))
    const from = start == null ? null : start + elapsed
    elapsed += minutes
    const to = start == null ? null : start + elapsed
    return {minutes, startTime: from == null ? '' : clock(from), endTime: to == null ? '' : clock(to), startDayOffset: from == null ? 0 : Math.floor(from / 1440), endDayOffset: to == null ? 0 : Math.floor(to / 1440)}
  })
  return {sections, minutes: elapsed, endTime: sections.at(-1)?.endTime || plan.startTime || '', endDayOffset: sections.at(-1)?.endDayOffset || 0}
}
export function restoreSectionTiming(bucket) {
  if (bucket.durationMinutes != null || bucket.timingMode === 'automatic') return bucket
  const start = clockMinutes(bucket.startTime), end = clockMinutes(bucket.endTime)
  return start != null && end != null && end > start ? {...bucket, durationMinutes: end - start} : bucket
}
