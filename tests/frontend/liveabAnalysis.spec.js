import {describe, it, expect} from 'vitest'
import {liveabAnalysisBalls, analysisValue, resultColor} from '@/utils/liveabAnalysis'
import {fieldMarkToBall} from '@/utils/fieldMark'
import {markToColRow} from '@/features/player-home/lib/constants'
const rows = [
  {pitching:{pitcher_id:1,profile:{first_name:'Pat',last_name:'Pitcher'},type_throw:'CB',trajectory:'LD',pitch_mark:901,miles_per_hour:80,zone:'S'},batting:{batter_id:2,profile:{first_name:'Sam',last_name:'Hitter'},velocity:90,field_mark:3201}},
  {pitching:{pitcher_id:1,type_throw:'FB',trajectory:'TK',pitch_mark:3600,miles_per_hour:null},batting:{batter_id:2,velocity:null}},
]
describe('LiveAB mobile-style analysis',()=>{
  it('keeps pitcher and hitter identity, velocity and locations separate',()=>{
    const pitcher=liveabAnalysisBalls(rows), hitter=liveabAnalysisBalls(rows,'hitting')
    expect(pitcher[0]).toMatchObject({playerId:'1',playerName:'Pat Pitcher',pitch:'CV',velocity:80,pitch_mark:901})
    expect(hitter[0]).toMatchObject({playerId:'2',velocity:90,field_mark:3201,trajectory:'LD'})
    expect(hitter[1].field_mark).toBeUndefined()
    expect(markToColRow(pitcher[0].pitch_mark)).toEqual({col:16,row:1})
    expect(fieldMarkToBall(hitter[0]).distance_travel).toBeGreaterThan(0)
  })
  it('matches pitch percentages and counts without treating missing velocity as a reading',()=>{
    const balls=liveabAnalysisBalls(rows)
    expect(analysisValue(balls,'CV','pitch','pitching')).toBe('50.0%')
    expect(analysisValue(balls,'CV','contact','pitching')).toBe(1)
    expect(analysisValue(balls,'CV','pitch','hitting')).toBe(1)
    expect(analysisValue(balls,'FB','velocity','pitching')).toBe('—')
    expect(analysisValue(balls,'MAX','velocity','pitching')).toBe('80.0')
    expect(analysisValue([], 'MAX','velocity','hitting')).toBe('—')
  })
  it('uses the mobile outcome colors and handles missing data',()=>{
    expect(resultColor({trajectory:'LD'})).toBe('#2ECC71')
    expect(resultColor({trajectory:'FOUL'})).toBe('#F1C40F')
    expect(resultColor({trajectory:'SM'})).toBe('#E74C3C')
    expect(resultColor({trajectory:'TK'})).toBe('#3498DB')
    expect(liveabAnalysisBalls(null)).toEqual([])
  })
})

import {readFileSync} from 'node:fs'
import {parse, compileScript} from '@vue/compiler-sfc'
import {computed, reactive, ref, watch} from 'vue'
import {pitchColors, trajectoryColors} from '@/utils/liveabAnalysis'
function panel(props) {
  const source = compileScript(parse(readFileSync('resources/js/components/statistics/LiveABAnalysis.vue','utf8')).descriptor,{id:'live-analysis-test'}).content
    .replace(/^import[^\n]+\n/gm,'').replace('export default','return')
  // Only JavaScript identifiers belong in Function's parameter list.
  // Node 24 module namespaces can also expose a 'module.exports' key.
  const globals = {computed, ref, watch, liveabAnalysisBalls, analysisValue, resultColor, pitchColors, trajectoryColors, VelocitySprayField:{}, StatsStrikeZonePlot:{}, stadium:''}
  const component = new Function(...Object.keys(globals),source)(...Object.values(globals))
  return component.setup(reactive(props),{expose(){}})
}
it('keeps Team and player ID 1 distinct and combines player and pitch filters',()=>{
  const p = panel({balls:[...rows,{pitching:{pitcher_id:3,type_throw:'FB',pitch_mark:10}}],side:'pitching',mode:'pitch'})
  expect(p.shown.value).toHaveLength(3)
  p.selected.value='1'
  expect(p.shown.value).toHaveLength(2)
  p.filter.value='CV'
  expect(p.shown.value).toHaveLength(1)
  p.filter.value='ALL'
  expect(p.selected.value).toBe('1')
  expect(p.shown.value).toHaveLength(2)
  p.selected.value=null
  expect(p.shown.value).toHaveLength(3)
})
it('excludes absent field locations and retains mobile hitter velocity columns',()=>{
  const p=panel({balls:rows,side:'hitting',mode:'velocity'})
  expect(p.located.value).toHaveLength(1)
  expect(p.columns.value).toEqual(['MAX','LD','FB','GB'])
})
