export const canManageSubscriptions = (user) =>
  user?.capabilities?.subscription_admin === true
