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
  <div class="fixed inset-0 z-50 flex justify-center items-center px-4">
    <div class="flex flex-col w-full max-w-2xl bg-app-navy border border-white/10 rounded-2xl shadow-2xl overflow-hidden max-h-[80vh]">

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
        <div class="flex items-center gap-2">
          <div class="w-1 h-5 bg-app-red rounded-full" />
          <h2 class="text-white font-fungo-700 text-lg">Add Existing Player</h2>
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
            <label class="block text-app-muted text-xs font-fungo-700 uppercase tracking-wide mb-1">Name</label>
            <InputBase v-model="dataPlayer.name" class="w-full" />
          </div>
          <div class="flex-1">
            <label class="block text-app-muted text-xs font-fungo-700 uppercase tracking-wide mb-1">Mobile Number</label>
            <InutTel v-model="dataPlayer.mobileNumber" class="w-full" />
          </div>
          <button
            @click="searchPlayers"
            class="flex items-center gap-2 bg-app-red hover:bg-app-red-hover text-white text-sm font-fungo-700 px-4 py-2.5 rounded-xl transition whitespace-nowrap"
          >
            <SearchIcon />
            Search
          </button>
        </div>
      </div>

      <!-- Results -->
      <div class="overflow-y-auto flex-1 px-6 py-4">
        <PlayerCard :data="tableData" v-if="showCard" :isLoad="isLoading.status" />
      </div>

      <!-- Pagination -->
      <div v-if="playerLinks.length" class="flex justify-end gap-1 px-6 py-3 border-t border-white/10">
        <button
          v-for="(page, index) in playerLinks"
          :key="index"
          class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-fungo-700 transition"
          :class="page.active
            ? 'bg-app-red border-app-red text-white'
            : 'bg-app-card border-white/10 text-app-muted hover:border-app-red/50 hover:text-white'"
          @click="searchPlayers(page.label)"
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
