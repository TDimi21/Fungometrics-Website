<script setup>
import { InputBase, InutTel } from '@/components/form'
import { SearchIcon } from '@/components/icons'
import {ref, onMounted, reactive} from 'vue'
import { PlayerCard } from './index'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from "@/utils/AlertPlugin"
import { ArrowHeadRightIcon, ArrowHeadLeftIcon } from '@/components/icons'

  const { axiosGet } = useAxiosAuth()
  const props = defineProps({
    isOpen: {
      type: Boolean,
      required: true
    },
  })

  const emits = defineEmits(["closeModal"])
  const showCard = ref(false)
  const tableData = ref([])
  const isLoading = reactive({status: false})
  const playerLinks = ref([])

  let dataPlayer = reactive({
    name: '',
    mobileNumber: '',
  })

  const searchPlayers = async(page = 1) => {
    const data = {}
    // tableData.value = []
    // if(dataPlayer.name == "" || dataPlayer.mobileNumber == ""){
    //   await toast.fire({
    //     icon: 'error',
    //     title: 'Search Error !!!',
    //     text: "Both fields are required",
    //   })
    // }else{
      try {
        isLoading.status = true
        await axiosGet(`coach/search/players?phone=${dataPlayer.mobileNumber}&name=${dataPlayer.name.toLowerCase()}&page=${page}`, data).then(
          (response) => {
            if (response) {
              tableData.value = response.data.data
              playerLinks.value = response.data.links
            }
          }
        )
      } catch (error) {
        const codeError = error.response.data.code
        if(codeError == "042-E"){
          await toast.fire({
            icon: 'error',
            title: 'Search Error !!!',
            text: "Not  Results Found",
          })
        }else{
          await toast.fire({
            icon: 'error',
            title: 'Error get data',
            text: 'Yo can try with a different type of user',
          })
        }
      }finally {
        isLoading.status = false
      }
    // }
  }

  const close = () => {
    emits("closeModal", props.isOpen)
  }

  const getRosterPlayers = async(page = 1) => {
    const data = {}
    try {
      showCard.value = !showCard.value
      isLoading.status = true
      await axiosGet(`coach/roster/players`, data)
        .then((response) => {
          if (response) {
            tableData.value = response.data.data
            console.log(tableData.value);
            // pages.value = response.data.meta.links
          }
        })
    } catch (error) {
      await toast.fire({
        icon: 'error',
        title: 'Error get data',
        text: 'Yo can try with a different type of user',
      })
    } finally{
      isLoading.status = false
    }
  }

  onMounted(() => {
    showCard.value = !showCard.value
  })
</script>

<template>
  <div class="fixed inset-0 z-50 flex justify-center items-center">
    <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[50%]
    max-h-[50%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
      <div>
        <div class="flex flex-row w-[100%] items-center mb-5 px-4 ">
          <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Choose from existing player</h1>
          <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="close">
            <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
          </div>
        </div>
        <div class="bg-fungo-gray2 flex flex-row w-[100%] items-center mb-5 py-10 px-[15%]">
          <div class="flex flex-col lg:flex-row flex-nowrap items-center space-x-3 gap-2">
            <div>
              <label for="search" class="block w-[100%] text-center lg:text-start lg:w-[65%]">Search by name</label>
              <InputBase v-model="dataPlayer.name" class="inline-flex w-[100%] max-w-[100%]"/>
            </div>
            <div>
              <label for="search" class="block w-[100%] text-center lg:text-start lg:w-[65%]">Search By Mobile number</label>
              <InutTel v-model="dataPlayer.mobileNumber" class="inline-flex w-[50%] max-w-[50%]"/>
            </div>
            <div>
              <button @click="searchPlayers"
              class="bg-fungo-darkblue inline-flex rounded-lg w-10 h-10 items-center justify-center p-2 mt-4 lg:mt-6">
                <SearchIcon />
              </button>
            </div>
          </div>
        </div>
        <PlayerCard :data="tableData" v-if="showCard" :isLoad="isLoading.status"></PlayerCard>
        <div class="pagination flex justify-end items-center px-[10%] md:px-[5%] mt-12">
          <button
            v-for="(page, index) in playerLinks"
            :key="index"
            class="bg-white border border-fungo-darkblue w-[40px] h-[40px]"
            :class="{ 'bg-fungo-lightblue' : page.active }"
            @click=" searchPlayers(page.label) "
          >
            <span
              v-if="page.label.includes('Prev')"
              class="flex justify-center items-center"
            >
              <ArrowHeadLeftIcon classes="w-[30px] h-[30px]"/>
            </span>

            <span
              v-else-if="page.label.includes('Next')"
              class="flex justify-center items-center"
            >
              <ArrowHeadRightIcon classes="w-[30px] h-[30px]"/>
            </span>

            <span v-else>
              {{ Number.parseInt(index, 10) }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
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
.pagination button:first-child {
  border-radius: 10px 0 0 10px;
}
.pagination button:last-child {
  border-radius: 0 10px 10px 0;
}
</style>
