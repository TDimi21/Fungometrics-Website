<script setup>
  import { ref, reactive, onMounted } from 'vue'
  import ModalPlayer from './dashboard/ModalPlayer.vue';
  import { useAxiosAuth } from '@/composables/axios-auth.js'

  const { axiosGet } = useAxiosAuth()
  const props = defineProps({
    item: {
      type: Object,
      required: true
    },
  })

  const isOpenModal = ref(false)
  const isLoading = reactive({status: true})
  const dataMetric = ref({});
  const dataScore = ref({});

  const getFitnessPlayer = async() => {
    try {
      isLoading.status = true
      const { data } = await axiosGet(`player/fitness/${props.item.id}`)
      dataMetric.value = data.data
    } catch (error) {
      console.log(error);
    } finally {
      isLoading.status= false
    }
  }

  const getScorePlayer = async() => {
    try {
      isLoading.status = true
      const { data } = await axiosGet(`coach/statistics/${props.item.id}`)
      dataScore.value = data.data
    } catch (error) {
      console.log(error);
    } finally {
      isLoading.status= false
    }
  }

  const showModal = async () => {
    await getScorePlayer()
    await getFitnessPlayer()
    isOpenModal.value = true;
  };

  const close = () =>{
    console.log(props.item.avatar);
    isOpenModal.value = false;
  }
</script>
<template>
  <div
    class="card-player flex flex-col items-center cursor-pointer relative"
    @click="showModal()"
  >
    <!-- Player Name -->
    <div class="w-full text-center text-white font-fungo-800 text-[13px] py-1 px-2 truncate">
      {{ item.name.full }}
    </div>

    <!-- Avatar with number badge -->
    <div class="relative mx-auto">
      <template v-if="item.avatar != null">
        <img :src="item.avatar" alt="" class="player-avatar object-cover">
      </template>
      <img v-else src="../assets/img/layout/logofungo-nav.png" alt="" class="player-avatar object-cover">
      <!-- Number badge -->
      <div class="number-badge">#{{ item.shirt_number ?? '-' }}</div>
    </div>

    <!-- Stats row -->
    <div class="flex flex-row justify-around w-full mt-2 pb-2 px-2">
      <div class="text-center">
        <div class="text-[10px] text-gray-400 font-fungo-700 uppercase tracking-wide">Velo</div>
        <div class="text-white font-fungo-800 text-[14px]">{{ dataScore?.velo ?? '-' }}</div>
      </div>
      <div class="border-l border-gray-600"></div>
      <div class="text-center">
        <div class="text-[10px] text-gray-400 font-fungo-700 uppercase tracking-wide">EV</div>
        <div class="text-white font-fungo-800 text-[14px]">{{ dataScore?.ev ?? '-' }}</div>
      </div>
    </div>
  </div>
  <ModalPlayer @closeModal="close()" :isOpen="isOpenModal" :item="item" :response="dataMetric" :score="dataScore" v-if="isOpenModal"></ModalPlayer>
</template>
<style scoped>
.card-player {
  background: linear-gradient(160deg, #1a2a4a 0%, #0d1b35 100%);
  border-radius: 12px;
  width: 140px;
  min-height: 190px;
  margin: 6px;
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 4px 15px rgba(0,0,0,0.4);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.card-player:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.5);
}
.player-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  border: 3px solid rgba(255,255,255,0.15);
  display: block;
  margin: 0 auto;
}
.number-badge {
  position: absolute;
  bottom: 2px;
  right: 4px;
  background: #c0392b;
  color: white;
  font-size: 11px;
  font-weight: 800;
  border-radius: 10px;
  padding: 1px 6px;
  border: 1px solid rgba(255,255,255,0.3);
}

::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}
::-webkit-scrollbar-button {
  width: 0px;
  height: 0px;
}
::-webkit-scrollbar-thumb {
  background: #e41111;
  border: 0px none #ffffff;
  border-radius: 5px;
}
::-webkit-scrollbar-thumb:hover {
  background: #ffffff;
}
::-webkit-scrollbar-thumb:active {
  background: #000000;
}
::-webkit-scrollbar-track {
  background: #666666;
  border: 22px solid #918383;
  border-radius: 4px;
}
::-webkit-scrollbar-track:hover {
  background: #e41111;
}
::-webkit-scrollbar-track:active {
  background: #333333;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
