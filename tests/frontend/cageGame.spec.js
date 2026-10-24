import {describe,it,expect} from 'vitest'
import {configFromLevel} from '@/utils/cageGame/ballFlight'
import {savedGameHit,validGameField,fieldPayload} from '@/utils/cageGame/session'
import {battedBallOutcome} from '@/utils/cageGame/battedBallOutcome'
describe('Cage game parity and persistence',()=>{
 it('retains presets and custom dimensions and rejects invalid fields',()=>{
  const field=fieldPayload(configFromLevel('12u'));expect(field.basePathFt).toBe(70);expect(validGameField(field)).toBe(true)
  field.fences.lineL=0;expect(validGameField(field)).toBe(false)
  expect(validGameField(null)).toBe(false)
 })
 it('keeps the same outcome across reloads and replays',()=>{
  const row={id:'hit-one',launch_angle_velocity:98,launch_angle:22,spray_angle:8,distance_travel:300},field=fieldPayload(configFromLevel('hs'))
  const first=savedGameHit(row,field)
  expect(savedGameHit(JSON.parse(JSON.stringify(row)),JSON.parse(JSON.stringify(field)))).toEqual(first)
  expect(Object.values(first.outcome.probabilities).reduce((a,b)=>a+b,0)).toBe(100)
 })
 it('matches the app foul and fence rules',()=>{
  const field=fieldPayload(configFromLevel('hs'))
  expect(battedBallOutcome({exitVelocityMph:100,launchAngleDeg:25,sprayAngleDeg:46,carryFt:450},field).foul).toBe(true)
  expect(battedBallOutcome({exitVelocityMph:100,launchAngleDeg:25,sprayAngleDeg:0,carryFt:450},field).probabilities.homeRun).toBe(85)
  expect(battedBallOutcome({exitVelocityMph:100,launchAngleDeg:25,sprayAngleDeg:0,carryFt:200},field).probabilities.homeRun).toBe(0)
 })
})
