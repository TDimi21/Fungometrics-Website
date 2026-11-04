import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref } from 'vue'
import { liveabAnalysisBalls, resultColor } from '@/utils/liveabAnalysis'
import { liveabBoxScore } from '@/utils/liveabBoxScore'
const source = compileScript(parse(readFileSync('resources/js/components/statistics/LiveABPlayerAnalytics.vue','utf8')).descriptor,{id:'analytics-test'}).content.replace(/^import[\s\S]*?from ['"][^'"]+['"];?\s*$/gm,'').replace('export default','return')
function setup(side, playerId) {
  const globals = {computed,ref,liveabAnalysisBalls,resultColor,liveabBoxScore,Dialog:{},DialogPanel:{},DialogTitle:{},StatsStrikeZonePlot:{},VelocitySprayField:{}}
  const component = new Function(...Object.keys(globals),source)(...Object.values(globals))
  const rows = [
    {pitching:{pitcher_id:'p1',type_throw:'FB',pitch_mark:100,zone:'S',trajectory:'SM',miles_per_hour:90},batting:{batter_id:'b1'}},
    {pitching:{pitcher_id:'p1',type_throw:'CH',trajectory:'TK',zone:'B'},batting:{batter_id:'b2'}},
    {pitching:{pitcher_id:'p2',type_throw:'FB',trajectory:'TK'},batting:{batter_id:'b1'}},
  ]
  return component.setup({side,playerId,rows,players:[]},{expose:()=>{},emit:()=>{}})
}
describe('Scorer player analytics',()=>{
  it('isolates a pitcher and combines opponent and pitch filters without inventing locations',()=>{
    const s=setup('pitching','p1')
    expect(s.playerRows.value).toHaveLength(2)
    expect(s.located.value).toHaveLength(1)
    s.opponent.value='b2'
    expect(s.shown.value).toHaveLength(1)
    expect(s.located.value).toHaveLength(0)
    s.pitch.value='FB'
    expect(s.shown.value).toHaveLength(0)
  })
  it('isolates the hitter across pitchers and handles a player without pitches',()=>{
    const s=setup('hitting','b1')
    expect(s.playerRows.value).toHaveLength(2)
    expect(s.opponents.value).toEqual(['p1','p2'])
    s.opponent.value='p2'
    expect(s.shown.value).toHaveLength(1)
    expect(setup('hitting','missing').metrics.value).toEqual([])
  })
})
