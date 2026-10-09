import {describe, it, expect} from 'vitest'
import {calendarDays, localDateKey, shiftCalendarDate, moveCalendar, plansOnDate} from '../../resources/js/features/planner/lib/calendar'
describe('workout calendar dates', () => {
  it('shows Monday through Sunday across a year boundary', () => {
    expect(calendarDays('2027-01-01')).toEqual(['2026-12-28','2026-12-29','2026-12-30','2026-12-31','2027-01-01','2027-01-02','2027-01-03'])
  })
  it('includes leap day and fills six full weeks for a month', () => {
    const days = calendarDays('2028-02-15','month')
    expect(days).toHaveLength(42)
    expect(days).toContain('2028-02-29')
    expect(new Set(days).size).toBe(42)
  })
  it('clamps month navigation to its last valid date', () => {
    expect(moveCalendar('2026-01-31','month',1)).toBe('2026-02-28')
    expect(moveCalendar('2028-03-31','month',-1)).toBe('2028-02-29')
    expect(moveCalendar('2026-12-31','month',1)).toBe('2027-01-31')
  })
  it('preserves local calendar dates through daylight-saving weekends', () => {
    expect(shiftCalendarDate('2026-03-07',2)).toBe('2026-03-09')
    expect(shiftCalendarDate('2026-10-31',2)).toBe('2026-11-02')
    expect(localDateKey(new Date(2026,9,9,23,59))).toBe('2026-10-09')
  })
  it('keeps multiple workouts on one day and separates draft and published filters', () => {
    const plans = [{id:1,date:'2026-10-09',status:'draft'},{id:2,date:'2026-10-09',status:'published'},{id:3,date:'2026-10-10',status:'draft'}]
    expect(plansOnDate(plans,'2026-10-09').map(p=>p.id)).toEqual([1,2])
    expect(plansOnDate(plans,'2026-10-09','published').map(p=>p.id)).toEqual([2])
    expect(plansOnDate(plans,'2026-10-11')).toEqual([])
  })
})
