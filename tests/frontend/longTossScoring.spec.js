import {guidedProgress,plannedEntry,progressKey} from '@/features/long-toss/guidedProgress'
import {longTossFeet} from '@/features/long-toss/distanceUnits'
import { describe, it, expect, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { isLongTossScoringRoute, summarizeThrows, playerThrows, validDistance } from '@/utils/longTossScoring'
import { extractSessionRows } from '@/utils/sessionReportRows'

const source = compileScript(parse(readFileSync('resources/js/pages/training/LongTossScoring.vue', 'utf8')).descriptor, { id: 'lt-test' }).content
const script = source.replace(/^import .*from ['"].*['"];?\s*$/gm, '').replace('export default', 'return')
async function screen({ role = 'coach', completed = false, fails = false, noSession = false } = {}) {
  const session = { long_toss_mode:'guided', id: 'session-1', user_id: role === 'player' ? 'p1' : 'coach1', team_id: role === 'player' ? null : 'team-1', modes: 'LT', is_completed: completed, players: [{ id: 'p1', sort: 0 }, { id: 'p2', sort: 1 }] }
  const rows = [{ id: 'r1', user_id: 'p1', set: 2, hop: 0, distance: 120 }, { id: 'r2', user_id: 'p2', set: 1, hop: 1, distance: 180 }]
  const api = {
    axiosGet: vi.fn(async path => ({ data: { data: path.startsWith('training/') ? session : { ball_x_ball: rows } } })),
    axiosPost: vi.fn(async (path, body) => { if (fails) throw Error('network'); return { data: { data: { result: { ...body, id: 'saved' } } } } }),
    axiosPut: vi.fn(async () => ({})),
  }
  const router = { push: vi.fn(async () => {}) }
  const training = { trainingActive: noSession ? {} : { id: session.id }, setDataTraining: vi.fn() }
  const mocks = { computed: Vue.computed, onMounted: Vue.onMounted, ref: Vue.ref, watch: Vue.watch, useRoute: () => ({ query: {} }), useRouter: () => router,
    useTrainingStore: () => training, useUserStore: () => ({ userData: { id: role === 'player' ? 'p1' : 'coach1', type: role } }),
    useTeamStore: () => ({ team: { id: 'team-1' } }), usePlayerStore: () => ({ players: [] }), useAxiosAuth: () => api, Layout: {}, TrainingSessionStatsTabs: {}, GuidedLongToss: {},
    extractSessionRows, playerThrows, summarizeThrows, validDistance, longTossFeet, guidedProgress,plannedEntry,progressKey,
    plannerSessionReturn: (storage, id, user, fallback) => fallback, sessionStorage: {},
  }
  const component = new Function(...Object.keys(mocks), script)(...Object.values(mocks))
  let state
  const renderer = Vue.createRenderer({ createComment: () => ({}), remove: () => {}, insert: () => {}, parentNode: () => null, nextSibling: () => null })
  const app = renderer.createApp({ setup() { state = component.setup({}, { expose: () => {} }); return () => null } })
  app.mount({})
  await new Promise(resolve => setTimeout(resolve, 0))
  return { state, api, router, app }
}

describe('Long Toss web scoring', () => {
  it('opens only Long Toss standalone scoring and setup', () => {
    expect(isLongTossScoringRoute({ path: '/track/training-mode/LT' })).toBe(true)
    expect(isLongTossScoringRoute({ path: '/create/mode', query: { mode: 'LT' } })).toBe(true)
    for (const path of ['/track/batting', '/track/training-mode/EV', '/create/mode']) expect(isLongTossScoringRoute({ path })).toBe(false)
  })
  it('shows missing measurements as missing, and compares only the selected player', () => {
    expect(summarizeThrows([])).toEqual({ count: 0, top: null, average: null })
    expect(summarizeThrows(playerThrows([{ user_id: 'p1', distance: 100 }, { user_id: 'p2', distance: 200 }], 'p1')).average).toBe(100)
    for (const value of ['', '0', '-1', '1.5', 'abc', '999999999999']) expect(validDistance(value)).toBe(false)
    expect(validDistance('150')).toBe(true)
  })
  it('loads server counters, changes players, saves to the app endpoint, and clears entry only on success', async () => {
    const { state: s, api, app } = await screen()
    expect(s.currentSet.value).toBe(2); expect(s.inSet.value).toBe(1)
    s.selected.value = 'p2'; await Vue.nextTick()
    expect(s.currentSet.value).toBe(1)
    s.sets.value.p2 = 2
    expect(s.inSet.value).toBe(0)
    expect(s.sets.value.p1).toBe(2)
    s.guidedAccess.value = {canRecord:true,finishReady:false}
    s.distance.value = '210'; s.hop.value = 2
    await Promise.all([s.save(), s.save()])
    expect(api.axiosPost).toHaveBeenCalledTimes(1)
    expect(api.axiosPost).toHaveBeenCalledWith('result/longtoss', { practice_id: 'session-1', team_id: 'team-1', user_id: 'p2', set: 2, distance: 210, hop: 2 })
    expect(s.stats.value.count).toBe(2); expect(s.distance.value).toBe(''); expect(s.hop.value).toBe(0)
    app.unmount()
  })
  it('preserves an uncertain throw and requires reconciliation before retry', async () => {
    const { state: s, api, app } = await screen({ fails: true })
    s.guidedAccess.value = {canRecord:true,finishReady:false}
    s.distance.value = '160'; await s.save(); await s.save()
    expect(s.distance.value).toBe('160'); expect(s.ready.value).toBe(false)
    expect(api.axiosPost).toHaveBeenCalledTimes(1); expect(s.stats.value.count).toBe(1)
    app.unmount()
  })
  it('does not save into completed or absent sessions', async () => {
    for (const options of [{ completed: true }, { noSession: true }]) {
      const { state: s, api, app } = await screen(options)
      s.distance.value = '150'; await s.save(); expect(api.axiosPost).not.toHaveBeenCalled(); app.unmount()
    }
  })
  it.each(['coach', 'player'])('opens session stats for %s without ending the session', async role => {
    const { state: s, api, router, app } = await screen({ role })
    expect(s.statsRows.value).toHaveLength(role === 'player' ? 1 : 2)
    await s.report()
    expect(router.push).toHaveBeenCalledWith(`/session/report/session-1/long_toss${role === 'player' ? '?scope=player' : ''}`)
    expect(api.axiosPut).not.toHaveBeenCalled()
    router.push.mockClear()
    s.busy.value = true
    await s.report()
    expect(router.push).not.toHaveBeenCalled()
    app.unmount()
  })
  it('completes the original session and opens its report', async () => {
    const { state: s, api, router, app } = await screen()
    s.guidedAccess.value = {canRecord:false,finishReady:true}
    s.note.value = ' Good session '; await s.finish()
    expect(api.axiosPut).toHaveBeenCalledWith('training/session-1', { end_note: 'Good session', is_completed: true })
    expect(router.push).toHaveBeenCalledWith('/session/report/session-1/long_toss'); app.unmount()
  })
})

it('makes team and other-owned sessions read-only for players', async () => {
  const {state: s, api, app} = await screen({role:'player'})
  expect(s.writable.value).toBeTruthy()
  s.session.value.team_id = 'team-1'
  s.session.value.is_player_shared = true
  expect(s.writable.value).toBeTruthy()
  s.session.value.is_player_shared = false
  expect(s.writable.value).toBeFalsy()
  s.note.value = 'Done'; await s.finish()
  expect(api.axiosPut).not.toHaveBeenCalled()
  s.session.value.team_id = null; s.session.value.user_id = 'other'
  expect(s.writable.value).toBeFalsy()
  app.unmount()
})

it('requires a guided check-in before throws and a post-session check-in before ending', async () => {
  const {state:s, api, app}=await screen()
  s.distance.value='150'; await s.save(); await s.finish()
  expect(api.axiosPost).not.toHaveBeenCalled()
  expect(api.axiosPut).not.toHaveBeenCalled()
  app.unmount()
})

it('regular sessions save throws without guided check-ins', async()=>{
 const {state:s,api,app}=await screen();s.session.value.long_toss_mode='regular';s.distance.value='150';
 await s.save();expect(api.axiosPost).toHaveBeenCalledTimes(1);app.unmount();
})

it('converts yard entries into stored feet and rejects distances over 500 feet',async()=>{
 const {state:s,api,app}=await screen();s.session.value.long_toss_mode='regular';s.distanceUnit.value='yd';s.distance.value='100';
 await s.save();expect(api.axiosPost.mock.calls[0][1].distance).toBe(300);
 api.axiosPost.mockClear();s.distance.value='167';await s.save();expect(api.axiosPost).not.toHaveBeenCalled();app.unmount();
});

it('prefills the active set, preserves manual edits until progress advances, and isolates players',async()=>{
 const {state:s,app}=await screen();
 const stages=[{set_number:1,phase:'Going Out',distance_ft:30,throws:3},{set_number:2,phase:'Going Out',distance_ft:100,throws:3}];
 const guide={player_id:'p1',status:'active',stage:0,recorded_throws:0,stage_throws:0,current_stage:stages[0],snapshot:{stages},can_record:true};
 s.updateGuidedAccess({canRecord:true,finishReady:false,guide});expect(s.distance.value).toBe('30');expect(s.progress.value.recorded).toBe(0);
 s.distance.value='35';s.updateGuidedAccess({canRecord:false,finishReady:false,guide});expect(s.distance.value).toBe('35');
 s.updateGuidedAccess({canRecord:true,finishReady:false,guide:{...guide,stage:1,recorded_throws:3,current_stage:stages[1]}});
 expect(s.distance.value).toBe('100');expect(s.currentSet.value).toBe(2);expect(s.progress.value.remaining).toBe(3);
 s.selected.value='p2';await Vue.nextTick();s.updateGuidedAccess({canRecord:true,finishReady:false,guide});expect(s.distance.value).toBe('');
 s.updateGuidedAccess({canRecord:true,finishReady:false,guide:{...guide,player_id:'p2'}});expect(s.distance.value).toBe('30');
 s.distanceUnit.value='yd';await Vue.nextTick();expect(s.distance.value).toBe('10');app.unmount();
});
