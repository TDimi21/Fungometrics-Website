import { useLiveABStore } from '@/store/liveAB.js'
import { storeToRefs } from 'pinia'
import defaultIMg from '@/assets/img/login/assteslogin/updatedlogo.png'

export const useGetPlayerAb = () => {
  const useLiveAB = useLiveABStore()
  const { teamsAndPlayers } = storeToRefs(useLiveAB)

  const getPlayerInfo = (playerId) => {
    let toResponse = teamsAndPlayers.value.find(item => String(item.id) === String(playerId))

    if (!toResponse) {
      return { avatar: defaultIMg, name: { first: '', last: playerId, full: playerId } }
    }

    return {
      avatar: toResponse.avatar != null ? toResponse.avatar : defaultIMg,
      name: toResponse.name
    }
  }

  return {
    getPlayerInfo
  }
}