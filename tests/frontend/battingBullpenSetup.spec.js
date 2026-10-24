import { describe, it, expect, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import * as Vue from 'vue'

const source = compileScript(parse(readFileSync('resources/js/pages/training/CreateBattingBullpen.vue', 'utf8')).descriptor, { id: 'setup-test' }).content
const script = source.replace(/^import .*from ['"].*['"];?\s*$/gm, '').replace('export default', 'return')
const settle = async () => { await Vue.nextTick(); await new Promise(resolve => setTimeout(resolve, 0)) }
async function mountSetup(slug, get, role = 'coach') {
  const teams = [{ id: 't1', name: 'Team One' }, { id: 't2', name: 'Team Two' }]
  const teamStore = { team: teams[0], teams, setTeam: vi.fn() }
  const playerStore = { setPlayers: vi.fn() }
  const trainingStore = { setDataTraining: vi.fn(), setCountBallsTraining: vi.fn(), cleanListPlayer: vi.fn() }
  const api = { axiosGet: get || vi.fn(async path => ({ data: { data: path.endsWith('t1') ? [{ id: 'p1', name: { full: 'Alex' } }, { id: 'p2', name: { full: 'Sam' } }] : [{ id: 'p3', name: { full: 'Lee' } }] } })),
    axiosPost: vi.fn(async () => ({ data: { data: { id: 'session' } } })) }
  const router = { push: vi.fn(async () => {}) }
  const mocks = { computed: Vue.computed, ref: Vue.ref, watch: Vue.watch, useRoute: () => ({ params: { slug } }), useRouter: () => router,
    useUserStore: () => ({ userData: { type: role, id: 'self', first_name: 'Alex' } }), useTeamStore: () => teamStore, usePlayerStore: () => playerStore, useTrainingStore: () => trainingStore, useAxiosAuth: () => api,
    Dialog: {}, DialogPanel: {}, DialogTitle: {}, Layout: {} }
  const component = new Function(...Object.keys(mocks), script)(...Object.values(mocks))
  let state
  const renderer = Vue.createRenderer({ createComment: () => ({}), insert: () => {}, remove: () => {}, parentNode: () => null, nextSibling: () => null })
  const app = renderer.createApp({ setup() { state = component.setup({}, { expose: () => {} }); return () => null } })
  app.mount({}); await settle()
  return { state, api, router, app, teamStore }
}
describe.each(['batting', 'bullpen'])('%s app-style player selection', slug => {
  it('requires players, supports deselection and cancels draft selections', async () => {
    const { state: s, app } = await mountSetup(slug)
    expect(s.canCreate.value).toBe(false)
    s.openPicker(); s.togglePlayer('p1'); s.togglePlayer('p1')
    expect(s.draftIds.value).toEqual([])
    s.togglePlayer('p1'); s.applyPlayers()
    expect(s.canCreate.value).toBe(true)
    s.openPicker(); s.togglePlayer('p2'); s.pickerOpen.value = false; s.openPicker()
    expect(s.draftIds.value).toEqual(['p1'])
    s.removePlayer('p1'); expect(s.canCreate.value).toBe(false); app.unmount()
  })
  it('creates once with selected order and optional notes, then opens scoring', async () => {
    const { state: s, api, router, app } = await mountSetup(slug)
    s.openPicker(); s.togglePlayer('p1'); s.togglePlayer('p2'); s.applyPlayers(); s.movePlayer(1, -1)
    await Promise.all([s.createPractice(), s.createPractice()])
    expect(api.axiosPost).toHaveBeenCalledTimes(1)
    expect(api.axiosPost).toHaveBeenCalledWith('training', { team: 't1', type: slug === 'bullpen' ? 'P' : 'B', note: slug === 'bullpen' ? 'Bullpen session' : 'Batting session', players: [{ id: 'p2', sort: 0 }, { id: 'p1', sort: 1 }] })
    expect(router.push).toHaveBeenCalledWith({ path: `/track/${slug}`, query: { session: 'session' } }); app.unmount()
  })
  it('clears selection on team change and submits the correct team', async () => {
    const { state: s, api, teamStore, app } = await mountSetup(slug)
    s.openPicker(); s.togglePlayer('p1'); s.applyPlayers()
    s.teamId.value = 't2'; await settle()
    expect(s.selected.value).toEqual([]); expect(s.roster.value.map(p => p.id)).toEqual(['p3'])
    s.openPicker(); s.togglePlayer('p3'); s.applyPlayers(); s.note.value = ' Stretch first '; await s.createPractice()
    expect(api.axiosPost.mock.calls[0][1]).toMatchObject({ team: 't2', note: 'Stretch first', players: [{ id: 'p3', sort: 0 }] })
    expect(teamStore.setTeam).toHaveBeenCalledWith({ id: 't2', name: 'Team Two' }); app.unmount()
  })
  it('ignores a late roster response from a previously selected team', async () => {
    let resolveFirst
    const get = vi.fn(path => path.endsWith('t1') ? new Promise(resolve => { resolveFirst = resolve }) : Promise.resolve({ data: { data: [{ id: 'p3' }] } }))
    const { state: s, app } = await mountSetup(slug, get)
    s.teamId.value = 't2'; await settle()
    resolveFirst({ data: { data: [{ id: 'p1' }] } }); await settle()
    expect(s.roster.value.map(p => p.id)).toEqual(['p3']); expect(s.loading.value).toBe(false); app.unmount()
  })
  it('blocks creation when the roster fails to load', async () => {
    const { state: s, api, app } = await mountSetup(slug, vi.fn().mockRejectedValue(Error('unavailable')))
    expect(s.error.value).toContain('Unable to load'); await s.createPractice()
    expect(api.axiosPost).not.toHaveBeenCalled(); app.unmount()
  })
  it('does not create duplicates after an unconfirmed request and lets Cancel open the session list', async () => {
    const { state: s, api, router, app } = await mountSetup(slug)
    s.openPicker(); s.togglePlayer('p1'); s.applyPlayers()
    api.axiosPost.mockRejectedValueOnce(Error('timeout'))
    await s.createPractice(); await s.createPractice()
    expect(api.axiosPost).toHaveBeenCalledTimes(1)
    expect(s.canCreate.value).toBe(false)
    await s.cancel(); expect(router.push).toHaveBeenCalledWith(`/training/${slug}`)
    app.unmount()
  })

})

describe('Cage setup', () => {
  it('validates feet/inches and creates the cage with selected hitters', async () => {
    const { state: s, api, router, app } = await mountSetup('cage')
    s.openPicker(); s.togglePlayer('p1'); s.applyPlayers()
    s.dimensions.value.width.inch = 12; await s.createPractice(); expect(api.axiosPost).not.toHaveBeenCalled()
    s.dimensions.value.width.inch = 6; await s.createPractice()
    expect(api.axiosPost).toHaveBeenCalledWith('training', expect.objectContaining({ type: 'C', cage: { width: { ft: 14, inch: 6 }, height: { ft: 12, inch: 0 }, length: { ft: 70, inch: 0 } }, players: [{ id: 'p1', sort: 0 }] }))
    expect(router.push).toHaveBeenCalledWith({ path: '/track/cage', query: { session: 'session' } }); app.unmount()
  })
})

it('lets a player create a personal cage with dimensions without a coach roster', async () => {
 const { state:s, api, app } = await mountSetup('cage', undefined, 'player')
 expect(api.axiosGet).not.toHaveBeenCalled(); expect(s.selectedIds.value).toEqual(['self'])
 await s.createPractice(); expect(api.axiosPost).toHaveBeenCalledWith('training', expect.objectContaining({ team:null, type:'C', players:[{id:'self',sort:0}], cage:expect.any(Object) })); app.unmount()
})
