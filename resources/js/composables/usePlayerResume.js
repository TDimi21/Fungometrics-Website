import { useRouter } from 'vue-router'
import { useTrainingStore } from "@/store/training";
import {useUserStore} from "@/store/user";
import { useAxiosAuth } from '@/composables/axios-auth.js'

export const usePlayerResume = () => {
  const activeTraining = useTrainingStore();
  const router = useRouter()
  const { axiosGet } = useAxiosAuth()
  const { userData } = useUserStore();

  const resumenBattingPlayer = (training) => {
    let newActiveTraining = training
    let players = []
    newActiveTraining.lineup = [
      {
        "id": userData.id,
        "name": {
          "full": userData.name.full,
          "first": userData.name.first,
          "last": userData.name.last
        },
        "picture": userData.avatar,
        "shirt_number": userData.shirt_number
      }
    ]
  
    newActiveTraining.players = newActiveTraining.lineup
    axiosGet('statistics/'+training.id+'/batting').then((response)=>{
      let playersStats = response.data.data.by_player
      newActiveTraining.lineup.forEach(player => {
        players.push(player)
      });
      activeTraining.cleanListPlayer()
      for (const key in playersStats) {
        if (Object.hasOwnProperty.call(playersStats, key)) {
          const element = playersStats[key];
          activeTraining.addPLayerInfo(key, {
            'balls': element.length,
          })
        }
      }
      newActiveTraining.players = players
      activeTraining.setDataTraining(newActiveTraining);
      router.push('/track/batting')
    }).catch(function(error) {
      if (error.response.status == 404) {
        newActiveTraining.lineup.forEach(player => {
          players.push(player)
        });
        activeTraining.cleanListPlayer()
        newActiveTraining.players = players
        activeTraining.setDataTraining(newActiveTraining);
        router.push('/track/batting')
      }
    });
  }

  const resumenBullpenPlayer = (training) => {
    let newActiveTraining = training
    let players = []
    newActiveTraining.lineup = [
      {
        "id": userData.id,
        "name": {
          "full": userData.name.full,
          "first": userData.name.first,
          "last": userData.name.last
        },
        "picture": userData.avatar,
        "shirt_number": userData.shirt_number
      }
    ]

    axiosGet('statistics/'+training.id+'/bullpen').then((response)=>{
      let playersStats = response.data.data.by_player
      newActiveTraining.lineup.forEach(player => {
        players.push(player)
      });
      activeTraining.cleanListPlayer()
      for (const key in playersStats) {
        if (Object.hasOwnProperty.call(playersStats, key)) {
          const element = playersStats[key];
          activeTraining.addPLayerInfo(key, {
            'pitch': element.length,
          })
        }
      }
      newActiveTraining.players = players
      activeTraining.setDataTraining(newActiveTraining);
      router.push('/track/bullpen')
    }).catch(function(error) {
      if (error.response.status == 404) {
        newActiveTraining.lineup.forEach(player => {
          players.push(player)
        });
        activeTraining.cleanListPlayer()
        newActiveTraining.players = players
        activeTraining.setDataTraining(newActiveTraining);
        router.push('/track/bullpen')
      }
    });
  }

  const resumenModePlayer = async(players) => {
    let data = {
      id: players.id,
      is_completed: players.is_completed,
      players: [userData],
      mode: players.modes,
      note: players.note,
      start: players.start,
      team: players.team,
      type: players.type
    }
  
    let modes = {
      'EV' : 'exitvelocity',
      'LT': 'longtoss',
      'WB': 'weightball'
    }
  
    await activeTraining.setDataTraining(data)
  
  
    axiosGet('statistics/'+players.id+"/"+modes[data.mode]).then((response)=>{
      let playersStats = response.data.data.by_player
      activeTraining.cleanListPlayer()

      data.players.forEach(item => {
        let data = {
          "balls": 0,
          "bxs": 0,
          "set": 1
        }
        activeTraining.countThrowArray[item.id] = data
      })
      router.push({
        path: '/track/training-mode/' + data.mode,
      });
    }).catch((error)=>{
      activeTraining.cleanListPlayer()
      data.players.forEach(item => {
        let data = {
          "throw": 0,
          "throwForSet": 0,
          "set": 1
        }
        activeTraining.countThrowArray[item.id] = data
      })
      router.push({
        path: '/track/training-mode/' + data.mode,
      });
    })
  }

  return {
    resumenBattingPlayer,
    resumenBullpenPlayer,
    resumenModePlayer
  }
}