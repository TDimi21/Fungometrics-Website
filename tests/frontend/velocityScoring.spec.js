import { describe, it, expect, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { isVelocityScoringRoute, playerThrows, validDistance } from '@/utils/longTossScoring'
import { extractSessionRows } from '@/utils/sessionReportRows'

const source = compileScript(parse(readFileSync('resources/js/pages/training/VelocityScoring.vue', 'utf8')).descriptor, { id: 'lt-test' }).content
const script = source.replace(/^import .*from ['"].*['"];?\s*$/gm, '').replace('export default', 'return')
async function screen({ role = 'coach', mode = 'EV', wrongMode = false, completed = false, fails = false, noSession = false } = {}) {
  const session = { id: 'session-1', team_id: 'team-1', modes: wrongMode ? 'LT' : mode, is_completed: completed, players: [{ id: 'p1', sort: 0 }, { id: 'p2', sort: 1 }] }
  const rows = [{ id: 'r1', user_id: 'p1', set: 2, trajectory: 'GB', weight: 5, velocity: 60 }, { id: 'r2', user_id: 'p2', set: 1, trajectory: 'GB', weight: 5, velocity: 80 }]
  const api = {
    axiosGet: vi.fn(async path => ({ data: { data: path.startsWith('training/') ? session : { ball_x_ball: rows } } })),
    axiosPost: vi.fn(async (path, body) => { if (fails) throw Error('network'); return { data: { data: { result: { ...body, id: 'saved' } } } } }),
    axiosPut: vi.fn(async () => ({})),
  }
  const router = { push: vi.fn(async () => {}) }
  const training = { trainingActive: noSession ? {} : { id: session.id }, setDataTraining: vi.fn() }
  const mocks = { computed: Vue.computed, onMounted: Vue.onMounted, ref: Vue.ref, watch: Vue.watch, useRoute: () => ({ query: {} }), useRouter: () => router,
    useTrainingStore: () => training, useUserStore: () => ({ userData: { id: role === 'player' ? 'p1' : 'coach1', type: role } }),
    useTeamStore: () => ({ team: { id: 'team-1' } }), usePlayerStore: () => ({ players: [] }), useAxiosAuth: () => api, Layout: {}, TrainingSessionStatsTabs: {},
    extractSessionRows, playerThrows, validDistance,
    plannerSessionReturn: (storage, id, user, fallback) => fallback, sessionStorage: {},
  }
  const component = new Function(...Object.keys(mocks), script)(...Object.values(mocks))
  let state
  const renderer = Vue.createRenderer({ createComment: () => ({}), remove: () => {}, insert: () => {}, parentNode: () => null, nextSibling: () => null })
  const app = renderer.createApp({ setup() { state = component.setup({ mode }, { expose: () => {} }); return () => null } })
  app.mount({})
  await new Promise(resolve => setTimeout(resolve, 0))
  return { state, api, router, app }
}

describe.each(['EV', 'WB'])('%s app-compatible web scoring', mode => {
  it('allows setup and scoring without unlocking other session modes', () => {
    expect(isVelocityScoringRoute({ path: `/track/training-mode/${mode}` })).toBe(true)
    expect(isVelocityScoringRoute({ path: '/create/mode', query: { mode } })).toBe(true)
    expect(isVelocityScoringRoute({ path: '/track/batting' })).toBe(false)
  })
  it('restores sets, compares the selected player, and saves exactly once to its mode endpoint', async () => {
    const { state: s, api, app } = await screen({ mode })
    expect(s.currentSet.value).toBe(2)
    expect(s.stats.value.average).toBe(60); expect(s.teamStats.value.average).toBe(70)
    expect(s.byGroup(s.ownRows.value, mode === 'EV' ? 'GB' : 5)).toBe(60)
    s.velocity.value = '90'; s.trajectory.value = 'LD'; s.weight.value = 7
    await Promise.all([s.save(), s.save()])
    expect(api.axiosPost).toHaveBeenCalledTimes(1)
    expect(api.axiosPost).toHaveBeenCalledWith(`result/${mode === 'EV' ? 'exitvelocity' : 'weightball'}`, {
      practice_id: 'session-1', team_id: 'team-1', user_id: 'p1', velocity: 90, set: 2,
      ...(mode === 'EV' ? { trajectory: 'LD' } : { weight: 7 }),
    })
    expect(s.velocity.value).toBe(''); expect(s.stats.value.count).toBe(2)
    if (mode === 'WB') expect(s.weight.value).toBe(7)
    s.selected.value = 'p2'; await Vue.nextTick(); expect(s.currentSet.value).toBe(1); expect(s.stats.value.count).toBe(1)
    app.unmount()
  })
  it('rejects missing trajectory or invalid weight', async () => {
    const { state: s, api, app } = await screen({ mode })
    s.velocity.value = '80'; s.weight.value = 0; await s.save()
    expect(api.axiosPost).not.toHaveBeenCalled(); app.unmount()
  })
  it('preserves input on an uncertain save without automatically retrying', async () => {
    const { state: s, api, app } = await screen({ mode, fails: true })
    s.velocity.value = '80'; s.trajectory.value = 'FB'; await s.save(); await s.save()
    expect(s.velocity.value).toBe('80'); expect(s.ready.value).toBe(false)
    expect(api.axiosPost).toHaveBeenCalledTimes(1); app.unmount()
  })
  it('blocks completed, absent, and wrong-mode sessions', async () => {
    for (const options of [{ completed: true }, { noSession: true }, { wrongMode: true }]) {
      const { state: s, api, app } = await screen({ mode, ...options })
      s.velocity.value = '80'; s.trajectory.value = 'GB'; await s.save()
      expect(api.axiosPost).not.toHaveBeenCalled(); app.unmount()
    }
  })
  it.each(['coach', 'player'])('opens session stats for %s without ending the session', async role => {
    const { state: s, api, router, app } = await screen({ mode, role })
    expect(s.statsRows.value).toHaveLength(role === 'player' ? 1 : 2)
    await s.report()
    expect(router.push).toHaveBeenCalledWith(`/session/report/session-1/${mode === 'EV' ? 'exit_velocity' : 'weight_ball'}${role === 'player' ? '?scope=player' : ''}`)
    expect(api.axiosPut).not.toHaveBeenCalled()
    router.push.mockClear()
    s.busy.value = true
    await s.report()
    expect(router.push).not.toHaveBeenCalled()
    app.unmount()
  })
  it('finishes the original session and opens the correct report', async () => {
    const { state: s, api, router, app } = await screen({ mode })
    s.note.value = 'Done'; await s.finish()
    expect(api.axiosPut).toHaveBeenCalledWith('training/session-1', { end_note: 'Done', is_completed: true })
    expect(router.push).toHaveBeenCalledWith(`/session/report/session-1/${mode === 'EV' ? 'exit_velocity' : 'weight_ball'}`)
    app.unmount()
  })
})
