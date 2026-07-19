import { describe, expect, it } from 'vitest'
import { canManageSubscriptions } from '../../resources/js/utils/administration.js'
import { isEntitlementForbidden } from '../../resources/js/composables/axios-auth.js'

describe('server-derived administration authority', () => {
  it('does not infer administrator authority from an email address', () => {
    expect(canManageSubscriptions({ email: 'admin@fungometrics.com' })).toBe(false)
    expect(canManageSubscriptions({
      email: 'ordinary@example.com',
      capabilities: { subscription_admin: true },
    })).toBe(true)
  })

  it('refreshes access only for entitlement-specific forbidden responses', () => {
    expect(isEntitlementForbidden({ response: { status: 403, data: {} } })).toBe(false)
    expect(isEntitlementForbidden({
      response: { status: 403, data: { required_entitlement: 'heat_maps' } },
    })).toBe(true)
    expect(isEntitlementForbidden({
      response: { status: 401, data: { required_entitlement: 'heat_maps' } },
    })).toBe(false)
  })
})
