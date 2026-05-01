import {defineStore} from "pinia";
import {ref} from "vue";

export const usePlayerStore = defineStore('players',()=>{
    const players = ref([]);
    const setPlayers = (data)=> players.value = data


    return{
      players,
      setPlayers
    }
  },
  {
    persist:true,
    storage: sessionStorage
  });
