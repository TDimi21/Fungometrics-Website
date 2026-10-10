import {describe,it,expect} from 'vitest'
import {plannerLink,teamPlannerRows,validPlannerDate} from '../../resources/js/features/planner/lib/plannerLinks'
describe('dashboard planner shortcuts',()=>{
  it('opens the workout page on the selected day with the requested action',()=>{
    expect(plannerLink('2026-10-09','create')).toEqual({name:'workout.planner',query:{date:'2026-10-09',action:'create'}})
    expect(plannerLink('2026-10-10','players','p1').query).toEqual({date:'2026-10-10',action:'players',plan:'p1'})
    expect(plannerLink('2026-10-10').query).not.toHaveProperty('action')
  })
  it('does not show another team’s workouts or templates',()=>{
    const rows=[{id:'a',team_id:'1',status:'draft'},{id:'b',team_id:'2',status:'published'},{id:'c',team_id:'1',status:'template'},{id:'d',status:'draft'}]
    expect(teamPlannerRows(rows,1).map(p=>p.id)).toEqual(['a'])
    expect(teamPlannerRows(rows,null)).toEqual([])
  })
  it('rejects malformed dates and impossible calendar dates from links',()=>{
    expect(validPlannerDate('2026-10-09')).toBe(true)
    expect(validPlannerDate('2028-02-29')).toBe(true)
    for(const value of ['2026-02-29','2026-13-01','bad',undefined,['2026-10-09']])expect(validPlannerDate(value)).toBe(false)
  })
})
