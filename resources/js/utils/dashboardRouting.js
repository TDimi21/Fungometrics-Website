// Dashboard identity comes from the authenticated account, not the last page
// visited in this browser or a saved post-login destination.
export const dashboardRedirect = (type, path, query = {}) => {
  if (type === 'coach' && path === '/player-dashboard') return { path: '/dashboard' }
  if (type === 'player' && path === '/dashboard') return { path: '/player-dashboard', query }
  return null
}
