import { describe, it, expect } from 'vitest'
import { reactive, computed, ref } from 'vue'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { livePracticeRows, groupPracticeRows } from '@/utils/livePracticeStats'
const source = compileScript(parse(readFileSync('resources/js/components/statistics/LivePracticeStats.vue', 'utf8')).descriptor, { id: 'live-stats-test' }).content
const script = source.replace(/^import .*from ['"].*['"];?\s*$/gm, '').replace('export default', 'return')
function panel(props) {
  const names = ['TabGroup','TabList','Tab','TabPanels','TabPanel','TabBallByBall','TabPitchBreakdown','TabContact','TabTrajectory','TabVelocity','TabBall','TabPitch','TabCont','TabVelo']
  const mocks = { computed, ref, livePracticeRows, groupPracticeRows, ...Object.fromEntries(names.map(n => [n, {}])) }
  return new Function(...Object.keys(mocks), script)(...Object.values(mocks)).setup(props, { expose() {} })
}
describe.each(['P','B'])('live %s statistics', mode => {
  it('updates tab data and player groups after saving while retaining sort selection', () => {
    const key = mode === 'P' ? 'pitcher_id' : 'batter_id'
    const props = reactive({ mode, rows: [{ id: 'old', [key]: 'p1', sort: 0 }], players: [{ id: 'p1', name: { first: 'Alex', last: 'One' } }, { id: 'p2', name: { full: 'Sam Two' } }] })
    const s = panel(props)
    expect(s.headings.value).toEqual(mode === 'P' ? ['Ball by ball','Pitch breakdown','Contact','Velocity'] : ['Ball by ball','Pitch breakdown','Contact','Trajectory','Velocity'])
    expect(s.enriched.value[0].profile.first_name).toBe('Alex')
    s.sortData('profile.first_name')
    props.rows.push({ id: 'new', [key]: 'p2', sort: 1, velocity: 88, miles_per_hour: 88 })
    expect(s.grouped.value.p2).toHaveLength(1)
    expect(s.sorted.value.map(r => r.id)).toEqual(['old','new'])
    s.sortData('sort'); s.sortData('sort')
    expect(s.sorted.value.map(r => r.id)).toEqual(['new','old'])
    expect(props.rows[1].profile).toBeUndefined()
  })
  it('keeps server profile metadata and never adds roster-only players to results', () => {
    const key = mode === 'P' ? 'pitcher_id' : 'batter_id'
    const result = livePracticeRows([{ id: 'r', [key]: 'p1', profile: { first_name: 'Server', last_name: 'Name', picture: '/avatar.jpg' } }], [{ id: 'p1', name: { full: 'Fallback' } }, { id: 'other' }], mode)
    expect(result[0].profile.first_name).toBe('Server')
    expect(Object.keys(groupPracticeRows(result,mode))).toEqual(['p1'])
    expect(groupPracticeRows([],mode)).toEqual({})
  })
})
