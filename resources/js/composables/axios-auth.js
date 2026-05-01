import axios from "axios"

export const useAxiosAuth = () => {
  const apiBaseUrl = process.env.API_ENDPOINT
  const token = JSON.parse(localStorage.getItem('auth'))?.token

  const axiosPost = (url, data) => {
    return axios.post(apiBaseUrl + url, data,{
      headers: {
        "Authorization": `Bearer ${token}`,
        "Content-Type": "application/json",
      }

    })
  }

  const axiosGet = (url, data) => {
    return axios.get(apiBaseUrl + url, {
      params: {
        ...data
      },
      headers: {
        "Authorization": `Bearer ${token}`,
        "Content-Type": "application/json",
      }
    })
  }
  const axiosPut = (url, data) => {
    return axios.put(apiBaseUrl + url, data,{
      headers: {
        "Authorization": `Bearer ${token}`,
        "Content-Type": "application/json",
      }

    })
  }
  const axiosDelete = (url, id) => {
    return axios.delete(apiBaseUrl + url + id, {
      headers: {
        "Authorization": `Bearer ${token}`,
        "Content-Type": "application/json",
      }
    })
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
