import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(process.cwd())
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')

describe('player profile claim flow', () => {
  it('uses mobile number and team code without an SMS verification step', () => {
    const login = read('resources/js/components/login/LoginTemplate.vue')

    expect(login).toContain("api_url + 'player/join'")
    expect(login).toContain("api_url + 'player/set-credentials'")
    expect(login).toContain('No text message is required.')
    expect(login).not.toContain('player/join/verify')
    expect(login).not.toContain('verification_code')
    expect(login).not.toContain('verification_required')
  })

  it('does not expose the retired verification route', () => {
    const routes = read('routes/api.php')

    expect(routes).toContain("post('join', JoinTeamByCode::class)")
    expect(routes).not.toContain("post('join/verify'")
    expect(routes).not.toContain('VerifyTeamJoin')
  })
})
