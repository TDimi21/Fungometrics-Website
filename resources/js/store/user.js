import {defineStore} from "pinia";
import {ref} from "vue";

export const useUserStore = defineStore('user',()=>{
  const userData = ref('');
  const setData = (user)=> userData.value = user

  return{
    userData,
    setData
  }
},
  {
    persist:true,
    storage: sessionStorage
  });
