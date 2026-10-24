import { describe,it,expect } from 'vitest'
import {validScript,nextBattingStep,cageStep,pitchAccuracy} from '@/utils/scriptedPractice'
import {CAGE_DRILLS} from '@/data/cageDrills'
import {ROUND_TYPES} from '@/data/battingRounds'
describe('App scripted practice parity',()=>{
 it('advances all hitters before advancing rounds, including after reload',()=>{
  const plan=[{round_type:'BARREL',swing_count:1},{round_type:'CONTACT',swing_count:1}],players=[{id:'a'},{id:'b'}]
  const swings=[{batter_id:'a',round_type:'BARREL'}]
  expect(nextBattingStep(plan,players,swings)).toMatchObject({round:0,playerId:'b',index:1})
  swings.push({batter_id:'b',round_type:'BARREL'})
  expect(nextBattingStep(plan,players,swings)).toMatchObject({round:1,playerId:'a'})
  swings.push({batter_id:'a',round_type:'CONTACT'},{batter_id:'b',round_type:'CONTACT'})
  expect(nextBattingStep(plan,players,swings)).toBeNull()
 })
 it('supports repeated cage drills and exact round boundaries',()=>{
  const plan=[{drillId:'cage1',swing_count:2},{drillId:'cage1',swing_count:3}]
  expect(cageStep(plan,2)).toMatchObject({round:2,index:1})
  expect(cageStep(plan,5)).toBeNull()
 })
 it('uses the app libraries and limits',()=>{
  expect(ROUND_TYPES).toHaveLength(20); expect(CAGE_DRILLS.length).toBeGreaterThan(20)
  expect(validScript([{round_type:'BARREL',swing_count:21}],'B')).toBe(false)
  expect(validScript([{round_type:'BARREL',swing_count:5},{round_type:'BARREL',swing_count:5}],'B')).toBe(false)
  expect(validScript([{pitchType:'FB',intendedCoord:0}],'P')).toBe(false)
  expect(pitchAccuracy(1830,1830)).toEqual({grade:'PERFECT',distance:0})
  expect(pitchAccuracy(1,3600).grade).toBe('MISS')
 })
})
