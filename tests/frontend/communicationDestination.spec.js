import { describe, it, expect, beforeEach } from 'vitest'
import { safeCommunicationDestination, rememberCommunicationDestination, pendingCommunicationDestination, consumeCommunicationDestination, retainInvitationToken, clearInvitationToken } from '../../resources/js/utils/communicationDestination.js'
const values = new Map()
const storage = { getItem: k => values.get(k), setItem: (k,v) => values.set(k,v), removeItem: k => values.delete(k) }
beforeEach(() => { values.clear(); clearInvitationToken() })
describe('communication authentication continuation', () => {
  it.each(['//evil.example','https://evil.example','/\\evil.example','/settings?redirect=https://evil.example','/invitations/accept#token=secret','/settings?token=secret','/admin','/settings%0a','/settings?tab=%0a'])('rejects unsafe destination %s', path => expect(safeCommunicationDestination(path)).toBeNull())
  it('preserves a workout through login and consumes it once', () => {
    const path='/player-dashboard?tab=workout&date=2026-10-16&plan=plan-id'
    expect(rememberCommunicationDestination(path,storage)).toBe(true)
    expect(pendingCommunicationDestination(storage)).toBe(path)
    expect(consumeCommunicationDestination('/dashboard',storage)).toBe(path)
    expect(consumeCommunicationDestination('/dashboard',storage)).toBe('/dashboard')
  })
  it('keeps invitation tokens only in memory while returning through sign-in', () => {
    const token='a'.repeat(64)
    retainInvitationToken(token); rememberCommunicationDestination('/invitations/accept',storage)
    expect(JSON.stringify([...values])).not.toContain(token)
    expect(consumeCommunicationDestination('/dashboard',storage)).toBe('/invitations/accept')
    expect(retainInvitationToken(null)).toBe(token)
    clearInvitationToken(); expect(retainInvitationToken(null)).toBe('')
  })
  it('expires old continuations and tolerates unavailable storage', () => {
    values.set('fmtrx.communication.destination',JSON.stringify({path:'/settings',at:Date.now()-1800001}))
    expect(pendingCommunicationDestination(storage)).toBeNull()
    expect(consumeCommunicationDestination('/dashboard',{getItem(){throw Error()},removeItem(){throw Error()}})).toBe('/dashboard')
  })
})
