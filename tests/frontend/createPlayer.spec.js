import { beforeEach, describe, expect, it, vi } from 'vitest'
import fs from 'node:fs'
import axios from 'axios'
import { getAuthToken } from '../../resources/js/utils/authToken.js'
import { useCreatePlayer, resolvePlayerTeamId, playerCreationError } from '../../resources/js/composables/create-player.js'
vi.mock('axios', () => ({ default: { post: vi.fn() } }))
vi.mock('../../resources/js/utils/authToken.js', () => ({ getAuthToken: vi.fn() }))
const teamId = '12345678-1234-1234-1234-123456789012'
const linkId = '87654321-4321-4321-4321-210987654321'
beforeEach(() => { vi.clearAllMocks(); getAuthToken.mockReturnValue(''); axios.post.mockResolvedValue({ data: {status: 'success'} }) })
describe('coach player creation request', () => {
  it('uses the real team UUID and cookie auth without an empty Bearer header', async () => {
    const form = new FormData(); form.set('team', linkId); form.set('name[first]', 'Player'); form.set('send_invitation', '0')
    await useCreatePlayer()({ id: linkId, id_team: teamId }, form)
    const [url, sent, config] = axios.post.mock.calls[0]
    expect(url.endsWith('coach/add/players')).toBe(true)
    expect(sent.get('team')).toBe(teamId)
    expect(sent.get('send_invitation')).toBe('0')
    expect(config.withCredentials).toBe(true)
    expect(config.headers).not.toHaveProperty('Authorization')
    expect(config.headers).not.toHaveProperty('Content-Type')
  })
  it('resolves the current token at submit time and keeps bearer compatibility', async () => {
    const submit = useCreatePlayer()
    getAuthToken.mockReturnValue('valid-token')
    await submit({ id: teamId }, new FormData())
    expect(axios.post.mock.calls[0][2].headers.Authorization).toBe('Bearer valid-token')
  })
  it.each([{}, {id: 'undefined'}, {id: teamId, id_team: 'bad'}])('rejects an invalid team before posting', async team => {
    await expect(useCreatePlayer()(team, new FormData())).rejects.toThrow('Select a valid team')
    expect(axios.post).not.toHaveBeenCalled()
  })
  it('supports direct team objects and explains empty 403 and capacity errors', () => {
    expect(resolvePlayerTeamId({ id: teamId })).toBe(teamId)
    expect(playerCreationError({response: {status: 403, data: {message: ''}}})).toContain('permission')
    expect(playerCreationError({response: {status: 403, data: {message: 'Player limit reached.'}}})).toBe('Player limit reached.')
    expect(playerCreationError({response: {status: 401}})).toContain('Sign in')
  })
  it.each(['resources/js/pages/roster/HomeRoster.vue', 'resources/js/layout/Layout.vue', 'resources/js/components/roster/PlayerCard.vue'])('routes each creation entry point through shared authenticated submission: %s', file => {
    const source = fs.readFileSync(file, 'utf8')
    expect(source).toMatch(/createPlayer\(team.value, dataForm(?:, (?:player|dataPlayer))?\)/)
    expect(source).not.toMatch(/\.post\([^\n]*coach\/add\/players/)
  })
})

describe('player creation policy acceptance', () => {
  const policies = {terms_version:'v1', privacy_version:'v2', acknowledgment_version:'v3'}
  it('blocks an unchecked agreement before sending a creation request', async () => {
    await expect(useCreatePlayer()({id:teamId}, new FormData(), {policy_accepted:false,policy_info:policies})).rejects.toThrow('check')
    expect(axios.post).not.toHaveBeenCalled()
  })
  it('posts the exact versions shown with an explicitly checked agreement', async () => {
    await useCreatePlayer()({id:teamId}, new FormData(), {policy_accepted:true,policy_info:policies})
    const sent = axios.post.mock.calls[0][1]
    expect(sent.get('policy_accepted')).toBe('1')
    expect(sent.get('terms_version')).toBe('v1')
    expect(sent.get('privacy_version')).toBe('v2')
    expect(sent.get('acknowledgment_version')).toBe('v3')
  })
})
