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
    class="flex flex-row w-full h-full border
    bg-white w-[230px] h-[75px]
    md:w-[295px] h-[110px] m-[2px]
    place-items-center relative
    card-player text-fungo-darkblue cursor-pointer
    p-2 gap-3.5" @click="showModal()"
  >
    <div class="flex-grow-0">
      <div
        class="h-[45px] w-[45px] md:h-[55px]
        md:w-[55px] rounded-full
        ring-[7px] ring-fungo-gray6
        mx-1">
        <template v-if="item.avatar != null">
          <img :src="item.avatar" alt="" class="h-[45px] w-[45px] md:h-[55px] md:w-[55px] rounded-full">
        </template>
        <img v-else src="../assets/img/layout/logofungo-nav.png" alt="" class="h-[45px] w-[45px] md:h-[55px] md:w-[55px] rounded-full">
      </div>
    </div>
    <div class="flex-grow">
      <div class="flex flex-col text-[14px] items-start">
        <div class="font-fungo-800 ">{{item.name.full}}</div>
        <div class="">Jersey: <span class="text-fungo-red font-fungo-800">{{item.shirt_number}}</span></div>
        <div class="">Position:</div>
      </div>
      <div
        class="absolute
        bottom-0 right-1
        md:right-3
        bg-fungo-lightblue
        text-[14px]
        md:text-[20px]
        px-1.5 md:px-2.5
        card-player-chip">{{ item.positions != null && item.positions.length > 0 ? item.positions[0].position : "T"}}</div>
    </div>
  </div>
  <ModalPlayer @closeModal="close()" :isOpen="isOpenModal" :item="item" :response="dataMetric" :score="dataScore" v-if="isOpenModal"></ModalPlayer>

</template>
<style scoped>
.card-player {
  border-top-right-radius: 10px;
  border-top-left-radius: 10px;
  border-bottom: 3px solid #d9d9d9;
}
.card-player-chip {
  border-top-right-radius: 5px;
  border-top-left-radius: 5px;
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
