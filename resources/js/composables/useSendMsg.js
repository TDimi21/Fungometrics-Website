import { ref } from 'vue'
import { usePlayerStore } from '@/store/players.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from "@/utils/AlertPlugin"

const useSendMsg = () => {
  const { players } = usePlayerStore()
  const { axiosPost, axiosGet } = useAxiosAuth()

  const isSending = ref(false)
  const isShowMsgModal = ref(false)
  const isShowMsgModalStatus = ref(false)
  const playersStatus = ref()
  const playersToSend = ref([])
  const palyersStore = players
  const statusMsg = ref('')
  
  const openSendMsgWindow = (dataIds, typeTraining = 'other') => {
    playersToSend.value = []

    if( typeTraining == 'other'){
      Object.keys(dataIds).forEach(item => {
        let newPlayer = palyersStore.find(itemPlayer => itemPlayer.id == item)

        playersToSend.value.push({
          id: newPlayer.id,
          avatar: newPlayer.avatar,
          isChecked: true,
          name: newPlayer.name.full,
          phone: newPlayer.phone,
          number_in_shirt: newPlayer.number_in_shirt
        })
      })
    } else if ( typeTraining == 'mode') {
      dataIds.forEach(item => {
        let newPlayer = palyersStore.find(itemPlayer => itemPlayer.id == item)
        playersToSend.value.push({
          id: newPlayer.id,
          avatar: newPlayer.avatar,
          isChecked: true,
          name: newPlayer.name.full,
          phone: newPlayer.phone,
          number_in_shirt: newPlayer.number_in_shirt
        })
      })
    }
    
    isShowMsgModal.value = !isShowMsgModal.value

  }

  const closeMsgWindow = () => {
    isShowMsgModal.value = !isShowMsgModal.value
  }

  const sendMsg = async(id) => {
    isSending.value = !isSending.value
    const playersChecked = playersToSend.value.filter(item => item.isChecked == true)
    let clearPlayers = []
    
    playersChecked.forEach(item => {
      clearPlayers.push({
        id: item.id,
        name: item.name,
        phone: item.phone
      })
    })

    try {
      await axiosPost(`coach/send/results/${id}`, { players: clearPlayers } ).then(res => {
        if (res) {
          toast.fire({
            icon: 'success',
            title: 'Send Message',
            text: res.data.message,
          })

          getSmsPlayers(id)
        }
      })
    } catch (error) {
      toast.fire({
        icon: 'warning',
        title: 'Error to send message',
        text: res.data.message,
      })
    } finally {
      isSending.value = !isSending.value
    }
  }

  const openStatusModal = (idPractice) => {
    isShowMsgModalStatus.value = !isShowMsgModalStatus.value
    getSmsPlayers(idPractice)
  }

  const getSmsPlayers = async(idPractice) => {
    statusMsg.value = ''
    try {
      const { data } = await axiosGet(`coach/list/results/${idPractice}`)
      playersStatus.value = data.data
      statusMsg.value = data.data.status_send
    } catch (error) {
      if (error.response.statusText == 'Not Found') {
        statusMsg.value = error.response.data.status
      }
    }
  }


  return {
    isShowMsgModal,
    isShowMsgModalStatus,
    playersStatus,
    playersToSend,
    isSending,
    statusMsg,

    sendMsg,
    openSendMsgWindow,
    closeMsgWindow,
    getSmsPlayers,
    openStatusModal
  }
}

export default useSendMsg