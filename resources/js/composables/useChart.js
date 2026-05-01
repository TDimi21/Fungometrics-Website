import { ref, onMounted, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team.js'
import { usePlayerStore } from '@/store/players.js'
import {useUserStore} from "../store/user";
import {toast} from "@/utils/AlertPlugin"

const useChart = () => {
  const { axiosGet, axiosPost } = useAxiosAuth()
  const { team } = useTeamStore()
  const { players } = usePlayerStore()

  const ballStrike = ref([])
  const ballStrikeSeries = ref([])
  const isloading = ref(false)
  const directional = ref([])
  const typeHitsBatting = ref(null)
  const typeHitsPitching = ref(null)
  const pitchThrows = ref(null)
  const pitchVelocityAverage = ref(null)
  const launchAngleAverage = ref()
  const smTake = ref()
  const seriesDinamicChart = ref([])
  const optionsPlayer = ref()
  const {userData} = useUserStore();
  const monthsShow = ref([])

  const formModel = ref({
    type: 1,
    players: [],
    range: 12
  })

  const getStaticChartData = async() => {
    try {
      isloading.value = !isloading.value
      const { data } = await axiosGet(`dashboard/${team.id}`)
      ballStrike.value = data.data['b/s']
      ballStrikeSeries.value = [ballStrike.value.balls.count, ballStrike.value.strikes.count]
      directional.value = data.data.directional_percents
      typeHitsBatting.value = data.data.type_hits_batting_percents
      typeHitsPitching.value = data.data.type_hits_pitching_percents
      pitchThrows.value = data.data.pitch_throws
      pitchVelocityAverage.value = data.data.pitch_velocity_average
      launchAngleAverage.value = data.data.launch_angle_average_velocity
      smTake.value = data.data.swing_miss_take_percents
    } catch (error) {
      console.log(error);
    } finally {
      isloading.value = !isloading.value
    }
  }

  const setPlayerData = async() => {
    let player = []
    for (const iterator of players) {
      let id = iterator.id
      player.push(id)
    }
    formModel.value.players = await player
  }



  const getFilteredDataChart = async() => {
    try {
      isloading.value != isloading.value
      const { data } = await axiosPost('charts',{
        team: team.id,
        type: formModel.value.type,
        players: formModel.value.players,
        range: formModel.value.range
      })
      // console.log(data.data);
      setSeriesDinamicChart(data.data)

    } catch (error) {
      if (error.response.data.data.length === 0) {
        seriesDinamicChart.value = []
        toast.fire({
          icon: 'warning',
          title: 'No found data',
        })
      }
    } finally {
      isloading.value != isloading.value
    }
  }

  const getFilteredDataChartPlayer = async() => {
    let message = ''
    try {
      const { data } = await axiosPost('charts',{
        type: formModel.value.type,
        players: formModel.value.players,
        range: formModel.value.range
      })

      // console.log('aqui', data.data);
      if(Object.entries(data.data).length != 0){
        setSeriesDinamicChartPlayer(data.data)
      }else{
        message = 'error'
      }
    } catch (error) {
      console.log(error);
      message = 'error'
    }
    return message;
  }

  const setSeriesDinamicChartPlayer = (response) => {
    seriesDinamicChart.value = []

    let playerValues = []
    Object.entries(response).forEach(([dates, data]) => {
      Object.entries(data).forEach(([index, item]) => {
        item.forEach(itm => {
          playerValues.push(itm)

        })
      })
    })

    seriesDinamicChart.value = [{
      name: userData.name.full,
      data: playerValues
    }]

    seriesDinamicChart.value = orderByDatePlayer(seriesDinamicChart.value)
  }

  const setSeriesDinamicChart = (response) => {
    seriesDinamicChart.value = []
    
    let playersValues = []
    let datesToShow = []

    Object.entries(response).forEach(([dates, playersData], ind) => {
      datesToShow.push(dates)

      Object.entries(playersData).forEach(([idPlayer, values], index) => {
        let allValues = []

        values.forEach(item => {
          allValues.push(item)
        })

        

        let playerName = players.find(item => item.id == idPlayer).name.full

        playersValues = [
          {
            name: playerName,
            data: allValues,
            customID: `${ind}${index}`,
            indentification: idPlayer
          },
           ...playersValues
        ]
        
      })

    })

    seriesDinamicChart.value = myClearRepeated(playersValues)

    monthsShow.value = datesToShow
    // console.log('ddd', seriesDinamicChart.value);
    
    monthsShow.value.sort()
  }

  const myClearRepeated = (playersValues) => {
    const resultMap = new Map();

    // Itera a través del arreglo original y combina los objetos con el mismo "name"
    playersValues.forEach(item => {
        if (!resultMap.has(item.indentification)) {
            resultMap.set(item.indentification, { ...item });
        } else {
            const existingItem = resultMap.get(item.indentification);
            existingItem.data = existingItem.data.concat(item.data);
        }
    });

    // Convierte el Map nuevamente a un arreglo
    const mergedData = Array.from(resultMap.values());


    return ordenarPorFecha(mergedData)
  }

  function ordenarPorFecha(arr) {
    arr.forEach(item => {
        // Convierte la fecha en cada elemento de data.x en un objeto Date para que sea comparable
        item.data.forEach(dataItem => {
            dataItem.x = new Date(dataItem.x);
        });
        // Ordena el arreglo data por fecha (data.x) en orden ascendente
        item.data.sort((a, b) => a.x - b.x);
    });
    // Ordena el arreglo principal por la fecha más temprana en data.x del primer elemento de data
    arr.sort((a, b) => a.data[0].x - b.data[0].x);
    return arr;
  }

  const orderByDatePlayer = (data) => {
    data[0].data.forEach(dataItem => {
      dataItem.x = new Date(dataItem.x);
    });

    data[0].data.sort((a, b) => a.x - b.x);

    return data;
  }

  const loadOnMounted = async() => {
    if (userData.type != 'player') {
      await setPlayerData()
      setTimeout(() => {
        getFilteredDataChart()
      }, 1000)
    }

  }
  // onMounted(async() => {
  // })

  const getFormatterDate = (dateString) => {
    const theDate = new Date(dateString)
    const options = { weekday: 'short', month: 'short', day: 'numeric' }

    const dateFormatter = theDate.toLocaleDateString('en-US', options)

    return dateFormatter
  }

  watch(
    () => formModel.value.players.length,
    (prev, next) => {
      if (prev !== 9 && next !== 0){
        getFilteredDataChart()
      }
    },
  )

  return {
    formModel,
    ballStrike,
    ballStrikeSeries,
    isloading,
    directional,
    typeHitsBatting,
    pitchThrows,
    pitchVelocityAverage,
    typeHitsPitching,
    launchAngleAverage,
    smTake,
    seriesDinamicChart,
    monthsShow,

    getFilteredDataChart,
    getFilteredDataChartPlayer,
    getFormatterDate,
    loadOnMounted,
    getStaticChartData
  }
}

export default useChart
