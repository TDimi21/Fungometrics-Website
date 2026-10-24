import { fieldPayload, validGameField } from '@/utils/cageGame/session'
import { configFromLevel as gameConfig } from '@/utils/cageGame/ballFlight'
import { validScript, nextBattingStep, cageStep, roundLabel, roundGoal, targetStyle } from '@/utils/scriptedPractice'
import { describe, it, expect, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { cageDimensions } from '@/utils/cageScoring'
import { createCageMapper } from '@/utils/cagePerspective'
import { fieldMarkToBall } from '@/utils/fieldMark'
import { createFieldMapper, configFromLevel } from '@/utils/ballFieldGeometry'
import { getPitchInfoFromCoordinate } from '@/utils/bullpenCoordinates'

const source = compileScript(parse(readFileSync('resources/js/pages/training/CageScoring.vue', 'utf8')).descriptor, { id: 'bp-test' }).content
const script = source.replace(/^import .*from ['"].*['"];?\s*$/gm, '').replace('export default', 'return')
async function screen({ role = 'coach', completed = false, scripted = false, type = 'C', fails = false, empty = false, plan = null, game = null } = {}) {
  const session = { id: 'session-1', team_id: 'team-1', type, cage_data: { width: { ft: 14, inch: 6 }, height: { ft: 12, inch: 0 }, length: { ft: 70, inch: 0 } }, is_completed: completed, is_scripted: scripted, script_plan:plan, game_field:game, players: [{ id: 'p1', name: { full: 'One' } }, { id: 'p2', name: { full: 'Two' } }] }
  const rows = [{ id: 'r1', user_id: 'p1', is_contact: true, launch_angle_velocity: 80 }, { id: 'r2', user_id: 'p2', is_contact: false, launch_angle_velocity: 0 }]
  let attempts = 0, leave
  const api = {
    axiosGet: vi.fn(async path => {
      if (!path.startsWith('training/') && empty) throw { response: { status: 404, data: { code: '038-E' } } }
      return { data: { data: path.startsWith('training/') ? session : { ball_x_ball: rows } } }
    }),
    axiosPost: vi.fn(async (path, body) => {
      if (path === 'result/cage/estimate') return { data: { success: true, data: { distance_ft: 215 } } }
      if (fails && attempts++ === 0) throw Error('lost response')
      return { data: { status: 'success', client_request_id: body.client_request_id, data: { ...body, id: 'saved' } } }
    }),
    axiosPut: vi.fn(async () => ({})),
  }
  const router = { push: vi.fn(async () => {}) }
  const mocks = { fieldPayload, validGameField, configFromLevel: gameConfig, CageGameFieldSetup:{}, CageGameReview:{}, validScript, nextBattingStep, cageStep, roundLabel, roundGoal, targetStyle, ScriptPlanBuilder: {}, computed: Vue.computed, onMounted: Vue.onMounted, onUnmounted: Vue.onUnmounted, window: { addEventListener: vi.fn(), removeEventListener: vi.fn() }, ref: Vue.ref, watch: Vue.watch, useRoute: () => ({ query: { session: session.id } }), useRouter: () => router,
    onBeforeRouteLeave: fn => { leave = fn },
    useTrainingStore: () => ({ trainingActive: {}, setDataTraining: vi.fn() }), useUserStore: () => ({ userData: { id: role === 'player' ? 'p1' : 'coach1', type: role } }),
    usePlayerStore: () => ({ players: [] }), useAxiosAuth: () => api, Layout: {}, LiveCageStats: {}, CageTarget: {},
    extractSessionRows, createCageMapper, cageDimensions,
    plannerSessionReturn: (storage, id, user, fallback) => fallback, sessionStorage: {},
  }
  const component = new Function(...Object.keys(mocks), script)(...Object.values(mocks))
  let state
  const renderer = Vue.createRenderer({ createComment: () => ({}), remove: () => {}, insert: () => {}, parentNode: () => null, nextSibling: () => null })
  const app = renderer.createApp({ setup() { state = component.setup({}, { expose: () => {} }); return () => null } })
  app.mount({}); await new Promise(resolve => setTimeout(resolve, 0))
  return { s: state, api, router, app, leave: () => leave() }
}

async function hit(s) { s.tap.value = { u: .5, v: .5 }; s.velocity.value = '88'; await new Promise(resolve => setTimeout(resolve, 330)) }
describe('Cage scoring', () => {
 it('uses authoritative dimensions, estimates, saves once and refreshes session rows', async () => {
  const { s, api, app } = await screen(); await hit(s)
  expect(s.dimensions.value.widthFt).toBe(14.5)
  await Promise.all([s.save(),s.save()])
  const saves = api.axiosPost.mock.calls.filter(([path]) => path === 'result/cage')
  expect(saves).toHaveLength(1)
  expect(saves[0][1]).toMatchObject({ practice_id:'session-1', user_id:'p1', team_id:'team-1', launch_angle_velocity:88, distance_travel:215, cage_mark:0, cage_position:'0' })
  expect(s.stats.value.count).toBe(2); expect(s.stats.value.top).toBe(88); expect(s.tap.value).toBeNull(); app.unmount()
 })
 it('blocks duplicate submissions after uncertain saves until results are reviewed', async () => {
  const { s, api, app, leave } = await screen({ fails:true }); await hit(s); await s.save(); await s.save()
  expect(api.axiosPost.mock.calls.filter(([path]) => path === 'result/cage')).toHaveLength(1)
  expect(leave()).toBe(false); await s.load(); expect(s.pending.value).toBeNull(); expect(s.showStats.value).toBe(true); app.unmount()
 })
 it.each([{ completed:true },{ scripted:true },{ type:'B' }])('prevents scoring incompatible sessions %j', async options => {
  const { s, api, app } = await screen(options); await hit(s); await s.save()
  expect(api.axiosPost.mock.calls.some(([path]) => path === 'result/cage')).toBe(false); app.unmount()
 })
 it('scopes players and connects report and completion to the same cage session', async () => {
  const { s, api, router, app } = await screen({ role:'player' })
  expect(s.players.value).toHaveLength(1); expect(s.visibleRows.value).toHaveLength(1)
  expect(api.axiosGet).toHaveBeenCalledWith('statistics/session-1/cage',{player:1})
  await s.report(); expect(router.push).toHaveBeenCalledWith('/session/report/session-1/cage?scope=player')
  s.note.value='Done'; await s.finish(); expect(api.axiosPut).toHaveBeenCalledWith('training/session-1',{end_note:'Done',is_completed:true}); app.unmount()
 })
 it('ignores an estimate returned after changing hitter and rejects invalid velocity', async () => {
  const { s, api, app } = await screen(); let resolve
  api.axiosPost.mockImplementationOnce(() => new Promise(r => { resolve=r }))
  s.tap.value={u:.5,v:.5}; s.velocity.value='88'; await new Promise(r=>setTimeout(r,330))
  s.selected.value='p2'; await Vue.nextTick()
  resolve({data:{success:true,data:{distance_ft:999}}}); await Vue.nextTick(); await Vue.nextTick()
  expect(s.estimate.value).toBeNull(); expect(s.validPitch.value).toBe(false)
  s.tap.value={u:.5,v:.5}; s.velocity.value='131'; expect(s.estimateInput.value).toBeNull(); app.unmount()
 })
 it('maps floor and both walls with the same mobile geometry', () => {
  const dims=cageDimensions({width:{ft:14,inch:6},height:{ft:12,inch:0},length:{ft:70,inch:0}},{ft:5,inch:6})
  expect(dims.contactHeightFt).toBe(2.75)
  const mapper=createCageMapper(undefined,dims)
  expect(mapper.mapTap(.5,.85).surface).toBe('floor')
  expect(mapper.mapTap(.2,.5).sprayDeg).toBeLessThan(0)
  expect(mapper.mapTap(.8,.5).sprayDeg).toBeGreaterThan(0)
  expect(cageDimensions(null)).toBeNull()
 })
})

it('resumes cage drill rounds from saved swings and stops at the plan total', async () => {
 const plan=[{drillId:'cage1',drillLabel:'Short Bat',swing_count:2},{drillId:'cage2',drillLabel:'Top Hand',swing_count:1}]
 const {s,api,app}=await screen({plan})
 expect(s.drill.value.drillId).toBe('cage2'); await hit(s);await s.save();expect(s.drill.value).toBeNull()
 await hit(s);await s.save();expect(api.axiosPost.mock.calls.filter(([path])=>path==='result/cage')).toHaveLength(1);app.unmount()
})

it('opens game review only after a confirmed save and retains player scoping',async()=>{
 const {s,api,app}=await screen({role:'player',game:fieldPayload(gameConfig('hs'))});await hit(s);await s.save()
 expect(s.showGame.value).toBe(true);expect(s.visibleRows.value.every(r=>r.user_id==='p1')).toBe(true)
 expect(api.axiosPost.mock.calls.find(([path])=>path==='result/cage')[1]).toMatchObject({practice_id:'session-1',user_id:'p1'});app.unmount()
})
