// Store only a validated frontend destination, never an invitation token or account data.
const key = 'fmtrx.communication.destination'
let invitationToken = ''
let invitationDraft = { email: '', claimCode: '' }
const paths = new Set(['/dashboard', '/player-dashboard', '/assessment-reports', '/development/player', '/settings', '/invitations/accept'])
export function safeCommunicationDestination(value) {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || /[\\\\\x00-\x20#]/.test(value)) return null
  try {
    const url = new URL(value, 'https://app.fmtrx.com')
    if (url.origin !== 'https://app.fmtrx.com' || !paths.has(url.pathname)) return null
    for (const [name, val] of url.searchParams) {
      if (!['tab', 'date', 'plan', 'player', 'assessment'].includes(name) || !/^[a-zA-Z0-9_-]{1,80}$/.test(val)) return null
    }
    return url.pathname + url.search
  } catch { return null }
}
export function rememberCommunicationDestination(value, storage = globalThis.sessionStorage) {
  const safe = safeCommunicationDestination(value)
  if (!safe) return false
  try { storage?.setItem(key, JSON.stringify({ path: safe, at: Date.now() })) } catch {}
  return true
}
export function pendingCommunicationDestination(storage = globalThis.sessionStorage) {
  try {
    const item = JSON.parse(storage?.getItem(key) || 'null')
    if (!item || !Number.isFinite(item.at) || Date.now() - item.at > 30 * 60 * 1000 || item.at > Date.now()) return null
    return safeCommunicationDestination(item.path)
  } catch { return null }
}
export function consumeCommunicationDestination(fallback, storage = globalThis.sessionStorage) {
  const destination = pendingCommunicationDestination(storage)
  try { storage?.removeItem(key) } catch {}
  return destination || fallback
}
// The invitation secret lives only in memory through SPA sign-in. Reopen the email after a full reload.
export function retainInvitationToken(raw) {
  if (raw !== null && raw !== undefined) {
    invitationToken = /^[a-f0-9]{64}$/.test(raw) ? raw : ''
    invitationDraft = { email: '', claimCode: '' }
  }
  return invitationToken
}
export function retainInvitationDraft(draft) {
  if (draft) invitationDraft = { email: String(draft.email || ''), claimCode: String(draft.claimCode || '') }
  return { ...invitationDraft }
}
export function clearInvitationToken() { invitationToken = ''; invitationDraft = { email: '', claimCode: '' } }
