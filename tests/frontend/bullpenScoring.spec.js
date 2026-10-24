import { describe, it, expect, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { createBullpenSubmission } from '@/utils/bullpenSubmission'
import { getPitchInfoFromCoordinate } from '@/utils/bullpenCoordinates'

const source = compileScript(parse(readFileSync('resources/js/pages/training/BullpenScoring.vue', 'utf8')).descriptor, { id: 'bp-test' }).content
const script = source.replace(/^import .*from ['"].*['"];?\s*$/gm, '').replace('export default', 'return')
async function screen({ role = 'coach', completed = false, scripted = false, type = 'P', fails = false, empty = false } = {}) {
  const session = { id: 'session-1', team_id: 'team-1', type, is_completed: completed, is_scripted: scripted, players: [{ id: 'p1', name: { full: 'One' } }, { id: 'p2', name: { full: 'Two' } }] }
  const rows = [{ id: 'r1', pitcher_id: 'p1', is_strike: true, miles_per_hour: 80 }, { id: 'r2', pitcher_id: 'p2', is_strike: false, miles_per_hour: 0 }]
  let attempts = 0, leave
  const api = {
    axiosGet: vi.fn(async path => {
      if (!path.startsWith('training/') && empty) throw { response: { status: 404, data: { code: '020-E' } } }
      return { data: { data: path.startsWith('training/') ? session : { ball_x_ball: rows } } }
    }),
    axiosPost: vi.fn(async (path, body) => {
      if (fails && attempts++ === 0) throw Error('lost response')
      return { data: { status: 'success', client_request_id: body.client_request_id, data: { ...body, id: 'saved' } } }
    }),
    axiosPut: vi.fn(async () => ({})),
  }
  const router = { push: vi.fn(async () => {}) }
  const mocks = { computed: Vue.computed, onMounted: Vue.onMounted, onUnmounted: Vue.onUnmounted, window: { addEventListener: vi.fn(), removeEventListener: vi.fn() }, ref: Vue.ref, watch: Vue.watch, useRoute: () => ({ query: { session: session.id } }), useRouter: () => router,
    onBeforeRouteLeave: fn => { leave = fn },
    useTrainingStore: () => ({ trainingActive: {}, setDataTraining: vi.fn() }), useUserStore: () => ({ userData: { id: role === 'player' ? 'p1' : 'coach1', type: role } }),
    usePlayerStore: () => ({ players: [] }), useAxiosAuth: () => api, Layout: {}, LivePracticeStats: {},
    extractSessionRows, getPitchInfoFromCoordinate, createBullpenSubmission: post => createBullpenSubmission(post, () => 'pitch-key'),
    plannerSessionReturn: (storage, id, user, fallback) => fallback, sessionStorage: {},
  }
  const component = new Function(...Object.keys(mocks), script)(...Object.values(mocks))
  let state
  const renderer = Vue.createRenderer({ createComment: () => ({}), remove: () => {}, insert: () => {}, parentNode: () => null, nextSibling: () => null })
  const app = renderer.createApp({ setup() { state = component.setup({}, { expose: () => {} }); return () => null } })
  app.mount({}); await new Promise(resolve => setTimeout(resolve, 0))
  return { s: state, api, router, app, leave: () => leave() }
}
function pitch(s) { s.location.value = 1830; s.pitchType.value = 'FB'; s.velocity.value = '88' }
describe('Bullpen web scoring', () => {
  it('matches app location, type, contact, and velocity payload and updates live totals', async () => {
    const { s, api, app } = await screen(); pitch(s)
    expect(s.teamStats.value).toEqual({ count: 2, percent: 50 })
    await Promise.all([s.save(), s.save()])
    expect(api.axiosPost).toHaveBeenCalledTimes(1)
    expect(api.axiosPost).toHaveBeenCalledWith('result/bullpen', expect.objectContaining({ practice_id: 'session-1', team_id: 'team-1', pitcher_id: 'p1', pitch_mark: 1830, type_throw: 'FB', trajectory: 'TK', miles_per_hour: 88, is_strike: true, zone: 'S', client_request_id: 'pitch-key' }))
    expect(s.stats.value).toMatchObject({ count: 2, average: 84, top: 88, strikes: 2 })
    expect(s.teamStats.value).toEqual({ count: 3, percent: 67 })
    expect(s.pitchType.value).toBe(''); expect(s.location.value).toBe(0)
    s.selected.value = 'p2'; await Vue.nextTick(); expect(s.stats.value.average).toBeNull(); app.unmount()
  })
  it('requires location and pitch type, but accepts unmeasured velocity as zero', async () => {
    const { s, api, app } = await screen({ empty: true })
    await s.save(); expect(api.axiosPost).not.toHaveBeenCalled()
    pitch(s); s.velocity.value = '9'; await s.save(); expect(api.axiosPost).not.toHaveBeenCalled()
    s.velocity.value = ''; await s.save()
    expect(api.axiosPost.mock.calls[0][1].miles_per_hour).toBe(0); expect(s.stats.value.average).toBeNull(); app.unmount()
  })
  it('retains the exact pitch for retry and prevents navigating away from an uncertain save', async () => {
    const { s, api, app, leave } = await screen({ fails: true }); pitch(s)
    await s.save(); expect(s.pending.value).not.toBeNull(); expect(leave()).toBe(false)
    expect(s.writable.value).toBe(false)
    await s.save()
    expect(api.axiosPost.mock.calls[0][1]).toEqual(api.axiosPost.mock.calls[1][1])
    expect(s.ownRows.value).toHaveLength(2); expect(leave()).toBe(true); app.unmount()
  })
  it.each([{ completed: true }, { scripted: true }, { type: 'B' }])('blocks incompatible or completed sessions %j', async options => {
    const { s, api, app } = await screen(options); pitch(s); await s.save(); expect(api.axiosPost).not.toHaveBeenCalled(); app.unmount()
  })
  it('scopes player stats, preserves original session identity, and links the report', async () => {
    const { s, api, router, app } = await screen({ role: 'player' })
    expect(s.players.value).toHaveLength(1); expect(s.visibleRows.value).toHaveLength(1)
    expect(s.teamStats.value).toEqual({ count: 1, percent: 100 })
    expect(api.axiosGet).toHaveBeenCalledWith('statistics/session-1/bullpen', { player: 1 })
    await s.report(); expect(router.push).toHaveBeenCalledWith('/session/report/session-1/bullpen?scope=player')
    s.note.value = 'Complete'; await s.finish()
    expect(api.axiosPut).toHaveBeenCalledWith('training/session-1', { end_note: 'Complete', is_completed: true }); app.unmount()
  })
  it('uses mobile strike boundaries and pitch sides', () => {
    expect(getPitchInfoFromCoordinate(1830)).toEqual({ is_strike: true, pitch_side: 'MC', zone: 'S' })
    expect(getPitchInfoFromCoordinate(1)).toEqual({ is_strike: false, pitch_side: 'TL', zone: 'B' })
    expect(getPitchInfoFromCoordinate((15 - 1) * 60 + 16).is_strike).toBe(true)
    expect(getPitchInfoFromCoordinate((14 - 1) * 60 + 16).is_strike).toBe(false)
  })
})
