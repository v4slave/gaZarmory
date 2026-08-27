export function userRoles(user) {
  if (!user) return []
  return user.roles?.length ? user.roles : [user.role].filter(Boolean)
}

export function canAccessRoles(user, allowedRoles) {
  if (!allowedRoles?.length) return true
  return userRoles(user).some(role => allowedRoles.includes(role))
}

export function canPreloadManagementPages(user) {
  return canAccessRoles(user, ['guild_leader', 'micro_guild_leader', 'developer', 'party_leader'])
}

