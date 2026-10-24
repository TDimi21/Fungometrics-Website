import { validScript, nextBattingStep, cageStep, roundLabel, roundGoal, targetStyle } from '@/utils/scriptedPractice'
import { describe, it, expect, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { battingPayload, battingFieldMark } from '@/utils/battingScoring'
import { fieldMarkToBall } from '@/utils/fieldMark'
import { createFieldMapper, configFromLevel } from '@/utils/ballFieldGeometry'
import { getPitchInfoFromCoordinate } from '@/utils/bullpenCoordinates'

const source = compileScript(parse(readFileSync('resources/js/pages/training/BattingScoring.vue', 'utf8')).descriptor, { id: 'bp-test' }).content
const script = source.replace(/^import .*from ['"].*['"];?\s*$/gm, '').replace('export default', 'return')
async function screen({ role = 'coach', completed = false, scripted = false, type = 'B', fails = false, empty = false, plan = null } = {}) {
  const session = { id: 'session-1', user_id: role === 'player' ? 'p1' : 'coach1', team_id: role === 'player' ? null : 'team-1', type, is_completed: completed, is_scripted: scripted, players: [{ id: 'p1', name: { full: 'One' } }, { id: 'p2', name: { full: 'Two' } }] }
  const rows = [{ id: 'r1', batter_id: 'p1', is_contact: true, velocity: 80 }, { id: 'r2', batter_id: 'p2', is_contact: false, velocity: 0 }]
  let attempts = 0, leave
  const api = {
    axiosGet: vi.fn(async path => {
      if (path.startsWith('result/scripted-bp/')) return {data:{data:{plan:{rounds:plan},batters:[]}}}
      if (!path.startsWith('training/') && empty) throw { response: { status: 404, data: { code: '019-E' } } }
      return { data: { data: path.startsWith('training/') ? session : { ball_x_ball: rows } } }
    }),
    axiosPost: vi.fn(async (path, body) => {
      if (fails && attempts++ === 0) throw Error('lost response')
      return { data: { status: 'success', client_request_id: body.client_request_id, data: { ...body, id: 'saved' } } }
    }),
    axiosPut: vi.fn(async () => ({})),
  }
  const router = { push: vi.fn(async () => {}) }
  const mocks = { validScript, nextBattingStep, cageStep, roundLabel, roundGoal, targetStyle, ScriptPlanBuilder: {}, computed: Vue.computed, onMounted: Vue.onMounted, onUnmounted: Vue.onUnmounted, window: { addEventListener: vi.fn(), removeEventListener: vi.fn() }, ref: Vue.ref, watch: Vue.watch, useRoute: () => ({ query: { session: session.id } }), useRouter: () => router,
    onBeforeRouteLeave: fn => { leave = fn },
    useTrainingStore: () => ({ trainingActive: {}, setDataTraining: vi.fn() }), useUserStore: () => ({ userData: { id: role === 'player' ? 'p1' : 'coach1', type: role } }),
    usePlayerStore: () => ({ players: [] }), useAxiosAuth: () => api, Layout: {}, LivePracticeStats: {},
    extractSessionRows, getPitchInfoFromCoordinate, battingPayload, BattingScoringField: {}, BattingReportCard: {},
    plannerSessionReturn: (storage, id, user, fallback) => fallback, sessionStorage: {},
  }
  const component = new Function(...Object.keys(mocks), script)(...Object.values(mocks))
  let state
  const renderer = Vue.createRenderer({ createComment: () => ({}), remove: () => {}, insert: () => {}, parentNode: () => null, nextSibling: () => null })
  const app = renderer.createApp({ setup() { state = component.setup({}, { expose: () => {} }); return () => null } })
  app.mount({}); await new Promise(resolve => setTimeout(resolve, 0))
  return { s: state, api, router, app, leave: () => leave() }
}
function swing(s) { s.location.value = 1830; s.quality.value = 'H'; s.trajectory.value = 'LD'; s.field.value = 3160; s.velocity.value = '88' }
describe('Batting web scorer', () => {
  it('saves the original batter and session, updates live metrics, clears the form, and prevents double clicks', async () => {
    const { s, api, app } = await screen(); swing(s)
    await Promise.all([s.save(), s.save()])
    expect(api.axiosPost).toHaveBeenCalledTimes(1)
    expect(api.axiosPost).toHaveBeenCalledWith('result/batting', expect.objectContaining({ practice_id: 'session-1', batter_id: 'p1', team_id: 'team-1', pitch_mark: 1830, field_mark: 3160, quality_of_contact: 'H', type_of_hit: 'LD', is_contact: true, velocity: 88 }))
    expect(s.stats.value).toMatchObject({ count: 2, top: 88, average: 84 })
    expect(s.field.value).toBe(0); expect(s.quality.value).toBe('')
    s.selected.value = 'p2'; await Vue.nextTick(); expect(s.stats.value.average).toBeNull(); app.unmount()
  })
  it('requires trajectory for contact, permits a take, and excludes field and velocity for MISS/F', async () => {
    const { s, api, app } = await screen({ empty: true })
    s.quality.value = 'W'; await s.save(); expect(api.axiosPost).not.toHaveBeenCalled()
    s.quality.value = ''; await s.save()
    expect(api.axiosPost.mock.calls[0][1]).toMatchObject({ quality_of_contact: 'N', type_of_hit: 'TK', is_contact: false, velocity: 0 })
    swing(s); s.chooseQuality('M'); expect(s.field.value).toBe(0); expect(s.velocity.value).toBe('')
    await s.save(); expect(api.axiosPost.mock.calls[1][1]).toMatchObject({ quality_of_contact: 'M', type_of_hit: 'SM', is_contact: false, field_mark: null, velocity: 0 }); app.unmount()
  })
  it('blocks resubmission after an uncertain save until saved results are checked', async () => {
    const { s, api, app, leave } = await screen({ fails: true }); swing(s)
    await s.save(); await s.save(); expect(api.axiosPost).toHaveBeenCalledTimes(1)
    expect(s.field.value).toBe(3160); expect(s.pending.value).toBe(true); expect(leave()).toBe(false)
    await s.load(); expect(s.pending.value).toBeNull(); expect(leave()).toBe(true); app.unmount()
  })
  it.each([{ completed: true }, { scripted: true }, { type: 'P' }])('blocks completed or incompatible sessions %j', async options => {
    const { s, api, app } = await screen(options); swing(s); await s.save(); expect(api.axiosPost).not.toHaveBeenCalled(); app.unmount()
  })
  it('requests player-scoped statistics and finishes the same session', async () => {
    const { s, api, router, app } = await screen({ role: 'player' })
    expect(s.players.value).toHaveLength(1); expect(s.visibleRows.value).toHaveLength(1)
    expect(api.axiosGet).toHaveBeenCalledWith('statistics/session-1/batting', { player: 1 })
    await s.report(); expect(router.push).toHaveBeenCalledWith('/session/report/session-1/batting?scope=player')
    s.note.value = 'Done'; await s.finish(); expect(api.axiosPut).toHaveBeenCalledWith('training/session-1', { end_note: 'Done', is_completed: true }); app.unmount()
  })
  it('preserves app field/velocity-only scoring as unknown trajectory rather than take', () => {
    const base = { session: { id: 's', team_id: 't' }, batter: 'p', pitch: 0, field: 3160, quality: '', trajectory: '', velocity: '' }
    expect(battingPayload(base)).toMatchObject({ type_of_hit: 'N', quality_of_contact: 'N', field_mark: 3160 })
    expect(battingPayload({ ...base, field: 0, velocity: '80' })).toMatchObject({ type_of_hit: 'N', velocity: 80 })
  })
  it('round-trips vector field taps through the same 80x80 coordinates as session reports', () => {
    const cfg = configFromLevel('hs'), map = createFieldMapper(600,400,cfg)
    for (const angle of [-30,0,30]) {
      const p = map.mapBall(200,angle), mark = battingFieldMark(p.px,p.py)
      const result = fieldMarkToBall({ field_mark: mark },cfg)
      // One column spans 90/80 degrees; mobile quantizes to this grid.
      expect(Math.abs(result.spray_angle-angle)).toBeLessThanOrEqual(90/80)
      expect(Math.abs(result.distance_travel-200)).toBeLessThan(6)
    }
  })
})

it('advances scripted rounds only after atomic confirmation and keeps session stats', async () => {
 const {s,api,app}=await screen({scripted:true,plan:[{round_type:'BARREL',swing_count:1}],empty:true})
 expect(s.step.value.playerId).toBe('p1');swing(s)
 api.axiosPost.mockImplementation(async (path,body)=>({data:{status:'success',data:{swing:{...body,id:'swing1',raw_score:8},batting_result:{id:'result1',batter_id:body.batter_id,velocity:88,is_contact:true},score:8}}}))
 await s.save()
 expect(api.axiosPost).toHaveBeenCalledWith('result/scripted-bp/swing',expect.objectContaining({batter_id:'p1',round_type:'BARREL',round_swing_index:1,contact_type:'Hard',web_result:expect.any(Object)}))
 expect(s.rows.value).toHaveLength(1);expect(s.scriptSwings.value).toHaveLength(1);expect(s.selected.value).toBe('p2')
 expect(s.step.value.playerId).toBe('p2'); app.unmount()
})

it('makes team and other-owned sessions read-only for players', async () => {
  const {s, api, app} = await screen({role:'player'})
  expect(s.writable.value).toBeTruthy()
  s.session.value.team_id = 'team-1'
  expect(s.writable.value).toBeFalsy()
  s.note.value = 'Done'; await s.finish()
  expect(api.axiosPut).not.toHaveBeenCalled()
  s.session.value.team_id = null; s.session.value.user_id = 'other'
  expect(s.writable.value).toBeFalsy()
  app.unmount()
})
