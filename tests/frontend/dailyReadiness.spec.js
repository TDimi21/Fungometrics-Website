import {describe,it,expect,vi,afterEach} from 'vitest'
import {readinessFields,readinessContext,readinessQuery,emptyReadiness,readinessValid,readinessPayload} from '../../resources/js/components/readiness/contract'
afterEach(()=>vi.useRealTimers())
describe('daily readiness submission contract',()=>{
 it('requires explicit answers to every rating instead of preselecting a score',()=>{
  expect(readinessValid(emptyReadiness())).toBe(false)
  const draft={...emptyReadiness(),sleep_hours:'7.5',...Object.fromEntries(readinessFields.map(([key])=>[key,10]))}
  expect(readinessValid(draft)).toBe(true)
  expect(readinessPayload(draft)).toMatchObject({sleep_hours:7.5,energy:10,arm_comfort:10})
  expect(readinessValid({...draft,energy:0})).toBe(false)
  expect(readinessValid({...draft,elbow_comfort:11})).toBe(false)
  expect(readinessValid({...draft,sleep_hours:25})).toBe(false)
 })
 it('preserves low comfort ratings and pain flags without reversing the new scale',()=>{
  const draft={sleep_hours:0,pain_flag:true,notes:'Review with coach',...Object.fromEntries(readinessFields.map(([key])=>[key,1]))}
  expect(readinessValid(draft)).toBe(true)
  expect(readinessPayload(draft)).toMatchObject({arm_comfort:1,pain_flag:true})
 })
 it('sends the browser local calendar date with an explicit timezone',()=>{
  vi.useFakeTimers();vi.setSystemTime(new Date(2026,9,25,23,30))
  const ctx=readinessContext()
  expect(ctx.date).toBe('2026-10-25')
  expect(ctx.timezone).toBeTruthy()
  expect(readinessQuery({date:ctx.date,timezone:'America/New_York'})).toBe('date=2026-10-25&timezone=America%2FNew_York')
 })
})
