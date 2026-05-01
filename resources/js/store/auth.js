import {defineStore} from "pinia";
import {ref} from "vue";

export const useAuthStore = defineStore('auth',()=>{
    const isLogged = ref({status: false});
    const token = ref('');
    const setToken = (data)=> token.value = data
    return{
      isLogged,
      token,
      setToken
    }
  },
  {
    persist:true,
    storage: sessionStorage
  });
