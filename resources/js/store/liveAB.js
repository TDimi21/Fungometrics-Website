import { defineStore } from "pinia";
import { ref } from "vue";

export const useLiveABStore = defineStore('liveABStore',()=>{

  /* this data is only finally for show player name in stats */
  const teamsAndPlayers = ref([])
  /* end */

  const statusPlayers = ref([])

  const getStatusPlayer = (idPitcher, idHitter) => {
    return statusPlayers.value.find(player => player.idPitcher === idPitcher && player.idHitter === idHitter)?.numPitch ?? 0
  }

  const setStatusPlayers = (idPitcher, idHitter, numPitch) => {

    let isRepeated = statusPlayers.value.find(playr => playr.idPitcher == idPitcher && playr.idHitter == idHitter)
    
    if (isRepeated == null || isRepeated == undefined) {
      statusPlayers.value.push({ idPitcher, idHitter, numPitch })    
    }

    statusPlayers.value.forEach((element, index) => {
      
      if (element.idHitter == idHitter && element.idPitcher == idPitcher) {
        statusPlayers.value[index] = { idPitcher, idHitter, numPitch }
      }
    });
  }

  const resetStatusPlayer = () => {
    statusPlayers.value = []
  }

  return{
    statusPlayers,

    setStatusPlayers,
    getStatusPlayer,
    resetStatusPlayer,
    teamsAndPlayers
  }
},
{
  persist:true,
  storage: sessionStorage
});