import { appendPlayerPolicy } from '@/utils/playerPolicy'
import { useAxiosAuth } from './axios-auth.js'

export const resolvePlayerTeamId = team => {
  // Legacy team payloads use id=membership ID; id_team is the team UUID.
  const id = team?.id_team ?? team?.id
  return typeof id === 'string' && /^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i.test(id) ? id : null
}

export const playerCreationError = error => {
  const status = error?.response?.status
  if (status === 401) return 'Your session has expired. Sign in again before adding a player.'
  if (status === 403) return error?.response?.data?.message?.trim() || 'You do not have permission to add a player to this team. Confirm your coach account and selected team.'
  return Object.values(error?.response?.data?.errors || error?.response?.data?.data?.errors || {}).flat()[0] || error?.response?.data?.message || error?.message || 'The player could not be added. Please try again.'
}

export const useCreatePlayer = () => {
  const { axiosPost } = useAxiosAuth()
  return async (team, form, policyModel = null) => {
    const teamId = resolvePlayerTeamId(team)
    if (!teamId) throw new Error('Select a valid team before adding a player.')
    form.set('team', teamId)
    if (policyModel) appendPlayerPolicy(form, policyModel)
    // The shared client omits empty Bearer headers and includes HttpOnly cookies.
    return axiosPost('coach/add/players', form)
  }
}
