export const localDateKey = (date = new Date()) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
export const parseCalendarDate = key => new Date(`${key}T12:00:00`)
export const shiftCalendarDate = (key, days) => {
  const date = parseCalendarDate(key)
  date.setDate(date.getDate() + days)
  return localDateKey(date)
}
export const calendarDays = (selected, mode = 'week') => {
  const date = parseCalendarDate(selected)
  if (mode === 'month') date.setDate(1)
  date.setDate(date.getDate() - (date.getDay() + 6) % 7)
  const start = localDateKey(date)
  return Array.from({length: mode === 'month' ? 42 : 7}, (_, i) => shiftCalendarDate(start, i))
}
export const moveCalendar = (key, mode, direction) => {
  if (mode === 'week') return shiftCalendarDate(key, direction * 7)
  const date = parseCalendarDate(key), day = date.getDate()
  date.setDate(1)
  date.setMonth(date.getMonth() + direction)
  const end = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()
  date.setDate(Math.min(day, end))
  return localDateKey(date)
}
export const plansOnDate = (plans, date, status = 'all') => plans.filter(plan => plan.date?.slice(0, 10) === date && (status === 'all' || plan.status === status))
