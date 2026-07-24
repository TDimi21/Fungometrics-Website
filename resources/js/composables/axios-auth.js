import axios from "axios"
import { getAuthToken } from '@/utils/authToken.js'

export const isEntitlementForbidden = error =>
  error?.response?.status === 403
  && typeof error?.response?.data?.required_entitlement === 'string'
  && error.response.data.required_entitlement.length > 0

export const useAxiosAuth = () => {
  const apiBaseUrl = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
  const withAccessRefreshSignal = promise => promise.catch(error => {
    if (
      isEntitlementForbidden(error)
      && typeof window !== 'undefined'
    ) {
      window.dispatchEvent(new CustomEvent('fmtrx-access-forbidden'))
    }
    throw error
  })

  const resolveToken = () => {
    return getAuthToken() || null
  }

  const authHeaders = (isMultipart = false) => {
    const token = resolveToken()
    return {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(!isMultipart ? { 'Content-Type': 'application/json' } : {}),
    }
  }

  const axiosPost = (url, data) => {
    const isMultipart = typeof FormData !== 'undefined' && data instanceof FormData
    return withAccessRefreshSignal(axios.post(apiBaseUrl + url, data,{
      headers: authHeaders(isMultipart),
      withCredentials: true,

    }))
  }

  const axiosGet = (url, data) => {
    return withAccessRefreshSignal(axios.get(apiBaseUrl + url, {
      params: {
        ...data
      },
      headers: authHeaders(),
      withCredentials: true,
    }))
  }
  const axiosPut = (url, data) => {
    return withAccessRefreshSignal(axios.put(apiBaseUrl + url, data,{
      headers: authHeaders(),
      withCredentials: true,

    }))
  }
  const axiosDelete = (url, id) => {
    return withAccessRefreshSignal(axios.delete(apiBaseUrl + url + id, {
      headers: authHeaders(),
      withCredentials: true,
    }))
  }
  return {
    axiosPost,
    axiosGet,
    axiosPut,
    axiosDelete,
  }
}


/*
esto solo se aplica a las apariciones en el plato donde el "0-0 pitch es un strike" si esto es cierto, evaluaremos el resultado de la aparición en el plato para determinar FPSo%, FPSw%, FPSh%

Aquí hay algunos ejemplos, luego puede determinar la mejor lógica para el código:

***Los lanzamientos 1-4 son una aparición en el plato
el lanzamiento 1 fue el primer lanzamiento (0-0) y fue un Strike → por lo tanto, incluimos este resultado en nuestro subconjunto de FPS

Ahora que hemos determinado que se trata de un FPS, debemos ir al paso final de la apariencia del plato, que es el paso 4.

El resultado fue Out/E, por lo que este resultado se considera un FPSo. FPEs decir, cuando el FirstPitch fue un strike, el resultado fue un Out/E o K

Esto también se consideraría un turno al bate de 4 lanzamientos porque hay 4 lanzamientos lanzados

***la próxima aparición en el plato es en los lanzamientos 5-7
El primer lanzamiento (0-0) fue una bola, por lo que NO incluimos esta aparición en el plato en nuestros resultados de FPS.

El primer lanzamiento (0-0) fue un strike (lanzamiento 8), por lo tanto, incluimos esta aparición en el plato en nuestros resultados de FPS.

El resultado del PA fue el lanzamiento 11, que fue un K, por lo que se consideraría un FPS0, porque hubo un First Pitch Strike lanzado y el resultado fue Out/E o K.

*/
