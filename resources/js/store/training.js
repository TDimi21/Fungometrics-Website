import {defineStore} from "pinia";
import {ref} from "vue";

export const useTrainingStore = defineStore('trainingActive',()=>{
    const isShowMsgModal = ref(false)
    const playersToSendMsg = ref([])
    const idTrainingToSendMsg = ref()
    const countBalls = ref(0)
    const countSets = ref(0)
    const countThrow = ref(0)
    const countThrowForSet = ref(0)
    const countThrowArray = ref({})
    const selectedRowID = ref("")

    const trainingActive = ref('');
    // status liveAB
    const liveTurn = ref(1)
    const livePitches = ref(0)

    const setDataTraining = (data)=> trainingActive.value = data
    const setCountBallsTraining = (data)=> countBalls.value = data
    const setCountThrowTraining = (data)=> countThrow.value = data
    const countSetTrainingMode = (data)=> countSets.value = data

    const responseTraining = ref({});

    const increasePitches = () => {
      livePitches.value++
    }

    const increaseTurn = () => {
      liveTurn.value++
    }

    const resetTurn = () => {
      liveTurn.value = 1
    }

    const statusPitchPlayer = ref({})

    const addPLayerInfo = ( key, value) => {
      statusPitchPlayer.value[key] = value
    }

    const getPlayerInfo = (key) => {
      return statusPitchPlayer.value[key]
    }

    const cleanListPlayer = () => {
      statusPitchPlayer.value = {}
    }

    return{
      trainingActive,
      liveTurn,
      livePitches,
      responseTraining,
      isShowMsgModal,
      playersToSendMsg,
      idTrainingToSendMsg,
      statusPitchPlayer,
      countBalls,
      countSets,
      countThrowForSet,
      countThrow,
      countThrowArray,
      selectedRowID,

      setDataTraining,
      setCountBallsTraining,
      setCountThrowTraining,
      countSetTrainingMode,
      increaseTurn,
      increasePitches,
      addPLayerInfo,
      getPlayerInfo,
      cleanListPlayer,
      resetTurn
    }
  },
  {
    persist:true,
    storage: sessionStorage
  });
