import {defineStore} from "pinia";
import {ref} from "vue";

export const useUserStore = defineStore('user',()=>{
  const userData = ref({});
  const setData = (user)=> userData.value = user

  // Merge a partial/fresh payload (e.g. player/me) into the stored user
  // without callers mutating store state directly. Mutates the existing
  // object in place so components holding a reference to it stay live.
  const mergeUserData = (fresh) => {
    if (!fresh || typeof fresh !== 'object') return
    if (!userData.value || typeof userData.value !== 'object') userData.value = {}
    Object.assign(userData.value, fresh)
  }

  return{
    userData,
    setData,
    mergeUserData
  }
},
  {
    persist:true,
    storage: sessionStorage
  });
