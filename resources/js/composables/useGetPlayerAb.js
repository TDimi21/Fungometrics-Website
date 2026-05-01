import { useLiveABStore } from '@/store/liveAB.js'
import { storeToRefs } from 'pinia'
import defaultIMg from '@/assets/img/noavatar.png'

export const useGetPlayerAb = () => {
  const useLiveAB = useLiveABStore()
  const { teamsAndPlayers } = storeToRefs(useLiveAB)

  const getPlayerInfo = (playerId) => {
    
    let toResponse = teamsAndPlayers.value.find(item => item.id === playerId)

    return {
      avatar: toResponse.avatar != null ? toResponse.avatar : defaultIMg,
      name: toResponse.name
    }
  }

  return {
    getPlayerInfo
  }
}