<script setup>
import { InutTel } from '@/components/form'
import { SearchIcon } from '@/components/icons'
import {ref, onMounted, reactive} from 'vue'
import { CoachCard } from './index'
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

  let dataCoach = reactive({
    mobileNumber: '',
  })
  const coachLinks = ref([])

  const searchCoach = async(page = 1) => {
    const data = {}
    try {
      isLoading.status = true
      await axiosGet(`coach/search/coaches?search=${dataCoach.mobileNumber}&page=${page}`, data).then((response) => {
        if (response) {
          tableData.value = response.data.data.data
          coachLinks.value = response.data.data.links
        }
      })
    } catch (error) {
      console.log(error.response.data);
      const codeError = error.response.data.code
      if(codeError == "043-E"){
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
  }

  // const searchCoach = async(page = 1) => {
  //   const data = {}
  //   if(dataCoach.mobileNumber == ""){
  //     await toast.fire({
  //       icon: 'error',
  //       title: 'Search Error !!!',
  //       text: "Field is required",
  //     })
  //   }else{
  //     try {
  //       isLoading.status = true
  //       // tableData.value = []
  //       await axiosGet(`coach/search/coaches?search=${dataCoach.mobileNumber}`, data).then((response) => {
  //         if (response) {
  //           tableData.value = response.data.data.data
  //         }
  //       })
  //     } catch (error) {
  //       console.log(error.response.data);
  //       const codeError = error.response.data.code
  //       if(codeError == "043-E"){
  //         await toast.fire({
  //           icon: 'error',
  //           title: 'Search Error !!!',
  //           text: "Not  Results Found",
  //         })
  //       }else{
  //         await toast.fire({
  //           icon: 'error',
  //           title: 'Error get data',
  //           text: 'Yo can try with a different type of user',
  //         })
  //       }
  //     }finally {
  //       isLoading.status = false
  //     }
  //   }
  // }

  const close = () => {
    emits("closeModal", props.isOpen)
  }

  const getRosterCoach = async(page = 1) => {
    const data = {}
    try {
      isLoading.status = true
      console.log(isLoading.status);
      await axiosGet(`coach/roster/coaches`, data)
        .then((response) => {
          if (response) {
            tableData.value = response.data.data
            showCard.value = !showCard.value
            console.log(tableData.value);
            // pages.value = response.data.meta.links
          }
        })
    } catch (error) {
      // await toast.fire({
      //   icon: 'error',
      //   title: 'Error get data',
      //   text: 'Yo can try with a different type of user',
      // })
      console.log(error.response.data);
      const codeError = error.response.data.code
      tableData.value = []
      showCard.value = !showCard.value
      isLoading.status = false
      if(codeError == "031-E"){
        // await toast.fire({
        //   icon: 'error',
        //   title: 'Search Error !!!',
        //   text: "Not  Results Found",
        // })
        console.log(error.response.data.code);
      }else{
        await toast.fire({
          icon: 'error',
          title: 'Error get data',
          text: 'Yo can try with a different type of user',
        })
      }
    } finally{
      isLoading.status = false
    }
  }

  onMounted(() => {
    // getRosterCoach()
    showCard.value = !showCard.value
    // searchCoach()
  })
</script>

<template>
  <div class="fixed inset-0 z-50 flex justify-center items-center">
    <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl
      min-h-[50%] max-h-[50%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
      <div>
        <div class="flex flex-row w-[100%] items-center px-4 ">
          <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Choose from existing coach</h1>
          <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="close">
            <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
          </div>
        </div>
        <div class="bg-fungo-gray2 flex flex-row w-[100%] items-center mb-5 py-5 px-[30%]">
          <div class="flex flex-col lg:flex-row flex-nowrap items-center space-x-3 gap-2">
            <div>
              <label for="search" class="block w-[100%] text-center lg:text-start lg:w-[65%]">Search By Mobile number</label>
              <InutTel v-model="dataCoach.mobileNumber" inputType="tel" class="inline-flex w-[100%] max-w-[100%]"/>
            </div>
            <div>
              <button @click="searchCoach"
              class="bg-fungo-darkblue inline-flex rounded-lg w-10 h-10 items-center justify-center p-2 mt-4 lg:mt-6">
                <SearchIcon />
              </button>
            </div>
          </div>
        </div>
        <CoachCard :data="tableData" v-if="showCard" :isLoading="isLoading.status"></CoachCard>
        <div class="pagination flex justify-end items-center px-[10%] md:px-[5%] mt-12">
          <button
            v-for="(page, index) in coachLinks"
            :key="index"
            class="bg-white border border-fungo-darkblue w-[40px] h-[40px]"
            :class="{ 'bg-fungo-lightblue' : page.active }"
            @click=" searchCoach(page.label) "
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
</style>
