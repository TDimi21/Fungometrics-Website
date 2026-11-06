import {ref} from 'vue'

export const apiBaseUrl = `${String(import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '/api/').replace(/\/+$/, '')}/`
export const apiAuthorizationFailed = ref(false)

// Cookie-authenticated requests must not send placeholder bearer credentials.
// Preserve real tokens, including the restricted tokens used during claiming.
export function prepareApiRequest(config, origin = window.location.origin) {
  const base = new URL(apiBaseUrl, origin)
  const target = new URL(config.url || '', new URL(config.baseURL || origin, origin))
  if (target.origin !== base.origin || !target.pathname.startsWith(base.pathname)) return config
  config.withCredentials = true
  const headers = config.headers || (config.headers = {})
  for (const key of Object.keys(headers)) {
    if (key.toLowerCase() === 'authorization' && /^Bearer\s*(?:undefined|null|\[object Object\])?\s*$/i.test(String(headers[key]))) delete headers[key]
  }
  if (!headers.Accept) headers.Accept = 'application/json'
  return config
}

export function installApiTransport(axios) {
  axios.interceptors.request.use(config => prepareApiRequest(config))
  axios.interceptors.response.use(response => {
    if (/\/me\/access(?:\?|$)/.test(response.config?.url || '')) apiAuthorizationFailed.value = false
    return response
  }, error => {
    const target = new URL(error.config?.url || '/', window.location.origin)
    const base = new URL(apiBaseUrl, window.location.origin)
    if (error.response?.status === 401 && target.origin === base.origin && target.pathname.startsWith(base.pathname)
        && !/\/(login|player\/join|complete\/)/.test(target.pathname)) apiAuthorizationFailed.value = true
    return Promise.reject(error)
  })
}
