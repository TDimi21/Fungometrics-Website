<script setup>
import { storeToRefs } from 'pinia'
import {ref, onMounted, defineProps, onUpdated} from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { toast } from "@/utils/AlertPlugin"
import { useTeamStore } from '@/store/team.js'
import { SearchIcon } from '@/components/icons'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { ArrowHeadRightIcon, ArrowHeadLeftIcon } from '@/components/icons'
import { PracticeTitle, PracticeTableLiveAB } from '@/components/practice'
import { SelectField, InputBase, BigButtonField } from '@/components/form'
import BattingLogoPractice from "@/components/graphics/BattingLogoPractice.vue"

const useTeam = useTeamStore()
const { axiosGet } = useAxiosAuth()
const router = useRouter()

const { team } = storeToRefs(useTeam)
const entriesModel = ref('')
const searchPractice = ref('')
const tableData = ref([])
const pages = ref([])
const entriesOption = ref(
  {
    one: 'Select Entries'
  }
)
const isLoading = ref(false)
const props = defineProps({
  slug: {
    type: String
  }
})

const getTrainigsByType = async(page = 1) => {
  const data = {
    type: 'L',
    search: searchPractice.value
  }
  try {
    isLoading.value = !isLoading.value
    await axiosGet(`sessions/all/liveab?page=${page}`, data)
      .then(response => {
        tableData.value = response.data.data
        pages.value = response.data.meta.links
      })

  } catch (error) {
    /* no foun data */
  } finally {
    isLoading.value = !isLoading.value
  }
}

const getPaginate = (pageNumber) => {
  if ( !pageNumber.includes('Prev') && !pageNumber.includes('Next')) {
    getTrainigsByType(pageNumber)
  }
}
const gotoCreateTraining = ()=>{

  router.replace({ name: 'create.ab'})
}

const search = () => {
  getTrainigsByType();
}

const updateList = () => {
  getTrainigsByType()
}

onMounted(() => {
  getTrainigsByType()
})

onUpdated(()=>{
  getTrainigsByType()
})
</script>

<template>
  <Layout>

    <PracticeTitle class="capitalize" :title="props.slug.replace('-', ' ') + ' Practice'" />

    <section class="bg-fungo-gray3 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%]">

      <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
        <div class="w-max">
          <BattingLogoPractice class="h-[80px] w-[80px] hidden lg:block" />
        </div>
        <!-- <div class="w-full lg:w-[30%]">
          <div class="flex items-center flex-nowrap space-x-3">
            <label for="entries">Show</label>
            <SelectField v-model="entriesModel" :options="entriesOption" />
          </div>
        </div> -->
        <div class="w-full lg:w-[50%]">
          <form @submit.prevent="search"
            name="search-practice"
            class="flex flex-row flex-nowrap items-center space-x-3"
          >
            <label for="search" class="block">Search</label>
            <InputBase v-model="searchPractice" inputType="search" class="inline-flex w-[65%]" />
            <button type="submit" role="submit" class="bg-fungo-red inline-flex rounded-lg w-10 h-10 items-center justify-center">
              <SearchIcon />
            </button>
          </form>
        </div>
        <div class="w-[100%] lg:w-[50%] flex justify-end">
          <BigButtonField color="dark" label="New practice" @click="gotoCreateTraining"/>
        </div>
      </div>
    </section>

    <PracticeTableLiveAB :tableData="tableData" :isLoading="isLoading" @updateList="updateList"/>

    <div class="pagination flex justify-end items-center px-[10%] md:px-[5%] mt-12">
      <button
        v-for="(page, index) in pages"
        :key="index"
        class="bg-white border border-fungo-darkblue w-[40px] h-[40px]"
        :class="{ 'bg-fungo-lightblue' : page.active }"
        @click=" getPaginate(page.label) "
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
  </Layout>
</template>
<style lang="css" scoped>
.pagination button:first-child {
  border-radius: 10px 0 0 10px;
}
.pagination button:last-child {
  border-radius: 0 10px 10px 0;
}
</style>
