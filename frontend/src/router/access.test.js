import { describe, expect, it } from 'vitest'
import { canAccessRoles, canPreloadManagementPages } from './access.js'

describe('route access and preloading', () => {
  it('does not preload or allow management pages for a member', () => {
    const member = { role: 'member', roles: ['member'] }
    expect(canPreloadManagementPages(member)).toBe(false)
    expect(canAccessRoles(member, ['guild_leader', 'developer'])).toBe(false)
  })

  it('supports users with multiple elevated roles', () => {
    const leader = { role: 'member', roles: ['member', 'party_leader'] }
    expect(canPreloadManagementPages(leader)).toBe(true)
  })

  it('rejects protected pages when no user is authenticated', () => {
    expect(canAccessRoles(null, ['guild_leader'])).toBe(false)
  })
})

