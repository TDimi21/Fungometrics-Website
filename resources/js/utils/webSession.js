import axios from 'axios'
import { discardLegacyAuthToken } from '@/utils/authToken.js'

export const exchangeWebToken = async (apiUrl, token) => {
  if (!token) return

  await axios.post(`${apiUrl}auth/web-session`, {}, {
    withCredentials: true,
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      'X-FMTRX-Client': 'web',
    },
  })

  discardLegacyAuthToken()
}

export const endWebSession = async apiUrl => {
  await axios.post(`${apiUrl}logout`, {}, {
    withCredentials: true,
    headers: { Accept: 'application/json' },
  })
  discardLegacyAuthToken()
}
