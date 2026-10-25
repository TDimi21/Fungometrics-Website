import {beforeEach, expect, it, vi} from 'vitest'
import {createPinia, setActivePinia} from 'pinia'
const api = vi.hoisted(() => {
  globalThis.sessionStorage = {getItem: () => null, setItem() {}, removeItem() {}}
  return {axiosGet: vi.fn(), axiosPatch: vi.fn()}
})
vi.mock('@/composables/axios-auth.js', () => ({useAxiosAuth: () => api}))
import {useTeamStore} from '@/store/team'
import {useUserStore} from '@/store/user'
let preferences
beforeEach(() => {
  setActivePinia(createPinia()); vi.clearAllMocks()
  useUserStore().setData({id: 'coach', type: 'coach'})
  preferences = {default_team_id: 'b', active_team_id: 'a', initial_team_id: 'b'}
  api.axiosGet.mockImplementation(async url => ({data: {data: url === 'coach/teams' ? [{id: 'a', name: 'Varsity'}, {id: 'b', name: 'JV'}] : {...preferences}}}))
  api.axiosPatch.mockImplementation(async (url, patch) => { preferences = {...preferences, ...patch}; return {data: {data: preferences}} })
})
it('uses default on login and keeps active separate until explicitly saved', async () => {
  const store = useTeamStore()
  store.setTeam({id: 'a'})
  await store.initializePreferences({login: true})
  expect(store.active_team_id).toBe('b')
  store.setTeam(store.teams[0])
  expect(store.active_team_id).toBe('a')
  expect(store.default_team_id).toBe('b')
  await store.setDefaultTeam()
  expect(store.default_team_id).toBe('a')
  expect(preferences.active_team_id).toBe('a')
})
it('preserves active team across navigation and falls back from revoked selections', async () => {
  const store = useTeamStore()
  await store.initializePreferences({login: true})
  store.setTeam(store.teams[0])
  await store.initializePreferences()
  expect(store.active_team_id).toBe('a')
  store.setTeam({id: 'revoked'})
  await store.initializePreferences()
  expect(store.active_team_id).toBe('b')
})
it('does not claim a default was saved when request fails', async () => {
  const store = useTeamStore()
  await store.initializePreferences({login: true})
  store.setTeam(store.teams[0])
  api.axiosPatch.mockRejectedValue(new Error('offline'))
  await store.setDefaultTeam()
  expect(store.default_team_id).toBe('b')
  expect(store.preferenceError).toContain('Could not save')
})
it('does not initialize coach preferences for players', async () => {
  useUserStore().setData({id: 'player', type: 'player'})
  await useTeamStore().initializePreferences()
  expect(api.axiosGet).not.toHaveBeenCalled()
})
