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
  <div class="fixed inset-0 z-50 flex justify-center items-center px-4">
    <div class="flex flex-col w-full max-w-2xl bg-app-navy border border-white/10 rounded-2xl shadow-2xl overflow-hidden max-h-[80vh]">

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
        <div class="flex items-center gap-2">
          <div class="w-1 h-5 bg-app-blue rounded-full" />
          <h2 class="text-white font-fungo-700 text-lg">Add Existing Coach</h2>
        </div>
        <button @click="close" class="text-app-muted hover:text-white transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Search bar -->
      <div class="px-6 py-4 bg-app-surface border-b border-white/10">
        <div class="flex flex-col sm:flex-row gap-3 items-end">
          <div class="flex-1">
            <label class="block text-app-muted text-xs font-fungo-700 uppercase tracking-wide mb-1">Mobile Number</label>
            <InutTel v-model="dataCoach.mobileNumber" inputType="tel" class="w-full" />
          </div>
          <button
            @click="searchCoach"
            class="flex items-center gap-2 bg-app-blue hover:bg-app-blue/80 text-white text-sm font-fungo-700 px-4 py-2.5 rounded-xl transition whitespace-nowrap"
          >
            <SearchIcon />
            Search
          </button>
        </div>
      </div>

      <!-- Results -->
      <div class="overflow-y-auto flex-1 px-6 py-4">
        <CoachCard :data="tableData" v-if="showCard" :isLoading="isLoading.status" />
      </div>

      <!-- Pagination -->
      <div v-if="coachLinks.length" class="flex justify-end gap-1 px-6 py-3 border-t border-white/10">
        <button
          v-for="(page, index) in coachLinks"
          :key="index"
          class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-fungo-700 transition"
          :class="page.active
            ? 'bg-app-blue border-app-blue text-white'
            : 'bg-app-card border-white/10 text-app-muted hover:border-app-blue/50 hover:text-white'"
          @click="searchCoach(page.label)"
        >
          <span v-if="page.label.includes('Prev')"><ArrowHeadLeftIcon classes="w-4 h-4"/></span>
          <span v-else-if="page.label.includes('Next')"><ArrowHeadRightIcon classes="w-4 h-4"/></span>
          <span v-else>{{ Number.parseInt(index, 10) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-thumb { background: #FF2B4A; border-radius: 5px; }
::-webkit-scrollbar-track { background: #1A1F45; border-radius: 4px; }
</style>
